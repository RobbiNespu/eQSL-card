<?php
declare(strict_types=1);

namespace App\Controller;

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
     * Stub for M3-T7 (gallery: My / Public / System tabs).
     *
     * Today this just renders a placeholder; M3-T7 wires the three-tab grid
     * with thumbnail rendering. Keeping the action present (and the route
     * already connected) means the gallery task is purely view + query work.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('title', 'Templates');
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
            if (empty($errors)) {
                $this->Flash->success('Template created.');

                return $this->redirect('/templates/' . $entity->id . '/edit');
            }
            $this->Flash->error(implode("\n", $errors));
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
            if (empty($errors)) {
                $this->Flash->success('Template saved.');

                return $this->redirect('/templates/' . $template->id . '/edit');
            }
            $this->Flash->error(implode("\n", $errors));
        }

        $this->set([
            'template' => $template,
            'mode' => 'edit',
            'title' => 'Edit template — ' . $template->name,
        ]);

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

        $entity->set('name', $name);
        $entity->set('description', $description);
        $entity->set('canvas_width', $canvasWidth);
        $entity->set('canvas_height', $canvasHeight);
        $entity->set('layout_json', $layoutJson);
        if ($isNew) {
            $entity->set('user_id', $userId);
        }

        $templates = $this->fetchTable('Templates');
        if (!$templates->save($entity)) {
            return ['Database save failed: ' . json_encode($entity->getErrors())];
        }

        return [];
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

        $uploads = $this->fetchTable('Uploads');
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
