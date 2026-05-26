<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AuditLogger;
use App\Service\OperationLog;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;
use Cake\Utility\Security;

/**
 * M6 — NCS dashboard owner/co-logger surface. Owner controls lifecycle;
 * owner + co-loggers may log check-ins.
 */
class NetSessionsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    /**
     * Load a net session owned by the current user, or throw 404.
     *
     * Used as an authorization check on all owner-only actions (edit,
     * start, end, delete, analytics, addLogger, removeLogger).
     *
     * @param int $id Net session primary key.
     * @return \App\Model\Entity\NetSession
     * @throws \Cake\Http\Exception\NotFoundException If not found or not owned.
     */
    private function ownedOrFail(int $id): \App\Model\Entity\NetSession
    {
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $row = $this->fetchTable('NetSessions')->find()
            ->where(['id' => $id, 'owner_id' => $uid])->first();
        if ($row === null) {
            throw new NotFoundException('Net session not found.');
        }
        return $row;
    }

    /**
     * List the current user's net sessions — live, upcoming, and recent (last 50).
     *
     * @return void
     */
    public function index(): void
    {
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('NetSessions');
        $this->set([
            'live'        => $tbl->findLiveForUser($uid)->all(),
            'upcoming'    => $tbl->findUpcomingForUser($uid)->all(),
            'recent'      => $tbl->findRecentForUser($uid, 50)->all(),
            'newSession'  => $tbl->newEmptyEntity(),
            'title'       => 'Net sessions',
        ]);
    }

    /**
     * Render the new net session form (GET) or create one (POST).
     *
     * Sets `owner_id` from the authenticated identity, `status` to `'scheduled'`,
     * a unique `public_slug`, and a random `logger_token` invite link.
     *
     * @return \Cake\Http\Response|null Redirect to the view on success, null to re-render.
     */
    public function add(): ?\Cake\Http\Response
    {
        $tbl = $this->fetchTable('NetSessions');
        $session = $tbl->newEmptyEntity();
        if ($this->request->is('post')) {
            $session = $tbl->patchEntity($session, $this->request->getData());
            $uid = $this->Authentication->getIdentity()->getIdentifier();
            $session->set('owner_id', $uid, ['guard' => false]);
            $session->set('status', 'scheduled', ['guard' => false]);
            $session->set('public_slug', $this->uniqueSlug(), ['guard' => false]);
            $session->set('logger_token', strtolower(Security::randomString(20)), ['guard' => false]);
            if ($tbl->save($session)) {
                OperationLog::event('net.session.created', ['user_id' => (int)$session->owner_id, 'session_id' => (int)$session->id]);
                $this->Flash->success('Net session created.');
                return $this->redirect(['action' => 'view', $session->id]);
            }
            $this->Flash->error('Could not create the net session.');
        }
        $this->set(['session' => $session, 'title' => 'New net session']);
        return null;
    }

    /**
     * Render (GET) or save (POST/PUT) metadata for an owned net session.
     *
     * @param int $id Net session primary key.
     * @return \Cake\Http\Response|null Redirect on save, null to re-render.
     */
    public function edit(int $id): ?\Cake\Http\Response
    {
        $session = $this->ownedOrFail($id);
        if ($this->request->is(['post', 'put'])) {
            $session = $this->fetchTable('NetSessions')->patchEntity($session, $this->request->getData());
            if ($this->fetchTable('NetSessions')->save($session)) {
                OperationLog::event('net.session.updated', ['session_id' => (int)$id]);
                $this->Flash->success('Net session updated.');
                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error('Could not update the net session.');
        }
        $this->set(['session' => $session, 'title' => 'Edit net session']);
        return null;
    }

    /**
     * Transition an owned net session to `live` status and stamp `started_at`.
     *
     * @param int $id Net session primary key.
     * @return \Cake\Http\Response Redirect to the cockpit.
     */
    public function start(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $session->set('status', 'live', ['guard' => false]);
        $session->set('started_at', DateTime::now(), ['guard' => false]);
        $this->fetchTable('NetSessions')->saveOrFail($session);
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        try {
            (new AuditLogger())->log(
                event: 'net.start',
                actorUserId: $uid,
                target: ['type' => 'NetSessions', 'id' => $id],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }
        OperationLog::event('net.session.started', ['user_id' => (int)$uid, 'session_id' => (int)$id]);
        $this->Flash->success('Net is live.');
        return $this->redirect(['action' => 'cockpit', $id]);
    }

    /**
     * Transition an owned net session to `ended` status and stamp `ended_at`.
     *
     * @param int $id Net session primary key.
     * @return \Cake\Http\Response Redirect to the session view.
     */
    public function end(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $session->set('status', 'ended', ['guard' => false]);
        $session->set('ended_at', DateTime::now(), ['guard' => false]);
        $this->fetchTable('NetSessions')->saveOrFail($session);
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        try {
            (new AuditLogger())->log(
                event: 'net.end',
                actorUserId: $uid,
                target: ['type' => 'NetSessions', 'id' => $id],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }
        OperationLog::event('net.session.ended', ['user_id' => (int)$uid, 'session_id' => (int)$id]);
        $this->Flash->success('Net ended.');
        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Hard-delete an owned net session.
     *
     * @param int $id Net session primary key.
     * @return \Cake\Http\Response Redirect to the index.
     */
    public function delete(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $this->fetchTable('NetSessions')->deleteOrFail($session);
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        try {
            (new AuditLogger())->log(
                event: 'net.delete',
                actorUserId: $uid,
                target: ['type' => 'NetSessions', 'id' => $id],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }
        OperationLog::event('net.session.deleted', ['user_id' => (int)$uid, 'session_id' => (int)$id]);
        $this->Flash->success('Net session deleted.');
        return $this->redirect(['action' => 'index']);
    }

    /**
     * Detail view for an owned net session, including co-logger list.
     *
     * @param int $id Net session primary key.
     * @return void
     */
    public function view(int $id): void
    {
        $session = $this->ownedOrFail($id);
        $loggers = $this->fetchTable('NetSessionLoggers')
            ->find()
            ->where(['net_session_id' => $id])
            ->contain(['Users'])
            ->all();
        $this->set(['session' => $session, 'loggers' => $loggers, 'title' => $session->net_title]);
    }

    /**
     * Add a co-logger to an owned net session by user id.
     *
     * Silently no-ops if the user is already a logger (prevents duplicate rows).
     *
     * @param int $id Net session primary key.
     * @return \Cake\Http\Response Redirect to the session view.
     */
    public function addLogger(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $this->ownedOrFail($id);
        $userId = (int)$this->request->getData('user_id');
        $loggers = $this->fetchTable('NetSessionLoggers');
        if ($userId > 0 && !$loggers->exists(['net_session_id' => $id, 'user_id' => $userId])) {
            $loggers->saveOrFail($loggers->newEntity(
                ['net_session_id' => $id, 'user_id' => $userId, 'added_via' => 'owner'],
                ['accessibleFields' => ['net_session_id' => true, 'user_id' => true, 'added_via' => true]]
            ));
        }
        $this->Flash->success('Co-logger added.');
        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Remove a co-logger from an owned net session. Silently no-ops if not found.
     *
     * @param int $id     Net session primary key.
     * @param int $userId Co-logger user id to remove.
     * @return \Cake\Http\Response Redirect to the session view.
     */
    public function removeLogger(int $id, int $userId): \Cake\Http\Response
    {
        $this->request->allowMethod('post', 'delete');
        $this->ownedOrFail($id);
        $loggers = $this->fetchTable('NetSessionLoggers');
        $row = $loggers->find()->where(['net_session_id' => $id, 'user_id' => $userId])->first();
        if ($row) {
            $loggers->deleteOrFail($row);
        }
        $this->Flash->success('Co-logger removed.');
        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Join a net session as a co-logger via the one-time invite token.
     *
     * Already-registered loggers are silently skipped; only the first join
     * inserts a row in `net_session_loggers`. Redirects to the cockpit on
     * success. Unknown token throws 404.
     *
     * @param string $token Logger invite token from the session's invite URL.
     * @return \Cake\Http\Response Redirect to the cockpit.
     */
    public function join(string $token): \Cake\Http\Response
    {
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $session = $this->fetchTable('NetSessions')->find()->where(['logger_token' => $token])->first();
        if ($session === null) {
            throw new NotFoundException('Invalid invite link.');
        }
        $loggers = $this->fetchTable('NetSessionLoggers');
        if ($session->owner_id !== $uid && !$loggers->exists(['net_session_id' => $session->id, 'user_id' => $uid])) {
            $loggers->saveOrFail($loggers->newEntity(
                ['net_session_id' => $session->id, 'user_id' => $uid, 'added_via' => 'invite'],
                ['accessibleFields' => ['net_session_id' => true, 'user_id' => true, 'added_via' => true]]
            ));
        }
        $this->Flash->success('You can now log check-ins for this net.');
        return $this->redirect(['action' => 'cockpit', $session->id]);
    }

    /**
     * Generate a random 16-character lower-case public slug that does not
     * already exist in `net_sessions.public_slug`.
     *
     * @return string Unique slug.
     */
    private function uniqueSlug(): string
    {
        $tbl = $this->fetchTable('NetSessions');
        do {
            $slug = strtolower(Security::randomString(16));
        } while ($tbl->exists(['public_slug' => $slug]));
        return $slug;
    }

    /**
     * Load a net session for which the current user is an owner or co-logger.
     *
     * Used by actions that allow the full logger team (cockpit, checkins,
     * checkin, exportAdif, exportPdf).
     *
     * @param int $id Net session primary key.
     * @return \App\Model\Entity\NetSession
     * @throws \Cake\Http\Exception\NotFoundException If not found or no logger access.
     */
    private function loggerSessionOrFail(int $id): \App\Model\Entity\NetSession
    {
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('NetSessions');
        if (!$tbl->isLogger($id, $uid)) {
            throw new NotFoundException('Net session not found.');
        }
        return $tbl->get($id);
    }

    /**
     * M6 T21 — ADIF export per net session.
     *
     * GET /net-sessions/{id}/export.adi returns an ADIF 3.1.4 text/plain
     * document with every check-in QSO tagged to this session. The adapter
     * maps the net session to the shape AdifExporter::export() expects
     * without modifying the exporter itself.
     *
     * Logger-scoped (owner + co-loggers): trying to export a session you
     * cannot log for returns 404.
     */
    public function exportAdif(int $id): \Cake\Http\Response
    {
        $session = $this->loggerSessionOrFail($id);
        $qsos = $this->fetchTable('Qsos')->find()
            ->where(['net_session_id' => $id])
            ->orderBy(['qso_datetime_utc' => 'ASC'])->all();
        $owner = $this->fetchTable('Users')->get($session->owner_id);
        $adif = (new \App\Service\AdifExporter())->export(
            new \App\Service\NetAdifAdapter($session),
            $qsos,
            (string)$owner->callsign
        );
        return $this->response
            ->withType('text/plain')
            ->withDownload('net-' . $session->id . '.adi')
            ->withStringBody($adif);
    }

    /**
     * M6 T22 — PDF net report export.
     *
     * GET /net-sessions/{id}/export.pdf renders the full check-in roster and
     * signal stats as an A4 PDF via dompdf. Logger-scoped (owner + co-loggers):
     * a stranger gets 404.
     */
    public function exportPdf(int $id): \Cake\Http\Response
    {
        $session = $this->loggerSessionOrFail($id);
        $metrics = new \App\Service\NetMetrics($this->fetchTable('Qsos'));
        $checkins = $this->fetchTable('Qsos')->find()
            ->where(['net_session_id' => $id])
            ->orderBy(['qso_datetime_utc' => 'ASC'])->all();
        $this->set([
            'session'  => $session,
            'stats'    => $metrics->sessionStats($id),
            'checkins' => $checkins,
        ]);
        $html = $this->createView()
            ->setTemplatePath('pdf')
            ->render('net_report', false);
        $pdf = (new \App\Service\NetReportPdf())->fromHtml($html);
        return $this->response
            ->withType('application/pdf')
            ->withDownload('net-' . $session->id . '-report.pdf')
            ->withStringBody($pdf);
    }

    /**
     * Analytics dashboard for an owned net session.
     *
     * Loads session stats, map points, and retention data from NetMetrics.
     *
     * @param int $id Net session primary key.
     * @return void
     */
    public function analytics(int $id): void
    {
        $session = $this->ownedOrFail($id);
        $metrics = new \App\Service\NetMetrics($this->fetchTable('Qsos'));
        $this->set([
            'session'   => $session,
            'stats'     => $metrics->sessionStats($id),
            'mapPoints' => $metrics->mapPoints($id),
            'retention' => $metrics->retention($session->owner_id, $session->net_title),
            'title'     => $session->net_title . ' — analytics',
        ]);
    }

    /**
     * NCS real-time cockpit for owner and co-loggers.
     *
     * Renders the check-in roster in newest-first order. Logger-scoped
     * (owner + co-loggers); strangers get 404.
     *
     * @param int $id Net session primary key.
     * @return void
     */
    public function cockpit(int $id): void
    {
        $session = $this->loggerSessionOrFail($id);
        $qsos = $this->fetchTable('Qsos')->find()
            ->where(['net_session_id' => $id])
            ->orderBy(['qso_datetime_utc' => 'DESC', 'id' => 'DESC'])
            ->all();
        $this->set([
            'session'  => $session,
            'checkins' => $qsos,
            'title'    => $session->net_title . ' — cockpit',
        ]);
    }

    /**
     * Create a check-in (POST) or return the delta feed (GET).
     *
     * POST path: validates the session is live, stamps server-side fields
     * (owner_id, logged_by, net_session_id, qso_type, ncs details, band/freq/mode,
     * timestamp), saves the QSO, and returns a JSON `{ok, checkin}` payload.
     * GET path: delegates to `checkinsFeed()` which returns the full or delta
     * roster as JSON.
     *
     * @param int $id Net session primary key.
     * @return \Cake\Http\Response JSON response.
     */
    public function checkins(int $id): \Cake\Http\Response
    {
        if ($this->request->is('post')) {
            $session = $this->loggerSessionOrFail($id);
            if ($session->status !== 'live') {
                return $this->jsonResponse(['ok' => false, 'error' => 'Net is not live.'], 409);
            }
            $uid = $this->Authentication->getIdentity()->getIdentifier();
            $qsos = $this->fetchTable('Qsos');
            $qso = $qsos->newEntity($this->request->getData());
            $qso->set('user_id', $session->owner_id, ['guard' => false]);
            $qso->set('logged_by_user_id', $uid, ['guard' => false]);
            $qso->set('net_session_id', $session->id, ['guard' => false]);
            $qso->set('qso_type', 'net', ['guard' => false]);
            $qso->set('ncs_callsign', $this->ncsCallsignFor($session), ['guard' => false]);
            $qso->set('net_title', $session->net_title, ['guard' => false]);
            $qso->set('net_organisation', $session->net_organisation, ['guard' => false]);
            $qso->set('band', $session->band, ['guard' => false]);
            $qso->set('frequency_mhz', $session->frequency_mhz, ['guard' => false]);
            $qso->set('mode', $session->mode, ['guard' => false]);
            $qso->set('qso_datetime_utc', DateTime::now(), ['guard' => false]);
            if (!$qsos->save($qso)) {
                return $this->jsonResponse(['ok' => false, 'errors' => $qso->getErrors()], 422);
            }
            try {
                (new AuditLogger())->log(
                    event: 'net.checkin.create',
                    actorUserId: $uid,
                    target: ['type' => 'NetSessions', 'id' => $id],
                    metadata: ['qso_id' => $qso->id, 'callsign' => $qso->call_worked],
                );
            } catch (\Throwable $e) {
                error_log('audit: ' . $e->getMessage());
            }
            OperationLog::event('net.checkin.created', ['user_id' => (int)$uid, 'session_id' => (int)$id, 'qso_id' => (int)$qso->id]);
            return $this->jsonResponse(['ok' => true, 'checkin' => $this->presentCheckin($qso)]);
        }
        return $this->checkinsFeed($id);
    }

    /**
     * Edit or delete a single check-in QSO (PATCH/PUT or DELETE).
     *
     * Logger-scoped (owner + co-loggers); strangers get 404. The session must
     * still be live for either mutation to proceed (returns 409 otherwise).
     * DELETE removes the QSO row entirely; PATCH/PUT patches a whitelist of
     * editable fields.
     *
     * @param int $id    Net session primary key.
     * @param int $qsoId QSO primary key to edit or delete.
     * @return \Cake\Http\Response JSON response.
     */
    public function checkin(int $id, int $qsoId): \Cake\Http\Response
    {
        $session = $this->loggerSessionOrFail($id);
        if ($session->status !== 'live') {
            return $this->jsonResponse(['ok' => false, 'error' => 'Net is not live.'], 409);
        }
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $qsos = $this->fetchTable('Qsos');
        $qso = $qsos->find()->where(['id' => $qsoId, 'net_session_id' => $id])->first();
        if ($qso === null) {
            throw new NotFoundException('Check-in not found.');
        }
        if ($this->request->is('delete')) {
            $this->fetchTable('NetSessionRemovals')->record($id, $qsoId);
            $qsos->deleteOrFail($qso);
            try {
                (new AuditLogger())->log(
                    event: 'net.checkin.delete',
                    actorUserId: $uid,
                    target: ['type' => 'NetSessions', 'id' => $id],
                    metadata: ['qso_id' => $qsoId, 'callsign' => $qso->call_worked],
                );
            } catch (\Throwable $e) {
                error_log('audit: ' . $e->getMessage());
            }
            OperationLog::event('net.checkin.deleted', ['user_id' => (int)$uid, 'session_id' => (int)$id, 'qso_id' => (int)$qsoId]);
            return $this->jsonResponse(['ok' => true, 'removed' => $qsoId]);
        }
        $qso = $qsos->patchEntity($qso, $this->request->getData(), [
            'fields' => ['call_worked', 'operator_name', 'grid_square', 'rst_received', 'rst_sent', 'net_role', 'notes'],
        ]);
        if (!$qsos->save($qso)) {
            return $this->jsonResponse(['ok' => false, 'errors' => $qso->getErrors()], 422);
        }
        return $this->jsonResponse(['ok' => true, 'checkin' => $this->presentCheckin($qso)]);
    }

    /**
     * Resolve the NCS callsign from the net session owner's user row.
     *
     * @param \App\Model\Entity\NetSession $s The net session.
     * @return string Owner's callsign, or empty string if unset.
     */
    private function ncsCallsignFor(\App\Model\Entity\NetSession $s): string
    {
        $owner = $this->fetchTable('Users')->get($s->owner_id);
        return (string)$owner->callsign;
    }

    /**
     * Project a Qso entity onto the wire shape the cockpit and public feed consume.
     *
     * @param \App\Model\Entity\Qso $q Check-in QSO entity.
     * @return array<string, mixed>
     */
    private function presentCheckin(\App\Model\Entity\Qso $q): array
    {
        return [
            'id'                 => $q->id,
            'callsign'           => $q->call_worked,
            'name'               => $q->operator_name,
            'grid'               => $q->grid_square,
            'signal'             => \App\Service\SignalReport::strength($q->rst_received),
            'rst'                => $q->rst_received,
            'role'               => $q->net_role,
            'at'                 => $q->qso_datetime_utc?->format('c'),
            'updated'            => $q->updated_at?->format('c'),
            'logged_by_user_id'  => $q->logged_by_user_id,
        ];
    }

    /**
     * Return the full or delta check-in roster as a JSON response.
     *
     * If a `?since=` ISO-8601 cursor is provided, only rows with
     * `updated_at > cursor` are included ('+' space-decoding applied). A
     * malformed cursor falls back to returning all rows.
     *
     * @param int $id Net session primary key.
     * @return \Cake\Http\Response JSON delta feed.
     */
    private function checkinsFeed(int $id): \Cake\Http\Response
    {
        $session = $this->loggerSessionOrFail($id);
        $since = (string)$this->request->getQuery('since', '');
        $qsos = $this->fetchTable('Qsos');

        $q = $qsos->find()->where(['net_session_id' => $id]);
        $sinceDt = null;
        if ($since !== '') {
            try {
                // URL query-string parsing converts '+' → ' ' (form encoding).
                // ISO-8601 offsets never contain spaces, so restore them before
                // parsing (e.g. "2026-05-22T12:00:00 00:00" → "+00:00").
                $sinceDt = new \DateTime(str_replace(' ', '+', $since));
                $q->where(['updated_at >' => new DateTime(str_replace(' ', '+', $since))]);
            } catch (\Exception $e) {
                // Malformed cursor — treat as no cursor and return all rows.
                $sinceDt = null;
            }
        }
        $q->orderBy(['qso_datetime_utc' => 'ASC', 'id' => 'ASC']);

        $checkins = [];
        foreach ($q->all() as $row) {
            $checkins[] = $this->presentCheckin($row);
        }
        $removed = $this->fetchTable('NetSessionRemovals')->idsRemovedSince($id, $sinceDt);

        return $this->jsonResponse([
            'server_time' => DateTime::now()->format('c'),
            'status'      => $session->status,
            'stats'       => (new \App\Service\NetMetrics($qsos))->sessionStats($id),
            'checkins'    => $checkins,
            'removed'     => $removed,
        ]);
    }
}
