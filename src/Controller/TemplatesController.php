<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\OperationLog;

/**
 * Templates Controller (M3-T2 scaffold).
 *
 * Surfaces the template designer (`add` / `edit`) and a placeholder `view`
 * action. Save / clone / publish land in subsequent M3 tasks (T4 / T8 / T9);
 * the gallery `index` is M3-T7. We scaffold those routes + actions now so the
 * designer view shell has somewhere stable to POST to once T4 wires save.
 *
 * Authorization model mirrors `CardsController`: every owned-template query
 * is scoped by `user_id = current identity`. `view()` additionally allows
 * `is_system` rows and `is_public AND is_approved` rows so users can preview
 * curated/shared templates before cloning (clone-and-edit lands in M3-T8).
 *
 * `add()` deliberately calls `render('edit')` so the designer view shell is
 * a single template — `mode` (`'new'` vs `'edit'`) tells the Alpine factory
 * whether to POST to `/templates/new` or `/templates/{id}/edit` (M3-T4).
 */
class TemplatesController extends AppController
{
    /**
     * Initialize hook.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    /**
     * Template gallery (M3-T7) — three tabs: Mine / Public / System.
     *
     * `mine` lists every template owned by the current user (any visibility
     * state) so they can keep editing in-progress drafts. `public` lists
     * curated public templates (`is_public AND is_approved`) authored by
     * other users — capped at 60 to keep the page light until pagination
     * lands. `system` lists the seeded system templates available to all
     * users for cloning. Sorted newest-first so freshly-saved work surfaces
     * at the top of `mine` without scrolling.
     *
     * @return void
     */
    public function index(): void
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $templates = $this->fetchTable('Templates');

        $mine = $templates->find()
            ->where(['Templates.user_id' => $userId])
            ->orderBy(['Templates.created_at' => 'DESC'])
            ->all();

        $public = $templates->find()
            ->where([
                'Templates.is_public' => true,
                'Templates.is_approved' => true,
                'Templates.user_id IS NOT' => $userId,
            ])
            ->orderBy(['Templates.created_at' => 'DESC'])
            ->limit(60)
            ->all();

        $system = $templates->find()
            ->where(['Templates.is_system' => true])
            ->orderBy(['Templates.created_at' => 'DESC'])
            ->all();

