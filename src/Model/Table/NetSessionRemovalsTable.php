<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use DateTimeInterface;

/**
 * M7 A4 — Tombstones for deleted net check-ins. Append-only.
 *
 * The feed reads tombstones with `removed_at > $since` to tell live
 * clients which check-in ids to drop from their roster.
 */
class NetSessionRemovalsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('net_session_removals');
        $this->setPrimaryKey('id');
        $this->belongsTo('NetSessions', ['foreignKey' => 'net_session_id']);
    }

    /**
     * Record a removal. Idempotent at the application layer (callers
     * only record when a delete actually happened) — no DB-level unique
     * constraint, since the same qso_id could theoretically reappear if
     * a session were re-opened (not supported today but harmless).
     */
    public function record(int $netSessionId, int $qsoId): void
    {
        $entity = $this->newEntity([
            'net_session_id' => $netSessionId,
            'qso_id'         => $qsoId,
            'removed_at'     => \Cake\I18n\DateTime::now(),
        ]);
        $this->saveOrFail($entity);
    }

    /**
     * Ids removed from a session after a given cursor. Used by the
     * delta feed to populate `removed[]`.
     *
     * @return list<int>
     */
    public function idsRemovedSince(int $netSessionId, ?DateTimeInterface $since): array
    {
        $q = $this->find()->where(['net_session_id' => $netSessionId])
            ->select(['qso_id'])->disableHydration();
        if ($since !== null) {
            $q->where(['removed_at >' => $since]);
        }
        return array_values(array_map(static fn ($row) => (int)$row['qso_id'], $q->all()->toList()));
    }
}
