<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreateQsos extends AbstractMigration
{
    public function change(): void
    {
        $this->table('qsos')
            ->addColumn('user_id', 'integer')
            ->addColumn('call_worked', 'string', ['limit' => 20])
            ->addColumn('qso_datetime_utc', 'datetime')
            ->addColumn('frequency_mhz', 'decimal', ['precision' => 10, 'scale' => 5, 'null' => true])
            ->addColumn('band', 'string', ['limit' => 8, 'null' => true])
            ->addColumn('mode', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('rst_sent', 'string', ['limit' => 8, 'null' => true])
            ->addColumn('rst_received', 'string', ['limit' => 8, 'null' => true])
            ->addColumn('operator_name', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('operator_qth', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('grid_square', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex('user_id')
            ->addIndex('call_worked')
            ->addIndex(['user_id', 'call_worked', 'qso_datetime_utc', 'band'], ['unique' => true, 'name' => 'qsos_dedup_idx'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
