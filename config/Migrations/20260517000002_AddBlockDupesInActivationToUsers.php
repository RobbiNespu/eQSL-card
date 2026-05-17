<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M5 T27 — `users.block_dupes_in_activation` per-user preference.
 *
 * When ON, the quick-add form disables Save while the dupe-check
 * badge is showing the red "Duplicate" state (T26). Prevents the
 * operator from committing a true duplicate during a busy net.
 *
 * Default: false (no blocking). Operators who want the safety net
 * opt in via the profile page (T27 form addition).
 *
 * Single boolean column rather than a JSON prefs blob — this is
 * currently the only operator-toggled preference. If we add more
 * (e.g. default mode, autofill rules), revisit by either adding
 * sibling columns or migrating to a `user_preferences` JSON column.
 */
final class AddBlockDupesInActivationToUsers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users')
            ->addColumn('block_dupes_in_activation', 'boolean', [
                'null' => false,
                'default' => false,
                'after' => 'bio',
                'comment' => 'M5 T27 — when true, /qsos/quick disables Save while dupe-check shows the red duplicate-in-activation state.',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('users')
            ->removeColumn('block_dupes_in_activation')
            ->update();
    }
}
