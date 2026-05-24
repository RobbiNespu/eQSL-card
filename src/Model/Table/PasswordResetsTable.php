<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Password reset tokens ORM table.
 *
 * Each row represents a single-use reset link issued to an email address.
 * The raw token is never stored — only its SHA-256 hash (token_hash). On
 * redemption the service checks `used_at IS NULL AND expires_at > NOW()`,
 * marks used_at, then directs the user to the change-password form.
 */
class PasswordResetsTable extends Table
{
    /**
     * Configure table name, primary key, and Timestamp behavior (created_at only).
     *
     * @param array<string, mixed> $config Table config passed from the ORM locator.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('password_resets');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                ],
            ],
        ]);
    }

    /**
     * Validation: email required and valid format; token_hash required scalar,
     * max 64 chars.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->email('email')
            ->notEmptyString('email')
            ->scalar('token_hash')
            ->maxLength('token_hash', 64)
            ->notEmptyString('token_hash');

        return $validator;
    }
}
