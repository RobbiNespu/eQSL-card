<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M4-T16: profile avatar storage column.
 *
 * The M1-T4 `users` table did not anticipate per-user avatar uploads — the
 * profile page (M4-T15) only edited textual identity fields. T16 adds an
 * `avatar_path` column so `ProfileController::uploadAvatar()` can persist a
 * relative `webroot/`-rooted path (e.g. `files/avatars/123.jpg`) per user.
 *
 * The column is nullable: existing accounts have no avatar, and the upload
 * action may legitimately fail mid-flow (image-bomb guard, optimizer error)
 * without leaving the row in a bogus state. The path is stored relative to
 * `WWW_ROOT` so it can be served via the existing `webroot/` static handler
 * with no further routing changes.
 *
 * `avatar_path` is intentionally NOT mass-assignable — the controller sets it
 * with `['guard' => false]` after the optimizer succeeds, so a malicious POST
 * to `/profile` cannot overwrite the path to point at someone else's file.
 */
final class AddAvatarPathToUsers extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('avatar_path', 'string', ['limit' => 255, 'null' => true, 'after' => 'bio'])
            ->update();
    }
}
