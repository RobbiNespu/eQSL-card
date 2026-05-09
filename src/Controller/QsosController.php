<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Qsos Controller (M2-T2 + M2-T3)
 *
 * Logbook surface for the authenticated user. T2 shipped the paginated `index`
 * with callsign/band/mode/date-range filters. T3 layers manual CRUD on top:
 * `view`, `add`, `edit`, `delete`.
 *
 * Authorization model: every query is scoped by `user_id = current identity`,
 * so a user can only ever see (or modify) their own QSOs. The `Qso` entity
 * additionally locks `user_id` in `_accessible`, so a hostile form payload
 * cannot reassign ownership via `patchEntity`.
 *
 * Spec note (§6.3): the `qsos` table has no `deleted_at` column, so QSO
 * deletion is hard-delete. The plan outline mentioned soft-delete; the spec
 * wins.
 */
class QsosController extends AppController
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
        // CakePHP 5 ships pagination directly on Controller; there is no
        // standalone Paginator component to load.
    }

    /**
     * Paginated logbook with optional search + filters.
     *
     * Query params:
     *  - q     : substring of `call_worked` (matched case-insensitively by
     *            uppercasing the input; entity normalizes stored values).
     *  - band  : exact match on `band` (e.g. `20m`).
     *  - mode  : exact match on `mode` (e.g. `SSB`).
     *  - from  : YYYY-MM-DD inclusive lower bound on `qso_datetime_utc`.
     *  - to    : YYYY-MM-DD inclusive upper bound on `qso_datetime_utc`.
     *
     * Sort: newest first.
     *
     * @return void
     */
    public function index(): void
    {
        $identity = $this->Authentication->getIdentity();
        $userId = $identity->getIdentifier();

        $query = $this->fetchTable('Qsos')->find()->where(['user_id' => $userId]);

        // Callsign substring search. Stored callsigns are uppercase (entity
        // mutator), so uppercasing the needle keeps the match case-insensitive
        // without forcing a function on the column (which would defeat any
        // future index on `call_worked`).
        $search = trim((string)$this->request->getQuery('q', ''));
        if ($search !== '') {
            $query->where(['call_worked LIKE' => '%' . strtoupper($search) . '%']);
        }

        $band = trim((string)$this->request->getQuery('band', ''));
        if ($band !== '') {
            $query->where(['band' => $band]);
        }

        $mode = trim((string)$this->request->getQuery('mode', ''));
        if ($mode !== '') {
            $query->where(['mode' => $mode]);
        }

        // Date-range filter. Strict YYYY-MM-DD format check guards against
        // accidental SQL surprises and keeps the filter form predictable.
        $from = (string)$this->request->getQuery('from', '');
        $to = (string)$this->request->getQuery('to', '');
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $query->where(['qso_datetime_utc >=' => $from . ' 00:00:00']);
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $query->where(['qso_datetime_utc <=' => $to . ' 23:59:59']);
        }

        $query->orderBy(['qso_datetime_utc' => 'DESC']);

        $qsos = $this->paginate($query, ['limit' => 25]);

        $this->set([
            'qsos' => $qsos,
            'filters' => compact('search', 'band', 'mode', 'from', 'to'),
            'title' => 'Logbook',
        ]);
    }

    /**
     * Single QSO detail view.
     *
     * The `id`+`user_id` predicate doubles as the authorization check: another
     * user's row simply doesn't match and `firstOrFail()` 404s. That keeps the
     * controller free of ad-hoc "is this mine?" branching.
     *
     * @param int $id QSO primary key.
     * @return void
     */
    public function view(int $id): void
    {
        $identity = $this->Authentication->getIdentity();
        $qso = $this->fetchTable('Qsos')->find()
            ->where(['id' => $id, 'user_id' => $identity->getIdentifier()])
            ->firstOrFail();
        $this->set('qso', $qso);
    }

    /**
     * Render the add form (GET) or persist a new QSO (POST).
     *
     * `user_id` is set explicitly from the authenticated identity AFTER
     * `patchEntity`, so a hostile payload that tries to ship `user_id` is
     * silently dropped twice (once by `_accessible`, once by this overwrite).
     *
     * On success the user lands on the QSO detail page so they can sanity-check
     * the row that was just created (frequencies, dates, normalization).
     *
     * Re-uses the `add.php` template for both add and edit by passing a `mode`
     * flag — the form fields are identical, only the heading and submit label
     * differ.
     *
     * @return \Cake\Http\Response|null Redirect on save, or null while
     *   rendering the form.
     */
    public function add(): ?\Cake\Http\Response
    {
        $qsos = $this->fetchTable('Qsos');
        $entity = $qsos->newEmptyEntity();

        if ($this->request->is('post')) {
            $entity = $qsos->patchEntity($entity, $this->request->getData());
            $entity->user_id = $this->Authentication->getIdentity()->getIdentifier();
            if ($qsos->save($entity)) {
                $this->Flash->success('QSO added.');

                return $this->redirect('/qsos/' . $entity->id);
            }
            $this->Flash->error('Could not save QSO. Check errors below.');
        }

        $this->set(['qso' => $entity, 'mode' => 'add']);
        $this->render('add');

        return null;
    }

    /**
     * Render the edit form (GET) or persist an edit (POST/PUT/PATCH).
     *
     * The fetch is scoped to `user_id`, so editing another user's QSO 404s
     * before any patching can happen. `user_id` is locked in `_accessible`,
     * so patchEntity ignores any attempt to overwrite it on a row we already
     * own.
     *
     * @param int $id QSO primary key.
     * @return \Cake\Http\Response|null Redirect on save, or null while
     *   rendering the form.
     */
    public function edit(int $id): ?\Cake\Http\Response
    {
        $qsos = $this->fetchTable('Qsos');
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $entity = $qsos->find()
            ->where(['id' => $id, 'user_id' => $userId])
            ->firstOrFail();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $qsos->patchEntity($entity, $this->request->getData());
            // user_id is locked in _accessible; patchEntity drops any attempt
            // to reassign it. Test `testEditCannotChangeUserId` proves this.
            if ($qsos->save($entity)) {
                $this->Flash->success('QSO updated.');

                return $this->redirect('/qsos/' . $entity->id);
            }
            $this->Flash->error('Could not save QSO.');
        }

        $this->set(['qso' => $entity, 'mode' => 'edit']);
        $this->render('add');

        return null;
    }

    /**
     * Two-stage ADIF/CSV import flow (M2-T6).
     *
     * Stage 1 (GET, or POST without file): render the upload form.
     * Stage 2 (POST with file `adif_csv`): parse the upload, run a light-weight
     *   pre-flight duplicate scan, stash the parsed records under a per-request
     *   token in the session, and render a summary view that lets the user
     *   confirm or bail out.
     * Stage 3 (POST with `confirm_token`): pull the stashed records back out
     *   of the session and batch-insert them inside a single transaction. The
     *   `qsos.qsos_dedup_idx` unique index (enforced via the table's
     *   `isUnique` rule, M2-T1) means duplicates fail to save and we count
     *   them as `skipped`. Save failures from validation also fall into
     *   `skipped` so the user sees a single count instead of an opaque crash.
     *
     * `user_id` is set explicitly from the authenticated identity AFTER
     * `newEntity`, so a hostile payload that ships `user_id` is silently
     * dropped (also locked in `_accessible`).
     *
     * @return \Cake\Http\Response|null Redirect on confirm/expiry; null while
     *   rendering upload or summary.
     */
    public function import(): ?\Cake\Http\Response
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $qsos = $this->fetchTable('Qsos');

        // Stage 3: confirm and batch-insert.
        if ($this->request->is('post') && $this->request->getData('confirm_token')) {
            $token = (string)$this->request->getData('confirm_token');
            $records = (array)$this->request->getSession()->read("import.{$token}");
            if (empty($records)) {
                $this->Flash->error('Import session expired. Please re-upload.');

                return $this->redirect('/qsos/import');
            }
            $inserted = 0;
            $skipped = 0;
            $qsos->getConnection()->transactional(function () use ($qsos, $records, $userId, &$inserted, &$skipped): void {
                foreach ($records as $rec) {
                    $entity = $qsos->newEntity($rec);
                    $entity->user_id = $userId;
                    if ($qsos->save($entity)) {
                        $inserted++;
                    } else {
                        // Duplicate (qsos_dedup_idx) or validation failure.
                        $skipped++;
                    }
                }
            });
            $this->request->getSession()->delete("import.{$token}");
            $this->Flash->success("Imported {$inserted} QSOs ({$skipped} skipped as duplicates or invalid).");

            return $this->redirect('/qsos');
        }

        // Stage 2: parse uploaded file and show summary.
        if ($this->request->is('post')) {
            $upload = $this->request->getUploadedFile('adif_csv');
            if (!$upload || $upload->getError() !== UPLOAD_ERR_OK) {
                $this->Flash->error('Please choose an ADIF (.adi) or CSV (.csv) file.');
                $this->set('stage', 'upload');

                return null;
            }
            $content = (string)$upload->getStream()->getContents();
            $name = strtolower((string)$upload->getClientFilename());

            $isAdif = str_ends_with($name, '.adi') || str_ends_with($name, '.adif');
            $parser = $isAdif ? new \App\Service\AdifParser() : new \App\Service\CsvParser();
            $result = $parser->parse($content);

            // Pre-flight duplicate count. Full conflict detection happens at
            // insert time (the unique index is the source of truth); this is
            // just to give the user a heads-up in the summary.
            $duplicateCount = 0;
            foreach ($result['records'] as $rec) {
                $exists = $qsos->find()->where([
                    'user_id' => $userId,
                    'call_worked' => strtoupper(trim((string)$rec['call_worked'])),
                    'qso_datetime_utc' => $rec['qso_datetime_utc'],
                    'band IS' => $rec['band'] ?? null,
                ])->count();
                if ($exists > 0) {
                    $duplicateCount++;
                }
            }

            $token = bin2hex(random_bytes(8));
            $this->request->getSession()->write("import.{$token}", $result['records']);

            $this->set([
                'stage' => 'summary',
                'valid' => count($result['records']),
                'invalid' => $result['invalid'],
                'duplicates' => $duplicateCount,
                'errors' => $result['errors'],
                'token' => $token,
                'sample' => array_slice($result['records'], 0, 5),
            ]);

            return null;
        }

        // Stage 1: upload form.
        $this->set('stage', 'upload');

        return null;
    }

    /**
     * Render-from-QSO flow (M2-T10).
     *
     * GET shows a template+background picker for the given QSO; POST renders a
     * card from that QSO's data, persists it with `qso_id` set and the QSO
     * snapshot stored in `qso_data_json`, and redirects to /cards/{newId}.
     *
     * Same render plumbing as `PublicController::generate` (T20): pre-decode
     * pixel-count guard against image bombs, content-hash dedup of the
     * post-optimize JPEG, single-shot CardRenderer + wrapPdf. Where this flow
     * differs is template selection — instead of forcing the system template,
     * the user picks from system/own/public-approved templates — and
     * background source — they can re-use one of their previous uploads OR
     * upload a fresh image.
     *
     * The `qso_data_json` snapshot is what the card will show forever; if the
     * user later edits or deletes the underlying QSO the rendered card stays
     * truthful to the moment it was generated.
     *
     * @param int $id QSO primary key.
     * @return \Cake\Http\Response|null Redirect on POST, null on GET form render.
     */
    public function renderCard(int $id): ?\Cake\Http\Response
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $qsos = $this->fetchTable('Qsos');
        $qso = $qsos->find()->where(['id' => $id, 'user_id' => $userId])->firstOrFail();

        // Templates the user is allowed to pick from: system templates ship
        // with the install, the user's own templates, and any public template
        // an admin has approved. `is_system DESC` floats the bundled templates
        // to the top of the picker.
        $templates = $this->fetchTable('Templates')->find()
            ->where(['OR' => [
                ['Templates.is_system' => true],
                ['Templates.user_id' => $userId],
                ['AND' => ['Templates.is_public' => true, 'Templates.is_approved' => true]],
            ]])
            ->orderBy(['Templates.is_system' => 'DESC', 'Templates.created_at' => 'DESC']);

        $existingUploads = $this->fetchTable('Uploads')->find()
            ->where(['user_id' => $userId])
            ->orderBy(['created_at' => 'DESC'])
            ->limit(20);

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $templateId = (int)($data['template_id'] ?? 0);
            $template = $this->fetchTable('Templates')->get($templateId);

            $uploadId = (int)($data['upload_id'] ?? 0);
            if ($uploadId === 0) {
                // Fresh upload path — same hardening as PublicController::generate
                // (T20): refuse pixel bombs BEFORE GD has a chance to decode.
                $tmpUpload = $this->resolveBackgroundUpload();

                $bgInfo = @getimagesize($tmpUpload);
                if ($bgInfo === false) {
                    @unlink($tmpUpload);
                    throw new \Cake\Http\Exception\BadRequestException('Background is not a valid image.');
                }
                if ($bgInfo[0] * $bgInfo[1] > 50_000_000) {
                    @unlink($tmpUpload);
                    throw new \Cake\Http\Exception\BadRequestException('Image dimensions exceed allowed limit.');
                }

                $optimizer = new \App\Service\ImageOptimizer(maxWidth: 2000, maxHeight: 1500, quality: 82);
                $tmpDest = tempnam(sys_get_temp_dir(), 'eqsl_opt_');
                $info = $optimizer->optimize($tmpUpload, $tmpDest);
                @unlink($tmpUpload);

                // Content-addressed dedup: the same picture uploaded twice
                // collapses to one row + one file on disk.
                $sha = $info['sha256_hash'];
                $uploadsDir = WWW_ROOT . 'files/uploads/';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0o775, true);
                }
                $finalPath = $uploadsDir . $sha . '.jpg';
                if (is_file($finalPath)) {
                    @unlink($tmpDest);
                } else {
                    rename($tmpDest, $finalPath);
                }

                $uploads = $this->fetchTable('Uploads');
                $upload = $uploads->find()->where(['sha256_hash' => $sha])->first();
                if (!$upload) {
                    $upload = $uploads->saveOrFail($uploads->newEntity([
                        'user_id' => $userId,
                        'original_filename' => 'qso-render.jpg',
                        'storage_path' => 'files/uploads/' . $sha . '.jpg',
                        'mime_type' => 'image/jpeg',
                        'width_px' => $info['width_px'],
                        'height_px' => $info['height_px'],
                        'file_size_bytes' => $info['file_size_bytes'],
                        'sha256_hash' => $sha,
                    ]));
                }
            } else {
                // Re-use one of the user's previous uploads. The `user_id`
                // predicate is the authorization check — picking another
                // user's upload id 404s before any disk access.
                $upload = $this->fetchTable('Uploads')->find()
                    ->where(['id' => $uploadId, 'user_id' => $userId])
                    ->firstOrFail();
                $finalPath = WWW_ROOT . $upload->storage_path;
            }

            $layout = json_decode((string)$template->layout_json, true) ?: [];
            $qsoData = $this->qsoToRenderData($qso);

            $renderer = new \App\Service\CardRenderer(WWW_ROOT . 'files/fonts/');
            $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
            $pngPath = WWW_ROOT . 'files/cards/' . $uuid . '.png';
            $pdfPath = WWW_ROOT . 'files/cards/' . $uuid . '.pdf';
            if (!is_dir(dirname($pngPath))) {
                mkdir(dirname($pngPath), 0o775, true);
            }
            $renderer->renderPng(
                ['canvas_width' => $template->canvas_width, 'canvas_height' => $template->canvas_height,
                 'fields' => $layout['fields'] ?? []],
                $finalPath, $qsoData, $pngPath
            );
            $renderer->wrapPdf($pngPath, $pdfPath, $template->canvas_width, $template->canvas_height);

            $cards = $this->fetchTable('Cards');
            $card = $cards->saveOrFail($cards->newEntity([
                'user_id' => $userId,
                'qso_id' => $qso->id,
                'template_id' => $template->id,
                'upload_id' => $upload->id,
                // Snapshot the QSO at render time. Edits/deletes to the
                // underlying QSO row must NEVER mutate a card that's already
                // been issued (cards are historical artefacts).
                'qso_data_json' => json_encode($qsoData, JSON_UNESCAPED_SLASHES),
                'png_path' => 'files/cards/' . $uuid . '.png',
                'pdf_path' => 'files/cards/' . $uuid . '.pdf',
            ]));

            $this->Flash->success('Card rendered.');

            return $this->redirect('/cards/' . $card->id);
        }

        $this->set([
            'qso' => $qso,
            'templates' => $templates,
            'existingUploads' => $existingUploads,
            'title' => 'Render eQSL for ' . $qso->call_worked,
        ]);
        // Action is named `renderCard` (because `render` collides with the
        // base controller's `render()`), but the URL segment is `/render` and
        // the template lives at `templates/Qsos/render.php` — keep the user-
        // facing surface consistent with the route by selecting the view
        // explicitly.
        $this->render('render');

        return null;
    }

    /**
     * Move the request's `background_upload` file to a tempdir scratch path.
     *
     * @return string Absolute path to the temp file caller must clean up.
     * @throws \Cake\Http\Exception\BadRequestException When no usable upload was provided.
     */
    private function resolveBackgroundUpload(): string
    {
        $upload = $this->request->getUploadedFile('background_upload');
        if ($upload && $upload->getError() === UPLOAD_ERR_OK) {
            $tmp = tempnam(sys_get_temp_dir(), 'eqsl_');
            $upload->moveTo($tmp);

            return $tmp;
        }
        throw new \Cake\Http\Exception\BadRequestException('Pick an existing background or upload a new image.');
    }

    /**
     * Project a Qso entity onto the placeholder shape consumed by CardRenderer.
     *
     * Mirrors the keys produced by `PublicController::buildQsoData` so the
     * same templates render correctly for both guest and logged-in flows.
     * `operator_callsign` is sourced from the authenticated identity (the
     * "my callsign" template field), not from the QSO row.
     *
     * @param object $qso Loaded Qso entity (typed loosely so the doc can stay
     *                    near the controller without a hard import).
     * @return array<string,string>
     */
    private function qsoToRenderData(object $qso): array
    {
        $identity = $this->Authentication->getIdentity();
        // Authentication identity wraps the underlying entity — `getOriginalData()`
        // is the canonical way to reach back to the User entity for fields the
        // identity interface itself doesn't expose.
        $userEntity = method_exists($identity, 'getOriginalData') ? $identity->getOriginalData() : $identity;

        return [
            'callsign'           => (string)$qso->call_worked,
            'operator_callsign'  => (string)($userEntity->callsign ?? ''),
            'qso_datetime_utc'   => $qso->qso_datetime_utc?->format('Y-m-d H:i:s') ?? '',
            'frequency_mhz'      => (string)($qso->frequency_mhz ?? ''),
            'band'               => (string)($qso->band ?? ''),
            'mode'               => (string)($qso->mode ?? ''),
            'rst_sent'           => (string)($qso->rst_sent ?? ''),
            'rst_received'       => (string)($qso->rst_received ?? ''),
            'operator_name'      => (string)($qso->operator_name ?? ''),
            'notes'              => (string)($qso->notes ?? ''),
        ];
    }

    /**
     * Hard-delete a QSO owned by the current user.
     *
     * POST-only (enforced by `allowMethod`) so a stray GET cannot wipe a row.
     * The `user_id` predicate handles the authorization boundary — another
     * user's QSO is invisible to this query and 404s before `delete()` runs.
     *
     * @param int $id QSO primary key.
     * @return \Cake\Http\Response Redirect to the logbook.
     */
    public function delete(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');

        $qsos = $this->fetchTable('Qsos');
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $entity = $qsos->find()
            ->where(['id' => $id, 'user_id' => $userId])
            ->firstOrFail();

        if ($qsos->delete($entity)) {
            $this->Flash->success('QSO deleted.');
        } else {
            $this->Flash->error('Could not delete QSO.');
        }

        return $this->redirect('/qsos');
    }
}
