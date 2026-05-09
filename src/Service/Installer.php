<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;
use Migrations\Migrations;

final class Installer
{
    public function runMigrations(): void
    {
        $migrations = new Migrations(['connection' => 'default']);
        $migrations->migrate();
    }

    /** @return \App\Model\Entity\User */
    public function createAdmin(array $data): object
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $entity = $users->newEntity([
            'name'     => (string)$data['name'],
            'email'    => (string)$data['email'],
            'callsign' => (string)$data['callsign'],
            'role'     => 'admin',
            'password' => (string)$data['password'],
        ]);
        $users->saveOrFail($entity);

        return $entity;
    }

    public function seedDefaultTemplate(string $jsonPath): void
    {
        $templates = TableRegistry::getTableLocator()->get('Templates');
        $existing = $templates->find()->where(['is_system' => true])->first();
        if ($existing !== null) {
            return; // idempotent
        }
        $payload = json_decode((string)file_get_contents($jsonPath), true, flags: JSON_THROW_ON_ERROR);
        $entity = $templates->newEntity(
            [
                'user_id'        => null,
                'name'           => $payload['name'],
                'description'    => $payload['description'] ?? null,
                'canvas_width'   => (int)$payload['canvas_width'],
                'canvas_height'  => (int)$payload['canvas_height'],
                'layout_json'    => json_encode(['fields' => $payload['fields']], JSON_UNESCAPED_SLASHES),
                'thumbnail_path' => null,
                'is_public'      => true,
                'is_approved'    => true,
                'is_system'      => true,
            ],
            ['accessibleFields' => [
                'is_public'   => true,
                'is_approved' => true,
                'is_system'   => true,
            ]]
        );
        $templates->saveOrFail($entity);
    }

    public function lock(string $lockPath): void
    {
        file_put_contents($lockPath, date(DATE_ATOM) . "\n", LOCK_EX);
    }
}
