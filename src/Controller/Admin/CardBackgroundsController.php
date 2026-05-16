<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;

/**
 * Admin all-backgrounds listing at /admin/card-backgrounds.
 *
 * Edit and delete are NOT duplicated here — they deep-link to the
 * user-facing CardBackgroundsController actions (which already check
 * admin role to allow operating on any owner's background) with
 * `?return=/admin/card-backgrounds` so the redirect lands back here.
 */
class CardBackgroundsController extends AppController
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
        $table = $this->fetchTable('CardBackgrounds');
        $query = $table->find()
            ->contain(['Users', 'GuestVisits'])
            ->orderBy(['CardBackgrounds.created_at' => 'DESC']);

        $includeDeleted = (bool)$this->request->getQuery('include_deleted', false);
        if (!$includeDeleted) {
            $query->where(['CardBackgrounds.deleted_at IS' => null]);
        }

        $kind = (string)$this->request->getQuery('kind', '');
        if ($kind === 'guest') {
            $query->where(['CardBackgrounds.guest_visit_id IS NOT' => null]);
        } elseif ($kind === 'user') {
            $query->where(['CardBackgrounds.user_id IS NOT' => null]);
        }

        $rows = $this->paginate($query, ['limit' => 30]);

        // Surface which row (if any) is currently acting as the site-default
        // background so the listing can badge it. Read direct from settings —
        // no FK needed.
        $defaultBgUploadId = (int)(new \App\Service\AppSettings())->get('default_background_upload_id', 0);

        // Per-row map of templates that have this background bound. Lets the
        // listing show "Used by template X, Y" so admins can see at a glance
        // which backgrounds are load-bearing and which are orphans.
        $bgIds = [];
        foreach ($rows as $r) {
            $bgIds[] = (int)$r->id;
        }
        $usedBy = [];
        if ($bgIds !== []) {
            $tpls = $this->fetchTable('Templates')->find()
                ->select(['id', 'name', 'user_id', 'background_upload_id'])
                ->where(['Templates.background_upload_id IN' => $bgIds])
                ->all();
            foreach ($tpls as $t) {
                $usedBy[(int)$t->background_upload_id][] = $t;
            }
        }

        $this->set([
            'backgrounds'       => $rows,
            'filters'           => compact('includeDeleted', 'kind'),
            'defaultBgUploadId' => $defaultBgUploadId,
            'usedBy'            => $usedBy,
            'title'             => 'Admin · All background images',
        ]);
    }
}
