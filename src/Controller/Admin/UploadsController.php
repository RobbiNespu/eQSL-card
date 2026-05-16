<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;

/**
 * Admin all-uploads listing at /admin/uploads.
 *
 * Edit and delete are NOT duplicated here — they deep-link to the user-
 * facing UploadsController actions (which already check admin role to
 * allow operating on any owner's upload) with `?return=/admin/uploads`
 * so the redirect lands back on the admin list.
 */
class UploadsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            return;
        }
        $user = $this->fetchTable('Users')->get($identity->getIdentifier());
        if ($user->role !== 'admin') {
            throw new \Cake\Http\Exception\ForbiddenException('Admin only.');
        }
    }

    public function index(): void
    {
        $uploads = $this->fetchTable('Uploads');
        $query = $uploads->find()
            ->contain(['Users', 'GuestVisits'])
            ->orderBy(['Uploads.created_at' => 'DESC']);

        $includeDeleted = (bool)$this->request->getQuery('include_deleted', false);
        if (!$includeDeleted) {
            $query->where(['Uploads.deleted_at IS' => null]);
        }

        $kind = (string)$this->request->getQuery('kind', '');
        if ($kind === 'guest') {
            $query->where(['Uploads.guest_visit_id IS NOT' => null]);
        } elseif ($kind === 'user') {
            $query->where(['Uploads.user_id IS NOT' => null]);
        }

        $rows = $this->paginate($query, ['limit' => 30]);

        // Surface which row (if any) is currently acting as the site-default
        // background so the listing can badge it. Read direct from settings —
        // no FK needed.
        $defaultBgUploadId = (int)(new \App\Service\AppSettings())->get('default_background_upload_id', 0);

        $this->set([
            'uploads'           => $rows,
            'filters'           => compact('includeDeleted', 'kind'),
            'defaultBgUploadId' => $defaultBgUploadId,
            'title'             => 'Admin · All uploads',
        ]);
    }
}
