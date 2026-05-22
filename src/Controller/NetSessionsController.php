<?php
declare(strict_types=1);

namespace App\Controller;

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
                $this->Flash->success('Net session created.');
                return $this->redirect(['action' => 'view', $session->id]);
            }
            $this->Flash->error('Could not create the net session.');
        }
        $this->set(['session' => $session, 'title' => 'New net session']);
        return null;
    }

    public function edit(int $id): ?\Cake\Http\Response
    {
        $session = $this->ownedOrFail($id);
        if ($this->request->is(['post', 'put'])) {
            $session = $this->fetchTable('NetSessions')->patchEntity($session, $this->request->getData());
            if ($this->fetchTable('NetSessions')->save($session)) {
                $this->Flash->success('Net session updated.');
                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error('Could not update the net session.');
        }
        $this->set(['session' => $session, 'title' => 'Edit net session']);
        return null;
    }

    public function start(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $session->set('status', 'live', ['guard' => false]);
        $session->set('started_at', DateTime::now(), ['guard' => false]);
        $this->fetchTable('NetSessions')->saveOrFail($session);
        $this->Flash->success('Net is live.');
        return $this->redirect(['action' => 'cockpit', $id]);
    }

    public function end(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $session->set('status', 'ended', ['guard' => false]);
        $session->set('ended_at', DateTime::now(), ['guard' => false]);
        $this->fetchTable('NetSessions')->saveOrFail($session);
        $this->Flash->success('Net ended.');
        return $this->redirect(['action' => 'view', $id]);
    }

    public function delete(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $this->fetchTable('NetSessions')->deleteOrFail($session);
        $this->Flash->success('Net session deleted.');
        return $this->redirect(['action' => 'index']);
    }

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

    private function uniqueSlug(): string
    {
        $tbl = $this->fetchTable('NetSessions');
        do {
            $slug = strtolower(Security::randomString(16));
        } while ($tbl->exists(['public_slug' => $slug]));
        return $slug;
    }

    private function loggerSessionOrFail(int $id): \App\Model\Entity\NetSession
    {
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('NetSessions');
        if (!$tbl->isLogger($id, $uid)) {
            throw new NotFoundException('Net session not found.');
        }
        return $tbl->get($id);
    }

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

    public function checkins(int $id): \Cake\Http\Response
    {
        if ($this->request->is('post')) {
            $session = $this->loggerSessionOrFail($id);
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
            return $this->jsonResponse(['ok' => true, 'checkin' => $this->presentCheckin($qso)]);
        }
        return $this->checkinsFeed($id);
    }

    public function checkin(int $id, int $qsoId): \Cake\Http\Response
    {
        $this->loggerSessionOrFail($id);
        $qsos = $this->fetchTable('Qsos');
        $qso = $qsos->find()->where(['id' => $qsoId, 'net_session_id' => $id])->first();
        if ($qso === null) {
            throw new NotFoundException('Check-in not found.');
        }
        if ($this->request->is('delete')) {
            $qsos->deleteOrFail($qso);
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

    private function ncsCallsignFor(\App\Model\Entity\NetSession $s): string
    {
        $owner = $this->fetchTable('Users')->get($s->owner_id);
        return (string)$owner->callsign;
    }

    private function presentCheckin(\App\Model\Entity\Qso $q, bool $includePrivate = true): array
    {
        $row = [
            'id'        => $q->id,
            'callsign'  => $q->call_worked,
            'name'      => $q->operator_name,
            'grid'      => $q->grid_square,
            'signal'    => \App\Service\SignalReport::strength($q->rst_received),
            'rst'       => $q->rst_received,
            'role'      => $q->net_role,
            'at'        => $q->qso_datetime_utc?->format('c'),
            'updated'   => $q->updated_at?->format('c'),
        ];
        if ($includePrivate) {
            $row['logged_by_user_id'] = $q->logged_by_user_id;
        }
        return $row;
    }

    private function checkinsFeed(int $id): \Cake\Http\Response
    {
        $session = $this->loggerSessionOrFail($id);
        $since = (string)$this->request->getQuery('since', '');
        $qsos = $this->fetchTable('Qsos');

        $q = $qsos->find()->where(['net_session_id' => $id]);
        if ($since !== '') {
            try {
                // URL query-string parsing converts '+' → ' ' (form encoding).
                // ISO-8601 offsets never contain spaces, so restore them before
                // parsing (e.g. "2026-05-22T12:00:00 00:00" → "+00:00").
                $cursor = new DateTime(str_replace(' ', '+', $since));
                $q->where(['updated_at >' => $cursor]);
            } catch (\Exception $e) {
                // Malformed cursor — treat as no cursor and return all rows.
            }
        }
        $q->orderBy(['qso_datetime_utc' => 'ASC', 'id' => 'ASC']);

        $checkins = [];
        foreach ($q->all() as $row) {
            $checkins[] = $this->presentCheckin($row, true);
        }

        return $this->jsonResponse([
            'server_time' => DateTime::now()->format('c'),
            'status'      => $session->status,
            'stats'       => (new \App\Service\NetMetrics($qsos))->sessionStats($id),
            'checkins'    => $checkins,
            // Hard-deletes are reflected by absence on a full refresh (no cursor).
            // Soft-delete tracking (tombstone list) is deferred to future work.
            'removed'     => [],
        ]);
    }
}
