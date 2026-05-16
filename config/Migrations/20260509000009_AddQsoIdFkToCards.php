<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class AddQsoIdFkToCards extends AbstractMigration
{
    public function change(): void
    {
        $this->table('cards')
            ->addForeignKey('qso_id', 'qsos', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
