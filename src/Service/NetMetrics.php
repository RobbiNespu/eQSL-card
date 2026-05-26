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

    /**
     * @param \Cake\ORM\Table $qsos The Qsos table (injected so tests can substitute a fixture-backed instance).
     */
    public function __construct(private Table $qsos) {}

    /**
     * Compute aggregated statistics for a single net session.
     *
     * Returns checkin count, unique callsigns, "new tonight" count (callsigns
     * not seen in any previous session by the same owner), check-in rate
     * (per minute), and signal strength distribution.
     *
     * @param int $sessionId The `net_sessions.id` to analyse.
     * @return array{checkins:int, unique:int, new:int, rate:float, signal:array<int|string,int>}
     */
    public function sessionStats(int $sessionId): array
    {
        $rows = $this->qsos->find()
            ->where(['net_session_id' => $sessionId])
            ->select(['call_worked', 'rst_received'])
            ->disableHydration()->all()->toList();

        $calls = array_filter(array_column($rows, 'call_worked'));
        $checkins = count($rows);
        $unique   = count(array_unique($calls));

        // Fetch the session entity (with hydration) so date fields are proper
        // DateTimeInterface instances and we can compute "new" and "rate".
        $netSessions = TableRegistry::getTableLocator()->get('NetSessions');
        /** @var \App\Model\Entity\NetSession|null $session */
        $session = $netSessions->find()
            ->where(['id' => $sessionId])
            ->select(['id', 'owner_id', 'started_at', 'ended_at'])
            ->first();

        $new  = 0;
        $rate = 0.0;

        if ($session !== null) {
            $ownerId = (int)$session->owner_id;

            // "New tonight": distinct callsigns in THIS session that do NOT
            // appear in any OTHER session owned by the same operator.
            $siblingIds = $netSessions->find()
                ->where(['owner_id' => $ownerId, 'id !=' => $sessionId])
                ->select(['id'])->disableHydration()->all()->extract('id')->toList();

            $callsInSession = array_values(array_unique($calls));

            if (count($callsInSession) > 0 && count($siblingIds) > 0) {
                $seenElsewhere = $this->qsos->find()
                    ->where(['net_session_id IN' => $siblingIds, 'call_worked IN' => $callsInSession])
                    ->select(['call_worked'])->distinct(['call_worked'])
                    ->disableHydration()->all()->extract('call_worked')->toList();
                $seenSet = array_flip($seenElsewhere);
                $new = count(array_filter($callsInSession, fn ($c) => !isset($seenSet[$c])));
            } else {
                // No siblings → every callsign is new to this owner's nets.
                $new = count($callsInSession);
            }

            // "Rate": check-ins per minute since started_at.
            $startedAt = $session->started_at;
            if ($startedAt !== null) {
                $startTs = $startedAt instanceof \DateTimeInterface
                    ? $startedAt->getTimestamp()
                    : (new \DateTime((string)$startedAt))->getTimestamp();
                $endedAt = $session->ended_at;
                if ($endedAt !== null) {
                    $endTs = $endedAt instanceof \DateTimeInterface
                        ? $endedAt->getTimestamp()
                        : (new \DateTime((string)$endedAt))->getTimestamp();
                } else {
                    $endTs = time();
                }
                $elapsedMinutes = max(($endTs - $startTs) / 60.0, 1);
                $rate = round($checkins / $elapsedMinutes, 1);
            }
        }

        return [
            'checkins' => $checkins,
            'unique'   => $unique,
            'new'      => $new,
            'rate'     => $rate,
            'signal'   => SignalReport::distribution(array_column($rows, 'rst_received')),
        ];
    }

    /**
     * Build a list of map marker points for the Leaflet participant map.
     *
     * Only QSOs with a non-null `grid_square` that successfully decodes via
     * Maidenhead are included; malformed grids are silently skipped.
     *
     * @param int $sessionId The `net_sessions.id` to map.
     * @return list<array{callsign:string,grid:string,lat:float,lon:float,signal:?int}>
     */
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

    /**
     * Compute retention analytics across the last `$window` ended sessions for a net title.
     *
     * Returns the per-session unique check-in counts, a list of "regular" callsigns
     * (appearing in >= `REGULAR_THRESHOLD` fraction of sessions), and the session-over-
     * session retention ratio (fraction of previous session's callsigns who returned).
     *
     * @param int    $ownerId  User ID of the net owner.
     * @param string $netTitle Net title to query (exact match).
     * @param int    $window   Number of most-recent ended sessions to include (default 8).
     * @return array{sessions:list<array{id:int,unique:int}>, regulars:list<string>, retention:float|null}
     */
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

        // Longest consecutive-session attendance streak. Iterates the same
        // session order used elsewhere in this method; for each callsign,
        // increments the running streak by 1 if present this session, otherwise
        // resets to 0. The maximum-so-far is tracked alongside its holders.
        $run = [];
        $best = ['len' => 0, 'leaders' => []];
        foreach ($sessionIds as $sid) {
            $present = array_fill_keys($attendance[$sid] ?? [], true);
            foreach (array_keys($present) as $c) {
                $run[$c] = ($run[$c] ?? 0) + 1;
                if ($run[$c] > $best['len']) {
                    $best = ['len' => $run[$c], 'leaders' => [$c]];
                } elseif ($run[$c] === $best['len']) {
                    $best['leaders'][] = $c;
                }
            }
            foreach (array_keys($run) as $c) {
                if (!isset($present[$c])) {
                    $run[$c] = 0;
                }
            }
        }
        $longestStreak = $best['len'];
        $streakLeaders = array_values(array_unique($best['leaders']));

        return [
            'sessions'       => array_map(fn ($sid) => ['id' => $sid, 'unique' => count($attendance[$sid])], $sessionIds),
            'regulars'       => array_values($regulars),
            'retention'      => $retention,
            'longest_streak' => $longestStreak,
            'streak_leaders' => $streakLeaders,
        ];
    }
}
