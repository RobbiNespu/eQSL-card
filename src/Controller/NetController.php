<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;

/**
 * M6 — public read-only live net view. No auth. Serves only public,
 * non-scheduled sessions and a whitelisted, read-only field subset
 * (no logged_by, no internal user ids). Separate controller keeps the
 * public surface small and auditable (same rationale as PublicController).
 */
class NetController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
        $this->Authentication->allowUnauthenticated(['live', 'feed']);
    }

    /**
     * Load a public, non-scheduled net session by its public slug, or throw 404.
     *
     * @param string $slug The `public_slug` value from the URL.
     * @return \App\Model\Entity\NetSession
     * @throws \Cake\Http\Exception\NotFoundException If not found or not public/active.
     */
    private function publicSessionOrFail(string $slug): \App\Model\Entity\NetSession
    {
        $row = $this->fetchTable('NetSessions')->find()
            ->where(['public_slug' => $slug, 'is_public' => true, 'status !=' => 'scheduled'])
            ->first();
        if ($row === null) {
            throw new NotFoundException('Net not found.');
        }
        return $row;
    }

    /**
     * Public read-only live net landing page.
     *
     * @param string $slug Public slug identifying the net session.
     * @return void
     */
    public function live(string $slug): void
    {
        $session = $this->publicSessionOrFail($slug);
        $this->set(['session' => $session, 'title' => $session->net_title]);
    }

    /**
     * Public delta-feed JSON endpoint for the live net view.
     *
     * Returns a JSON payload with `server_time`, `status`, `stats`, `checkins`,
     * and `removed`. Accepts an optional `?since=` ISO-8601 cursor to return
     * only rows updated after that timestamp. A malformed cursor falls back to
     * returning all rows.
     *
     * Only a whitelisted subset of fields is exposed (no `logged_by`, no
     * internal user ids) to keep the public surface read-only.
     *
     * @param string $slug Public slug identifying the net session.
     * @return \Cake\Http\Response JSON delta feed.
     */
    public function feed(string $slug): \Cake\Http\Response
    {
        $session = $this->publicSessionOrFail($slug);
        $since = (string)$this->request->getQuery('since', '');
        $qsos = $this->fetchTable('Qsos');
        $q = $qsos->find()->where(['net_session_id' => $session->id]);
        $sinceDt = null;
        if ($since !== '') {
            try {
                $sinceDt = new \DateTime(str_replace(' ', '+', $since));
                $q->where(['updated_at >' => $sinceDt]);
            } catch (\Exception $e) {
                // malformed cursor → treat as no cursor (return all)
                $sinceDt = null;
            }
        }
        $q->orderBy(['qso_datetime_utc' => 'ASC', 'id' => 'ASC']);

        $checkins = [];
        foreach ($q->all() as $row) {
            $checkins[] = [
                'id'       => $row->id,
                'callsign' => $row->call_worked,
                'name'     => $row->operator_name,
                'grid'     => $row->grid_square,
                'signal'   => \App\Service\SignalReport::strength($row->rst_received),
                'role'     => $row->net_role,
                'at'       => $row->qso_datetime_utc?->format('c'),
                'updated'  => $row->updated_at?->format('c'),
            ];
        }
        $removed = $this->fetchTable('NetSessionRemovals')->idsRemovedSince($session->id, $sinceDt);
        return $this->jsonResponse([
            'server_time' => DateTime::now()->format('c'),
            'status'      => $session->status,
            'stats'       => (new \App\Service\NetMetrics($qsos))->sessionStats($session->id),
            'checkins'    => $checkins,
            'removed'     => $removed,
        ]);
    }
}
