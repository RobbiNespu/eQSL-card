<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * M6 — net analytics. Values are computed from qsos scoped by
 * net_session_id (per-session) or owner_id+net_title (cross-session).
 */
final class NetMetrics
{
    public const WINDOW = 8;
    public const REGULAR_THRESHOLD = 0.5;

    public function __construct(private Table $qsos) {}

    public function sessionStats(int $sessionId): array
    {
        $rows = $this->qsos->find()
            ->where(['net_session_id' => $sessionId])
            ->select(['call_worked', 'rst_received'])
            ->disableHydration()->all()->toList();

        $calls = array_filter(array_column($rows, 'call_worked'));
        return [
            'checkins' => count($rows),
            'unique'   => count(array_unique($calls)),
            'signal'   => SignalReport::distribution(array_column($rows, 'rst_received')),
        ];
    }

    /** @return list<array{callsign:string,grid:string,lat:float,lon:float,signal:?int}> */
    public function mapPoints(int $sessionId): array
    {
        $rows = $this->qsos->find()
            ->where(['net_session_id' => $sessionId, 'grid_square IS NOT' => null])
            ->select(['call_worked', 'grid_square', 'rst_received'])
            ->disableHydration()->all();

        $points = [];
        foreach ($rows as $r) {
            $ll = Maidenhead::toLatLon((string)$r['grid_square']);
            if ($ll === null) {
                continue;
            }
            $points[] = [
                'callsign' => (string)$r['call_worked'],
                'grid'     => (string)$r['grid_square'],
                'lat'      => $ll['lat'],
                'lon'      => $ll['lon'],
                'signal'   => SignalReport::strength($r['rst_received']),
            ];
        }
        return $points;
    }

    public function retention(int $ownerId, string $netTitle, int $window = self::WINDOW): array
    {
        $netSessions = TableRegistry::getTableLocator()->get('NetSessions');
        $sessions = $netSessions->find()
            ->where(['owner_id' => $ownerId, 'net_title' => $netTitle, 'status' => 'ended'])
            ->orderBy(['ended_at' => 'DESC'])->limit($window)
            ->select(['id'])->disableHydration()->all()->toList();
        $sessionIds = array_reverse(array_column($sessions, 'id'));

        $attendance = [];
        foreach ($sessionIds as $sid) {
            $calls = $this->qsos->find()
                ->where(['net_session_id' => $sid])
                ->select(['call_worked'])->distinct(['call_worked'])
                ->disableHydration()->all()->extract('call_worked')->toList();
            $attendance[$sid] = array_values(array_filter($calls));
        }

        $counts = [];
        foreach ($attendance as $calls) {
            foreach ($calls as $c) {
                $counts[$c] = ($counts[$c] ?? 0) + 1;
            }
        }
        $n = max(count($attendance), 1);
        $regulars = array_keys(array_filter($counts, fn ($c) => $c / $n >= self::REGULAR_THRESHOLD));

        $retention = null;
        $count = count($sessionIds);
        if ($count >= 2) {
            $prev = $attendance[$sessionIds[$count - 2]] ?? [];
            $last = $attendance[$sessionIds[$count - 1]] ?? [];
            $retention = count($prev) ? round(count(array_intersect($prev, $last)) / count($prev), 3) : null;
        }

        return [
            'sessions'  => array_map(fn ($sid) => ['id' => $sid, 'unique' => count($attendance[$sid])], $sessionIds),
            'regulars'  => array_values($regulars),
            'retention' => $retention,
        ];
    }
}