        $this->set([
            'mine' => $mine,
            'public' => $public,
            'system' => $system,
            'title' => 'Templates',
        ]);
    }

    /**
     * New-template designer + save handler (M3-T4).
     *
     * GET: builds an in-memory `Template` entity with sane defaults
     * (1500x1000 canvas, empty fields array) and renders the shared designer
     * view. POST: validates the submitted form (name, canvas dims) plus the
     * `layout_json` payload via `TemplateLayoutValidator`, persists the row
     * scoped to the current user, then redirects to `/templates/{id}/edit`.
     * Validation failures re-render the form and surface errors via Flash.
     *
     * @return \Cake\Http\Response|null
     */
    public function add()
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $templates = $this->fetchTable('Templates');
        $entity = $templates->newEmptyEntity();
        $entity->canvas_width = 1500;
        $entity->canvas_height = 1000;
        $entity->layout_json = json_encode(['fields' => []]);

        if ($this->request->is('post')) {
            $errors = $this->saveTemplate($entity, $userId, isNew: true);
            $wantsJson = $this->request->accepts('application/json')
                && !$this->request->accepts('text/html');
            if (empty($errors)) {
                OperationLog::event('template.created', ['user_id' => (int)$userId, 'template_id' => (int)$entity->id]);
                $this->Flash->success('Template created.');
                // The designer save() fetch sends Accept: application/json
                // exclusively, so it needs a JSON `redirect_url` back —
                // otherwise the auto-followed 302 lands as a 200 HTML page
                // and the JS can't distinguish success from a validation
                // re-render. Plain browser form posts still get the 302.
                if ($wantsJson) {
                    return $this->jsonRedirect('/templates/' . $entity->id . '/edit');
                }

                return $this->redirect('/templates/' . $entity->id . '/edit');
            }
            $this->Flash->error(implode("\n", $errors));
            if ($wantsJson) {
                return $this->jsonErrors($errors);
            }
        }

        $this->set([
            'template' => $entity,
            'mode' => 'new',
            'title' => 'New template',
        ]);
        // Render the shared designer view; `mode = 'new'` tells the Alpine
        // factory to POST to `/templates/new` rather than `/templates/{id}/edit`.
        $this->render('edit');

        return null;
    }

    /**
     * Edit an existing user-owned template + save handler (M3-T4).
     *
     * Scoped strictly to the current user's own rows: system templates and
     * public-approved templates are read-only via this action and must be
     * cloned first (M3-T8). Cross-user attempts surface as 404 via
     * `firstOrFail` so we don't leak row existence. POST/PUT/PATCH validates
     * + persists; GET re-renders the designer. Successful save redirects back
     * to the edit URL so refresh stays idempotent.
     *
     * @param int $id Template id (route-bound).
     * @return \Cake\Http\Response|null
     */
    public function edit(int $id)
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $templates = $this->fetchTable('Templates');
        $template = $templates->find()
            ->where(['Templates.id' => $id, 'Templates.user_id' => $userId])
            ->firstOrFail();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $errors = $this->saveTemplate($template, $userId, isNew: false);
            $wantsJson = $this->request->accepts('application/json')
                && !$this->request->accepts('text/html');
            if (empty($errors)) {
                OperationLog::event('template.saved', ['user_id' => (int)$userId, 'template_id' => (int)$template->id]);
                $this->Flash->success('Template saved.');
                if ($wantsJson) {
                    return $this->jsonRedirect('/templates/' . $template->id . '/edit');
                }

                return $this->redirect('/templates/' . $template->id . '/edit');
            }
            $this->Flash->error(implode("\n", $errors));
            if ($wantsJson) {
                return $this->jsonErrors($errors);
            }
        }

        $this->set([
            'template' => $template,
            'mode' => 'edit',
            'title' => 'Edit template — ' . $template->name,
        ]);

        return null;
    }

    /**
     * Clone-and-edit (M3-T8).
     *
     * Duplicates a template the current user is allowed to read (their own,
     * any system row, or any public-approved row authored by another user)
     * into a fresh row owned by the current user, with all visibility flags
     * (`is_public`, `is_approved`, `is_system`) reset to false. Authorization
     * mirrors `view()` exactly so what's previewable is also clonable, and
     * non-readable rows surface as 404 via `firstOrFail` rather than a 403
     * (avoids leaking row existence — same pattern as `edit()`).
     *
     * Field copy is explicit (whitelist via `newEntity`) so the entity's
     * `_accessible` mask still applies to user-controllable columns; owner
     * + flag overrides are then stamped via `set(..., ['guard' => false])`
     * because the entity definition deliberately marks those fields as
     * inaccessible to mass-assignment. `thumbnail_path` is reset to null —
     * the next save (via `edit()`) regenerates it; we don't bother copying
     * the source's thumbnail because the new row's id won't match the
     * filename anyway.
     *
     * On success redirects straight to the edit URL so the user lands in the
     * designer with their fresh copy.
     *
     * @param int $id Source template id (route-bound).
     * @return \Cake\Http\Response|null
     */
    public function clone(int $id)
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $templates = $this->fetchTable('Templates');

        // Source can be: own template, system template, or public-approved template.
        $source = $templates->find()
            ->where(['Templates.id' => $id])
            ->where(['OR' => [
                ['Templates.user_id' => $userId],
                ['Templates.is_system' => true],
                ['AND' => ['Templates.is_public' => true, 'Templates.is_approved' => true]],
            ]])
            ->firstOrFail();

        $newEntity = $templates->newEntity([
            'name' => $source->name . ' (copy)',
            'description' => $source->description,
            'canvas_width' => $source->canvas_width,
            'canvas_height' => $source->canvas_height,
            'layout_json' => $source->layout_json,
            // Carry the bound background over to the clone so the fork
            // renders identically out of the gate. The new owner can
            // detach or swap it from the designer.
            'background_upload_id' => $source->background_upload_id,
            // Carry the QSO-type categorisation too so the fork is offered
            // in the same render context as the original.
            'qso_type' => $source->qso_type ?? 'contact',
        ]);
        // Owner / flag overrides via guard:false — these columns are
        // intentionally not mass-assignable on the entity.
        $newEntity->set('user_id', $userId, ['guard' => false]);
        $newEntity->set('is_public', false, ['guard' => false]);
        $newEntity->set('is_approved', false, ['guard' => false]);
        $newEntity->set('is_system', false, ['guard' => false]);
        // thumbnail_path is regenerated on the next save; don't copy across.
        $newEntity->set('thumbnail_path', null, ['guard' => false]);

        $templates->saveOrFail($newEntity);
        OperationLog::event('template.cloned', ['user_id' => (int)$userId, 'template_id' => (int)$newEntity->id, 'source_id' => (int)$id]);

        $this->Flash->success('Template cloned. You can now edit your copy.');

        return $this->redirect('/templates/' . $newEntity->id . '/edit');
    }

    /**
     * Hard-delete one of the user's own templates.
     *
     * Scoped strictly to non-system templates owned by the current user:
     * system templates and other users' public templates are off-limits and
     * surface as 404 via `firstOrFail`.
     *
     * The `cards.template_id` FK is `RESTRICT`, so we cannot drop a template
     * that any card row still references — even soft-deleted ones, since the
     * FK doesn't honour `deleted_at`. We pre-check the reference count and
     * refuse with a clear flash message so the user knows what to do (delete
     * the dependent cards from /cards first). This keeps the deletion atomic
     * and avoids leaving the user staring at an opaque SQL 23000 error.
     *
     * @param int $id Template id (route-bound).
     * @return \Cake\Http\Response Redirect to /templates.
     */
    public function delete(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');

        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $templates = $this->fetchTable('Templates');
        $template = $templates->find()
            ->where([
                'Templates.id'        => $id,
                'Templates.user_id'   => $userId,
                'Templates.is_system' => false,
            ])
            ->firstOrFail();

        $cards = $this->fetchTable('Cards');

        // Refuse if any ACTIVE (non-soft-deleted) cards reference this
        // template — the user still has them in their library and would lose
        // the ability to re-render. They must delete those from /cards first.
        $activeCount = $cards->find()
            ->where([
                'Cards.template_id'   => $id,
                'Cards.user_id'       => $userId,
                'Cards.deleted_at IS' => null,
            ])
            ->count();
        if ($activeCount > 0) {
            $this->Flash->error(sprintf(
                'Cannot delete: %d card%s in your library still uses this template. Delete those cards from /cards first.',
                $activeCount,
                $activeCount === 1 ? '' : 's'
            ));

            return $this->redirect('/templates');
        }

        // Hard-delete any SOFT-DELETED card rows referencing this template
        // (and their on-disk image files). These rows are invisible to the
        // user already; reclaiming them here lets the RESTRICT FK release so
        // the template can drop cleanly without leaving orphaned references.
        $orphans = $cards->find()
            ->where([
                'Cards.template_id' => $id,
                'Cards.user_id'     => $userId,
            ])
            ->all();
        foreach ($orphans as $orphan) {
            if (!empty($orphan->png_path)) {
                $imagePath = WWW_ROOT . $orphan->png_path;
                if (is_file($imagePath)) {
                    @unlink($imagePath);
                }
            }
            $cards->delete($orphan);
        }

        $templates->deleteOrFail($template);
        OperationLog::event('template.deleted', ['user_id' => (int)$userId, 'template_id' => (int)$id]);
        $this->Flash->success('Template deleted.');

        return $this->redirect('/templates');
    }

    /**
     * Tell an AJAX caller "save succeeded, go here" via a JSON envelope.
     *
     * The designer's `save()` fetch sets `Accept: application/json` so it can
     * tell apart a real success from a validation-error re-render. A 302 on
     * the success path gets auto-followed by fetch into a 200 HTML page,
     * which is indistinguishable from the failure case — so we hand back a
     * `redirect_url` instead and let the JS navigate.
     *
     * @param string $url Target URL the client should navigate to.
     * @return \Cake\Http\Response|null
     */
    private function jsonRedirect(string $url): ?\Cake\Http\Response
    {
        $this->set(['redirect_url' => $url]);
        $this->viewBuilder()->setClassName('Json')->setOption('serialize', ['redirect_url']);

        return null;
    }

    /**
     * Surface validation errors to an AJAX caller as 422 JSON.
     *
     * 422 (rather than 400) so the JS can `r.status === 422` cleanly to
     * distinguish "your input is invalid, fix it and resubmit" from a
     * transport-level 4xx.
     *
     * @param string[] $errors Validation error messages collected by saveTemplate().
     * @return \Cake\Http\Response|null
     */
    private function jsonErrors(array $errors): ?\Cake\Http\Response
    {
        $this->setResponse($this->getResponse()->withStatus(422));
        $this->set(['errors' => $errors]);
        $this->viewBuilder()->setClassName('Json')->setOption('serialize', ['errors']);

        return null;
    }

    /**
     * Shared save pipeline for `add()` and `edit()`.
     *
     * Trims/normalises form input, enforces name/canvas bounds, runs the
     * layout JSON through `TemplateLayoutValidator`, then persists. On the
     * new-row path we stamp `user_id` from the authenticated identity (never
     * from form input) so a user can't forge ownership.
     *
     * @param \Cake\Datasource\EntityInterface $entity Template entity to persist.
     * @param int $userId Authenticated user id (used as ownership stamp on new rows).
     * @param bool $isNew Whether this is a fresh insert vs an update.
     * @return string[] Errors (empty on success).
     */
    private function saveTemplate(\Cake\Datasource\EntityInterface $entity, int $userId, bool $isNew): array
    {
        $data = $this->request->getData();
        $name = trim((string)($data['name'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $canvasWidth = (int)($data['canvas_width'] ?? 1500);
        $canvasHeight = (int)($data['canvas_height'] ?? 1000);
        $layoutJson = (string)($data['layout_json'] ?? '{"fields":[]}');

        if ($name === '' || mb_strlen($name) > 120) {
            return ['Name is required (max 120 chars).'];
        }
        if ($canvasWidth < 100 || $canvasWidth > 5000 || $canvasHeight < 100 || $canvasHeight > 5000) {
            return ['Canvas dimensions must be in 100..5000 px.'];
        }

        $layoutErrors = (new \App\Service\TemplateLayoutValidator())->validate($layoutJson, $canvasWidth, $canvasHeight);
        if (!empty($layoutErrors)) {
            return $layoutErrors;
        }

        // QSO type: must be 'contact' or 'net' — anything else gets coerced to
        // 'contact' (the safer default). The render-from-QSO picker filter
        // depends on this matching the qsos.qso_type enum exactly. Extract
        // once with a fallback so the true branch can't trip an
        // "Undefined array key" warning when the POST omits the field.
        $qsoTypeRaw = (string)($data['qso_type'] ?? 'contact');
        $qsoType = in_array($qsoTypeRaw, ['contact', 'net'], true) ? $qsoTypeRaw : 'contact';

        $entity->set('name', $name);
        $entity->set('description', $description);
        $entity->set('canvas_width', $canvasWidth);
        $entity->set('canvas_height', $canvasHeight);
        $entity->set('layout_json', $layoutJson);
        $entity->set('qso_type', $qsoType);
        if ($isNew) {
            $entity->set('user_id', $userId);
        }

        // Background-on-template. Empty string = explicit "no background"
        // (render flows fall back to site default). Otherwise verify the
        // upload belongs to this user — block attempts to bind someone
        // else's image by guessing an id.
        if (array_key_exists('background_upload_id', $data)) {
            $bgIdRaw = trim((string)$data['background_upload_id']);
            if ($bgIdRaw === '') {
                $entity->set('background_upload_id', null);
            } else {
                $bgId = (int)$bgIdRaw;
                $owned = $this->fetchTable('CardBackgrounds')->find()
                    ->where(['id' => $bgId, 'user_id' => $userId, 'CardBackgrounds.deleted_at IS' => null])
                    ->count();
                if ($owned > 0) {
                    $entity->set('background_upload_id', $bgId);
                } else {
                    // Unowned/missing id — keep the silent-on-which-id security
                    // property (no "id X not found" leak) but surface a generic
                    // notice so the user knows their bg request was dropped.
                    $this->Flash->error(
                        'Selected background not found or not owned. Existing background kept.'
                    );
                }
            }
        }

        $templates = $this->fetchTable('Templates');
        if (!$templates->save($entity)) {
            return ['Database save failed: ' . json_encode($entity->getErrors())];
        }

        // Render thumbnail (best-effort; failure does not fail the save)
        try {
            $renderer = \App\Service\CardRenderer::fromSettings(WWW_ROOT . 'files/fonts/');
            $thumb = new \App\Service\TemplateThumbnailRenderer(
                $renderer,
                WWW_ROOT . 'files/templates/',
                WWW_ROOT . 'files/templates/_demo-bg.jpg'
            );
            $template = [
                'canvas_width' => $entity->canvas_width,
                'canvas_height' => $entity->canvas_height,
                'fields' => json_decode((string)$entity->layout_json, true)['fields'] ?? [],
            ];
            $relPath = $thumb->render($entity->id, $template);
            $entity->set('thumbnail_path', $relPath, ['guard' => false]);
            $templates->save($entity);
        } catch (\Throwable $e) {
            // Log but don't fail the save
            error_log('[TemplateThumbnailRenderer] ' . $e->getMessage());
            OperationLog::failure('template.thumbnail_render', $e, ['template_id' => (int)$entity->id]);
        }

        // Handle "Make public" request — queues for admin moderation
        $makePublic = !empty($this->request->getData('make_public'));
        if ($makePublic && !$entity->is_public) {
            $entity->set('is_public', true, ['guard' => false]);
            $entity->set('is_approved', false, ['guard' => false]);
            $templates->save($entity);

            // Notify admins
            $this->notifyAdminsOfPendingTemplate($entity);

            // M4-T3: Audit the moderation request. This fires only on the
            // false→true transition so a no-op edit (already public) doesn't
            // spam the audit log. Audit failures must never break the save.
            try {
                (new \App\Service\AuditLogger())->log(
                    event: 'template.public_requested',
                    actorUserId: $userId,
                    target: ['type' => 'Templates', 'id' => (int)$entity->id],
                    metadata: ['name' => (string)$entity->name],
                );
            } catch (\Throwable $e) {
                error_log('audit: ' . $e->getMessage());
            }
            OperationLog::event('template.public_requested', ['user_id' => (int)$userId, 'template_id' => (int)$entity->id]);
        }

        return [];
    }

    /**
     * Notify admins via email that a user submitted a template for public review.
     *
     * Iterates every `users.role = 'admin'` row and sends a "pending review"
     * mail using the `template_pending_review` view (both html + text). Failures
     * are swallowed and logged: a flaky SMTP server must not block the user's
     * save flow. In tests the mail transport is `debug://` (T3), so
     * `Mailer::deliver()` is a no-op.
     *
     * @param \Cake\Datasource\EntityInterface $template Newly-public template.
     * @return void
     */
    private function notifyAdminsOfPendingTemplate(\Cake\Datasource\EntityInterface $template): void
    {
        $admins = $this->fetchTable('Users')->find()
            ->where(['role' => 'admin'])
            ->all();

        foreach ($admins as $admin) {
            try {
                $mailer = new \Cake\Mailer\Mailer('default');
                $mailer->setTo($admin->email)
                    ->setSubject('eQSL — template pending review: ' . $template->name)
                    ->setEmailFormat('both')
                    ->setViewVars([
                        'templateName' => $template->name,
                        'templateId' => $template->id,
                        'submitterCallsign' => $this->Authentication->getIdentity()->getOriginalData()->callsign ?? '',
                    ])
                    ->viewBuilder()->setTemplate('template_pending_review');
                $mailer->deliver();
            } catch (\Throwable $e) {
                error_log('[notify admin] ' . $e->getMessage());
                OperationLog::failure('template.admin_notify', $e, ['template_id' => (int)$template->id]);
            }
        }
    }

    /**
     * Designer preview-background uploader (M3-T5).
     *
     * Accepts a single image (`background_upload`), runs it through
     * `ImageOptimizer` (re-encode strips EXIF + caps at 2000x1500), and
     * persists a row in `uploads` owned by the current user — the same flow
     * the guest form (M1) and render-from-QSO (M2-T10) use, just with a
     * `user_id` stamp instead of `guest_visit_id`. Returns JSON with the
     * persisted upload id + a `/files/uploads/...` URL the designer JS uses
     * to set the Fabric canvas background image.
     *
     * Rationale: a designer-uploaded background lives in the user's regular
     * upload library so it can be reused at render time without re-uploading.
     * Templates themselves stay background-agnostic per spec §6.4 — the
     * designer doesn't persist `backgroundUrl` on save.
     *
     * Hardening (matches PublicController::generate / QsosController::renderCard):
     * 1. POST-only (route + allowMethod).
     * 2. `getimagesize` on the raw upload before decode → reject non-images.
     * 3. Pixel-count guard (>50M px) before optimizer touches GD — image-bomb defense.
     * 4. Dedup by post-optimize sha256 so repeat uploads don't blow up disk.
     *
     * @return \Cake\Http\Response|null
     */
    public function uploadBackground()
    {
        $this->request->allowMethod('post');
        $userId = $this->Authentication->getIdentity()->getIdentifier();

        $upload = $this->request->getUploadedFile('background_upload');
        if (!$upload || $upload->getError() !== UPLOAD_ERR_OK) {
            $this->setResponse($this->getResponse()->withStatus(400));
            $this->set(['error' => 'background_upload required']);
            $this->viewBuilder()->setClassName('Json')->setOption('serialize', ['error']);

            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'eqsl_dbg_');
        $upload->moveTo($tmp);

        // T7 hardening: image-bomb defense — reject ridiculous pixel counts BEFORE decode.
        $info = @getimagesize($tmp);
        if ($info === false) {
            @unlink($tmp);
            $this->setResponse($this->getResponse()->withStatus(400));
            $this->set(['error' => 'Not a valid image']);
            $this->viewBuilder()->setClassName('Json')->setOption('serialize', ['error']);

            return null;
        }
        if ($info[0] * $info[1] > 50_000_000) {
            @unlink($tmp);
            $this->setResponse($this->getResponse()->withStatus(400));
            $this->set(['error' => 'Image too large']);
            $this->viewBuilder()->setClassName('Json')->setOption('serialize', ['error']);

            return null;
        }

        $optimizer = new \App\Service\ImageOptimizer(maxWidth: 2000, maxHeight: 1500, quality: 82);
        $tmpDest = tempnam(sys_get_temp_dir(), 'eqsl_dbg_opt_');
        $details = $optimizer->optimize($tmp, $tmpDest);
        @unlink($tmp);

        $sha = $details['sha256_hash'];
        $uploadsDir = WWW_ROOT . 'files/uploads/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o775, true);
        }
        $finalPath = $uploadsDir . $sha . '.jpg';
        // Dedup by post-optimize hash: if the byte-identical file already
        // lives on disk we drop the scratch copy and reuse the existing row.
        if (is_file($finalPath)) {
            @unlink($tmpDest);
        } else {
            rename($tmpDest, $finalPath);
        }

        $uploads = $this->fetchTable('CardBackgrounds');
        $row = $uploads->find()->where(['sha256_hash' => $sha])->first();
        if (!$row) {
            $row = $uploads->saveOrFail($uploads->newEntity([
                'user_id' => $userId,
                'original_filename' => 'designer-bg.jpg',
                'storage_path' => 'files/uploads/' . $sha . '.jpg',
                'mime_type' => 'image/jpeg',
                'width_px' => $details['width_px'],
                'height_px' => $details['height_px'],
                'file_size_bytes' => $details['file_size_bytes'],
                'sha256_hash' => $sha,
            ]));
        }

        $this->set([
            'upload_id' => $row->id,
            'storage_path' => $row->storage_path,
            'url' => '/' . $row->storage_path,
            'width_px' => $row->width_px,
            'height_px' => $row->height_px,
        ]);
        $this->viewBuilder()->setClassName('Json')->setOption('serialize', ['upload_id', 'storage_path', 'url', 'width_px', 'height_px']);

        return null;
    }

    /**
     * Read-only template preview (M3-T2 placeholder).
     *
     * Allows the current user to view their own templates plus system rows
     * (`is_system`) and curated public rows (`is_public AND is_approved`).
     * This is the surface T7's gallery cards link into for "preview before
     * clone". A richer preview (with rendered thumbnail) ships alongside T6.
     *
     * @param int $id Template id (route-bound).
     * @return void
     */
    public function view(int $id): void
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $templates = $this->fetchTable('Templates');
        $template = $templates->find()
            ->where(['Templates.id' => $id])
            ->where(['OR' => [
                ['Templates.user_id' => $userId],
                ['Templates.is_system' => true],
                ['AND' => ['Templates.is_public' => true, 'Templates.is_approved' => true]],
            ]])
            ->firstOrFail();

        $this->set(['template' => $template, 'title' => $template->name]);
    }
}
