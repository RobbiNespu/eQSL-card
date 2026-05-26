<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Table;

/**
 * Computes a weak ETag for a session's delta feed:
 *   W/"<sessionId>-<rowCount>-<maxUpdatedAtEpoch>-<maxRemovedAtEpoch>"
 * Two clients with the same value have provably-identical roster state.
 *
 * Coarse on purpose: a single roster mutation invalidates the tag, but
 * idle 4-second polls in between return 304 with no body — the common
 * case for active NCS dashboards.
 */
final class NetFeedValidator
{
    public function __construct(
        private Table $qsos,
        private Table $removals,
    ) {}

    /**
     * Compute the weak validator for the given session id.
     *
     * @param int $sessionId Net session primary key.
     * @return string Weak ETag value, e.g. `W/"42-17-1717000000-0"`.
     */
    public function compute(int $sessionId): string
    {
        $q = $this->qsos->find()
            ->where(['net_session_id' => $sessionId])
            ->select(['n' => 'COUNT(*)', 'mx' => 'MAX(updated_at)'])
            ->disableHydration()->first() ?? ['n' => 0, 'mx' => null];
        $r = $this->removals->find()
            ->where(['net_session_id' => $sessionId])
            ->select(['mx' => 'MAX(removed_at)'])
            ->disableHydration()->first() ?? ['mx' => null];
        $epoch = static fn ($v) => $v ? (new \DateTimeImmutable((string)$v))->getTimestamp() : 0;
        return sprintf('W/"%d-%d-%d-%d"', $sessionId, (int)$q['n'], $epoch($q['mx']), $epoch($r['mx']));
    }
}
