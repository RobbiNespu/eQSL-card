<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M4-T12: extend `password_resets` with a `kind` discriminator so the same
 * table can back both password-reset tokens (the original use, default
 * `password_reset`) and email-verification tokens (`email_verify`).
 *
 * Rationale: the row shape is identical (email + token_hash + expires_at +
 * used_at), so a separate table would just duplicate schema and the
 * single-shot consume logic. The discriminator + composite (kind, email)
 * index keeps lookups cheap.
 */
final class AddKindToPasswordResets extends AbstractMigration
{
    public function change(): void
    {
        $this->table('password_resets')
            ->addColumn('kind', 'string', [
                'limit' => 20,
                'default' => 'password_reset',
                'after' => 'email',
            ])
            ->addIndex(['kind', 'email'])
            ->update();
    }
}
