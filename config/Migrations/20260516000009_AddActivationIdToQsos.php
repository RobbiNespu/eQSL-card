<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M5 T13 — `qsos.activation_id` nullable FK.
 *
 * Per spec §13.3: NULL backfill is intentional for historic QSOs. The
 * activations entity only applies forward — no retroactive grouping.
 * When the operator starts an activation, new QSOs auto-tag with that
 * activation_id at save time (controller-side, T16).
 *
 * Index on (user_id, activation_id) so the ADIF export view (T17)
 * `WHERE user_id = ? AND activation_id = ?` is cheap. The composite
 * order matters: user_id is always known when scoping a query, while
 * activation_id may be NULL for the bulk of historic rows.
 *
 * ON DELETE SET NULL: if the operator deletes a logged activation,
 * we keep the QSOs (they're still real contacts) and just clear the
 * grouping pointer. Hard delete via CASCADE would be a footgun.
 */
final class AddActivationIdToQsos extends AbstractMigration
{
    public function up(): void
    {
        $this->table('qsos')
            ->addColumn('activation_id', 'integer', [
                'null' => true,
                /* Signed to match activations.id (which is signed to match
                   users.id). FK constraints in MySQL require matching
                   signedness between referencing and referenced columns. */
                'after' => 'user_id',
                'comment' => 'Optional FK to activations. NULL for historic QSOs and contacts logged outside any active activation.',
            ])
            ->addForeignKey('activation_id', 'activations', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
            ])
            ->addIndex(['user_id', 'activation_id'], [
                'name' => 'idx_qsos_user_activation',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('qsos')
            ->dropForeignKey('activation_id')
            ->removeIndexByName('idx_qsos_user_activation')
            ->removeColumn('activation_id')
            ->update();
    }
}
