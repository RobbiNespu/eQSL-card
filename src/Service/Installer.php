<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;
use Migrations\Migrations;

/**
 * Drives the one-shot installation wizard.
 *
 * The wizard controller calls these methods in order:
 *   1. `runMigrations()` — apply all pending CakePHP migrations.
 *   2. `createAdmin()` — insert the first admin user.
 *   3. `seedDefaultTemplate()` — load the bundled system template from JSON.
 *   4. `lock()` — write a lock file so the wizard is hidden on next boot.
 */
final class Installer
{
    /**
     * Run all pending CakePHP migrations on the `default` connection.
     *
     * @return void
     */
    public function runMigrations(): void
    {
        $migrations = new Migrations(['connection' => 'default']);
        $migrations->migrate();
    }

    /**
     * Create the initial admin user from wizard form data.
     *
     * @param array{name:string,email:string,callsign:string,password:string} $data Validated user data.
     * @return object The saved User entity.
     * @throws \Cake\ORM\Exception\PersistenceFailedException If validation or save fails.
     */
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

    /**
     * Insert the bundled system template from a JSON file if none exists yet.
     *
     * Idempotent: does nothing when a row with `is_system = true` already exists.
     *
     * @param string $jsonPath Absolute path to the template JSON file.
     * @return void
     * @throws \JsonException If the file contains invalid JSON.
     */
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
                'qso_type'       => $payload['qso_type'] ?? 'contact',
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

    /**
     * Write a lock file at `$lockPath` to mark the installation as complete.
     *
     * The file contains the ISO 8601 timestamp of when it was written.
     * The installer controller checks for this file at boot and redirects
     * the wizard to the login page when it exists.
     *
     * @param string $lockPath Absolute path for the lock file.
     * @return void
     */
    public function lock(string $lockPath): void
    {
        file_put_contents($lockPath, date(DATE_ATOM) . "\n", LOCK_EX);
    }
}
