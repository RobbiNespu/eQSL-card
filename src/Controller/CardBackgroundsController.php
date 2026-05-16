<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * User-facing card-background library at /card-backgrounds.
 *
 *  GET  /card-backgrounds             — list the current user's images (owner-scoped)
 *  GET  /card-backgrounds/{id}/edit   — edit attribution (author + license)
 *  POST /card-backgrounds/{id}/edit   — save attribution changes
 *  POST /card-backgrounds/{id}/delete — soft-delete (sets deleted_at; on-disk JPEG
 *                                       kept until the admin sweep prunes orphans)
 *
 * Renamed from `UploadsController` in commit landing migration
 * 20260516000007 — the table behind the scenes was relabelled
 * `card_backgrounds` to match the actual purpose. Old `/uploads`
 * URLs 301-redirect here for back-compat.
 *
 * Permission model: any user may operate on their OWN backgrounds.
 * Admins may operate on any background (so /admin/card-backgrounds
 * can deep-link into these actions without duplicating the form).
 */
class CardBackgroundsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    public function index(): void
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $table = $this->fetchTable('CardBackgrounds');
        $query = $table->find()
            ->where(['CardBackgrounds.user_id' => $userId, 'CardBackgrounds.deleted_at IS' => null])
            ->orderBy(['CardBackgrounds.created_at' => 'DESC']);

        $rows = $this->paginate($query, ['limit' => 24]);

        // Per-row "Used by template" map so the listing can show which
        // templates have this background bound via background_upload_id.
        $bgIds = [];
        foreach ($rows as $r) {
            $bgIds[] = (int)$r->id;
        }
        $usedBy = $bgIds === [] ? [] : $this->templateBindings($bgIds);

        $this->set([
            'backgrounds' => $rows,
            'usedBy'      => $usedBy,
            'title'       => 'Background library',
        ]);
    }

    public function edit(int $id)
    {
        $bg = $this->fetchTable('CardBackgrounds')
            ->find()
            ->where(['CardBackgrounds.id' => $id, 'CardBackgrounds.deleted_at IS' => null])
            ->firstOrFail();

        $this->assertCanModify($bg);

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            $authorName = trim((string)($data['author_name'] ?? ''));
            $authorName = $authorName !== '' ? $authorName : null;
            $licenseRaw = trim((string)($data['license'] ?? ''));
            $license = ($licenseRaw !== '' && array_key_exists($licenseRaw, \App\Service\ImageLicense::LICENSES))
                ? $licenseRaw
                : 'unknown';

            $bg->set('author_name', $authorName, ['guard' => false]);
            $bg->set('license', $license, ['guard' => false]);
            $this->fetchTable('CardBackgrounds')->saveOrFail($bg);

            try {
                (new \App\Service\AuditLogger())->log(
                    event: 'card_background.attribution_edited',
                    actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
                    target: ['type' => 'CardBackgrounds', 'id' => $bg->id],
                    metadata: ['author' => $authorName, 'license' => $license],
                );
            } catch (\Throwable $e) {
                error_log('audit: ' . $e->getMessage());
            }

            $this->Flash->success('Background attribution saved.');
            return $this->redirect($this->returnUrl());
        }

        $this->set([
            'background' => $bg,
            'title' => 'Edit background image',
        ]);
        return null;
    }

    public function delete(int $id)
    {
        $this->request->allowMethod('post');
        $bg = $this->fetchTable('CardBackgrounds')
            ->find()
            ->where(['CardBackgrounds.id' => $id, 'CardBackgrounds.deleted_at IS' => null])
            ->firstOrFail();

        $this->assertCanModify($bg);

        $bg->set('deleted_at', \Cake\I18n\DateTime::now(), ['guard' => false]);
        $this->fetchTable('CardBackgrounds')->saveOrFail($bg);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'card_background.deleted',
                actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
                target: ['type' => 'CardBackgrounds', 'id' => $bg->id],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success('Background deleted (existing cards continue to render).');
        return $this->redirect($this->returnUrl());
    }

    /** Owner can modify; admin can modify anyone's. */
    private function assertCanModify(\Cake\Datasource\EntityInterface $bg): void
    {
        $identity = $this->Authentication->getIdentity();
        $userId = $identity->getIdentifier();
        if ($bg->user_id === $userId) {
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
     * param so the admin all-backgrounds page can deep-link back to itself
     * without dragging the user to /card-backgrounds (owner-scoped).
     */
    private function returnUrl(): string
    {
        $return = (string)$this->request->getQuery('return', '');
        $allowed = ['/card-backgrounds', '/admin/card-backgrounds'];
        return in_array($return, $allowed, true) ? $return : '/card-backgrounds';
    }

    /**
     * @param int[] $bgIds
     * @return array<int, list<object>> background_id => list of Template entities
     */
    private function templateBindings(array $bgIds): array
    {
        $tpls = $this->fetchTable('Templates')->find()
            ->select(['id', 'name', 'user_id', 'background_upload_id'])
            ->where(['Templates.background_upload_id IN' => $bgIds])
            ->all();
        $out = [];
        foreach ($tpls as $t) {
            $out[(int)$t->background_upload_id][] = $t;
        }
        return $out;
    }
}
