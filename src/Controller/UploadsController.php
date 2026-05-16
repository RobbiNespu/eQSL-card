<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * User-facing upload library at /uploads.
 *
 *  GET  /uploads             — list the current user's uploads (owner-scoped)
 *  GET  /uploads/{id}/edit   — edit attribution (author + license)
 *  POST /uploads/{id}/edit   — save attribution changes
 *  POST /uploads/{id}/delete — soft-delete (sets deleted_at; on-disk JPEG
 *                              kept until the M4-T9 admin sweep prunes
 *                              orphans)
 *
 * Permission model: any user may operate on their OWN uploads. Admins may
 * operate on any upload (so /admin/uploads list page can deep-link into
 * these actions without duplicating the form).
 */
class UploadsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    public function index(): void
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $uploads = $this->fetchTable('Uploads');
        $query = $uploads->find()
            ->where(['Uploads.user_id' => $userId, 'Uploads.deleted_at IS' => null])
            ->orderBy(['Uploads.created_at' => 'DESC']);

        $rows = $this->paginate($query, ['limit' => 24]);

        $this->set([
            'uploads' => $rows,
            'title' => 'Background library',
        ]);
    }

    public function edit(int $id)
    {
        $upload = $this->fetchTable('Uploads')
            ->find()
            ->where(['Uploads.id' => $id, 'Uploads.deleted_at IS' => null])
            ->firstOrFail();

        $this->assertCanModify($upload);

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            $authorName = trim((string)($data['author_name'] ?? ''));
            $authorName = $authorName !== '' ? $authorName : null;
            $licenseRaw = trim((string)($data['license'] ?? ''));
            $license = ($licenseRaw !== '' && array_key_exists($licenseRaw, \App\Service\ImageLicense::LICENSES))
                ? $licenseRaw
                : 'unknown';

            $upload->set('author_name', $authorName, ['guard' => false]);
            $upload->set('license', $license, ['guard' => false]);
            $this->fetchTable('Uploads')->saveOrFail($upload);

            try {
                (new \App\Service\AuditLogger())->log(
                    event: 'upload.attribution_edited',
                    actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
                    target: ['type' => 'Uploads', 'id' => $upload->id],
                    metadata: ['author' => $authorName, 'license' => $license],
                );
            } catch (\Throwable $e) {
                error_log('audit: ' . $e->getMessage());
            }

            $this->Flash->success('Upload attribution saved.');
            return $this->redirect($this->returnUrl());
        }

        $this->set([
            'upload' => $upload,
            'title' => 'Edit upload',
        ]);
        return null;
    }

    public function delete(int $id)
    {
        $this->request->allowMethod('post');
        $upload = $this->fetchTable('Uploads')
            ->find()
            ->where(['Uploads.id' => $id, 'Uploads.deleted_at IS' => null])
            ->firstOrFail();

        $this->assertCanModify($upload);

        $upload->set('deleted_at', \Cake\I18n\DateTime::now(), ['guard' => false]);
        $this->fetchTable('Uploads')->saveOrFail($upload);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'upload.deleted',
                actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
                target: ['type' => 'Uploads', 'id' => $upload->id],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success('Upload deleted (existing cards continue to render).');
        return $this->redirect($this->returnUrl());
    }

    /** Owner can modify; admin can modify anyone's. */
    private function assertCanModify(\Cake\Datasource\EntityInterface $upload): void
    {
        $identity = $this->Authentication->getIdentity();
        $userId = $identity->getIdentifier();
        if ($upload->user_id === $userId) {
            return;
        }
        $user = $this->fetchTable('Users')->get($userId);
        if ($user->role === 'admin') {
            return;
        }
        throw new \Cake\Http\Exception\NotFoundException();
    }

    /**
     * Where to redirect after edit/delete. Honours an opt-in `return` query
     * param so the admin all-uploads page can deep-link back to itself
     * without dragging the user to /uploads (which is owner-scoped).
     */
    private function returnUrl(): string
    {
        $return = (string)$this->request->getQuery('return', '');
        $allowed = ['/uploads', '/admin/uploads'];
        return in_array($return, $allowed, true) ? $return : '/uploads';
    }
}
