<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M5 T12 — `activations` table.
 *
 * A first-class entity that groups consecutive QSOs logged at a single
 * portable site (POTA / SOTA / IOTA / field day / kampung activation).
 * Without it, batch-exporting "all QSOs from yesterday's Bukit Larut
 * activation" requires manual SQL.
 *
 * Schema mirrors spec §13.3:
 *
 *   id           bigint PK
 *   user_id      bigint FK users(id)   — the operator who owns the activation
 *   code         varchar(60)            — e.g. "9M2/PR-001", "POTA-K-1234",
 *                                          free-text for kampung sites
 *   name         varchar(120)           — human label, e.g. "Bukit Larut SOTA"
 *   grid_square  varchar(8) nullable    — Maidenhead 4 or 6 chars; nullable
 *                                          because GPS is opt-in
 *   started_at   datetime
 *   ended_at     datetime nullable      — null = currently active
 *   notes        text nullable
 *   created_at   datetime
 *
 * Indexes:
 *   - (user_id, ended_at) for "find this user's active activation" — the
 *     hot path queried on every quick-add page load.
 *   - (user_id, started_at DESC) for the recent-activations list.
 *
 * The qsos.activation_id FK lands in a separate migration (T13). Backfill
 * is intentionally NULL for historic QSOs — no auto-grouping for the
 * past, only forward.
 *
 * No data migration here; just a new empty table.
 */
final class CreateActivationsTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('activations')
            ->addColumn('user_id', 'integer', [
                'null' => false,
                /* Signed to match users.id (int(11) signed). MySQL rejects
                   FK constraints when signedness differs between the
                   referencing and referenced columns. */
            ])
            ->addColumn('code', 'string', [
                'limit' => 60,
                'null' => false,
                'comment' => 'Activation reference: 9M2/PR-001, POTA-K-1234, or free text.',
            ])
            ->addColumn('name', 'string', [
                'limit' => 120,
                'null' => false,
                'comment' => 'Human-readable label, e.g. "Bukit Larut SOTA".',
            ])
            ->addColumn('grid_square', 'string', [
                'limit' => 8,
                'null' => true,
                'comment' => 'Maidenhead grid, 4 or 6 chars; auto-filled from GPS when granted.',
            ])
            ->addColumn('started_at', 'datetime', ['null' => false])
            ->addColumn('ended_at', 'datetime', [
                'null' => true,
                'comment' => 'NULL = currently active; the active-activation banner queries on this.',
            ])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->addIndex(['user_id', 'ended_at'], [
                'name' => 'idx_activations_user_active',
            ])
            ->addIndex(['user_id', 'started_at'], [
                'name' => 'idx_activations_user_recent',
            ])
            ->create();
    }

    public function down(): void
    {
        $this->table('activations')->drop()->save();
    }
}
