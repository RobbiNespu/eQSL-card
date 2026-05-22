<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M6 — link check-ins to a net session and record who entered each row
 * + the participant's role. All nullable; null for non-net QSOs.
 */
final class AddNetSessionFieldsToQsos extends AbstractMigration
{
    public function up(): void
    {
        $this->table('qsos')
            ->addColumn('net_session_id', 'integer', ['null' => true, 'after' => 'activation_id'])
            ->addColumn('logged_by_user_id', 'integer', ['null' => true, 'after' => 'net_session_id'])
            ->addColumn('net_role', 'string', ['limit' => 12, 'null' => true, 'after' => 'logged_by_user_id'])
            ->addIndex(['net_session_id'])
            ->update();
    }

    public function down(): void
    {
        $this->table('qsos')
            ->removeColumn('net_session_id')
            ->removeColumn('logged_by_user_id')
            ->removeColumn('net_role')
            ->update();
    }
}
