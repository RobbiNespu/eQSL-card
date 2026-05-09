<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreateAppSettings extends AbstractMigration
{
    public function change(): void
    {
        $this->table('app_settings', ['id' => false, 'primary_key' => 'key'])
            ->addColumn('key', 'string', ['limit' => 80])
            ->addColumn('value', 'text')
            ->addColumn('updated_at', 'datetime')
            ->create();
    }
}
