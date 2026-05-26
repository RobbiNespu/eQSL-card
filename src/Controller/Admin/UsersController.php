<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\OperationLog;

/**
 * Admin users CRUD (M4-T6).
 *
 * Provides:
 *  - GET  /admin/users                — paginated list with `?q=` search
 *                                       (matches email / callsign / name).
 *  - GET  /admin/users/{id}/edit      — single-user edit form (role only).
 *  - POST /admin/users/{id}/edit      — apply role change with audit log.
 *  - POST /admin/users/{id}/delete    — soft-delete (sets deleted_at).
 *
 * Self-protection: the currently signed-in admin cannot demote or delete
 * their own account — both code paths short-circuit and flash an error.
 *
 * Access control mirrors the rest of `App\Controller\Admin\*`: anonymous
 * hits go through the AuthenticationMiddleware → redirect to /login,
 * authenticated non-admins get a 403 from `beforeFilter()`.
 */
class UsersController extends AdminController
{

    /**
     * Paginated, searchable list of non-deleted users.
     *
     * Supports `?q=` search across email, callsign, and name columns. Ordered
     * by most-recently-created first. Soft-deleted accounts are excluded.
     *
     * @return void
     */
    public function index(): void
    {
        $users = $this->fetchTable('Users');
        $query = $users->find()->where(['Users.deleted_at IS' => null]);

        $q = trim((string)$this->request->getQuery('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => [
                ['Users.email LIKE' => $like],
                ['Users.callsign LIKE' => strtoupper($like)],
                ['Users.name LIKE' => $like],
            ]]);
        }

        $users = $this->paginate($query->orderBy(['Users.created_at' => 'DESC']), ['limit' => 30]);
        $this->set(['users' => $users, 'q' => $q, 'title' => 'Admin · Users']);
    }

    /**
     * GET/POST /admin/users/{id}/edit — edit a user's role.
     *
     * Only the `role` field is exposed here; other profile fields belong to
     * the user. Self-demotion is blocked to prevent an admin from locking
     * themselves out.
     *
     * @param int $id Target user PK.
     * @return \Cake\Http\Response|null Redirect on successful save, null for GET/validation failure.
     */
    public function edit(int $id)
    {
        $users = $this->fetchTable('Users');
        $user = $users->find()->where(['Users.id' => $id, 'Users.deleted_at IS' => null])->firstOrFail();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $newRole = (string)$this->request->getData('role', '');
            if (!in_array($newRole, ['admin', 'user'], true)) {
                $this->Flash->error('Role must be admin or user.');
            } elseif ($newRole === 'user' && $user->id === $this->actorId) {
                $this->Flash->error('You cannot demote yourself.');
            } else {
                $oldRole = $user->role;
                $user->set('role', $newRole, ['guard' => false]);
                $users->saveOrFail($user);
                if ($oldRole !== $newRole) {
                    try {
                        (new \App\Service\AuditLogger())->log(
                            event: 'user.role_changed',
                            actorUserId: $this->actorId,
                            target: ['type' => 'Users', 'id' => $user->id],
                            metadata: ['from' => $oldRole, 'to' => $newRole],
                        );
                    } catch (\Throwable $e) {
                        error_log('audit: ' . $e->getMessage());
                    }

                    OperationLog::event('admin.user.role_changed', [
                        'actor_user_id'  => $this->actorId,
                        'target_user_id' => (int)$user->id,
                        'from'           => $oldRole,
                        'to'             => $newRole,
                    ]);
                }
                $this->Flash->success('User updated.');
                return $this->redirect('/admin/users');
            }
        }

        $this->set(['user' => $user, 'title' => 'Admin · Edit user']);
    }

    /**
     * POST /admin/users/{id}/delete — soft-delete a user account.
     *
     * Sets deleted_at to now; the account record is retained for data integrity
     * (QSOs, cards). Self-deletion is blocked.
     *
     * @param int $id Target user PK.
     * @return \Cake\Http\Response
     */
    public function delete(int $id)
    {
        $this->request->allowMethod('post');
        $users = $this->fetchTable('Users');
        $user = $users->find()->where(['Users.id' => $id, 'Users.deleted_at IS' => null])->firstOrFail();

        if ($user->id === $this->actorId) {
            $this->Flash->error('You cannot delete yourself.');
            return $this->redirect('/admin/users');
        }

        $user->set('deleted_at', \Cake\I18n\DateTime::now(), ['guard' => false]);
        $users->saveOrFail($user);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'user.deleted',
                actorUserId: $this->actorId,
                target: ['type' => 'Users', 'id' => $user->id],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        OperationLog::event('admin.user.deleted', [
            'actor_user_id'  => $this->actorId,
            'target_user_id' => (int)$user->id,
        ]);

        $this->Flash->success('User deleted.');
        return $this->redirect('/admin/users');
    }
}
