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

    public function live(string $slug): void
    {
        $session = $this->publicSessionOrFail($slug);
        $this->set(['session' => $session, 'title' => $session->net_title]);
    }

    public function feed(string $slug): \Cake\Http\Response
    {
        $session = $this->publicSessionOrFail($slug);
        $since = (string)$this->request->getQuery('since', '');
        $qsos = $this->fetchTable('Qsos');
        $q = $qsos->find()->where(['net_session_id' => $session->id]);
        if ($since !== '') {
            try {
                $q->where(['updated_at >' => new DateTime(str_replace(' ', '+', $since))]);
            } catch (\Exception $e) {
                // malformed cursor → treat as no cursor (return all)
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
        return $this->jsonResponse([
            'server_time' => DateTime::now()->format('c'),
            'status'      => $session->status,
            'stats'       => (new \App\Service\NetMetrics($qsos))->sessionStats($session->id),
            'checkins'    => $checkins,
            'removed'     => [],
        ]);
    }
}
