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

        // QSO type filter: 'contact' or 'net'. Empty (default) means both.
        // Any other value is ignored — the form only ships these two so a
        // garbage value via URL tinkering still produces a useful listing.
        $qsoType = trim((string)$this->request->getQuery('qso_type', ''));
        if (in_array($qsoType, \App\Model\Table\QsosTable::QSO_TYPES, true)) {
            $query->where(['qso_type' => $qsoType]);
        }

        // Transport filter. 'rf' or any internet code from Transport service.
        // 'internet' is a synthetic value that means "anything not rf".
        $transport = trim((string)$this->request->getQuery('transport', ''));
        if ($transport === 'internet') {
            $query->where(['transport !=' => 'rf']);
        } elseif (array_key_exists($transport, \App\Service\Transport::TRANSPORTS)) {
            $query->where(['transport' => $transport]);
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

        // Map qso_id => most-recent non-deleted card id for this user. The
        // template uses this to render a "View card" link instead of the
        // "Render" button when a card already exists, mirroring the
        // single-render guard in `renderCard()`.
        $activeCardByQso = [];
        $qsoIds = [];
        foreach ($qsos as $row) {
            $qsoIds[] = $row->id;
        }
        if ($qsoIds !== []) {
            $cardRows = $this->fetchTable('Cards')->find()
                ->select(['id', 'qso_id'])
                ->where([
                    'Cards.qso_id IN' => $qsoIds,
                    'Cards.user_id' => $userId,
                    'Cards.deleted_at IS' => null,
                ])
                ->orderBy(['Cards.created_at' => 'DESC'])
                ->all();
            foreach ($cardRows as $c) {
                // First write wins => most recent due to ORDER BY DESC.
                $activeCardByQso[(int)$c->qso_id] ??= (int)$c->id;
            }
        }

        // Templates the bulk-render modal can choose from: own + system + public-approved.
        $availableTemplates = $this->fetchTable('Templates')->find()
            ->where(['Templates.deleted_at IS' => null])
            ->where(['OR' => [
                ['Templates.user_id' => $userId],
                ['Templates.is_system' => true],
                ['AND' => ['Templates.is_public' => true, 'Templates.is_approved' => true]],
            ]])
            ->orderBy(['Templates.is_system' => 'DESC', 'Templates.created_at' => 'DESC'])
            ->all();

        // User's existing uploads (capped) for the bulk-render background picker.
        // Soft-deleted uploads (deleted_at non-null) must drop out — they're
        // hidden from /uploads and the on-disk file is already gone.
        $userUploads = $this->fetchTable('CardBackgrounds')->find()
            ->where(['user_id' => $userId, 'CardBackgrounds.deleted_at IS' => null])
            ->orderBy(['CardBackgrounds.created_at' => 'DESC'])
            ->limit(20)
            ->all();

        $this->set([
            'qsos' => $qsos,
            'filters' => compact('search', 'band', 'mode', 'from', 'to', 'qsoType', 'transport'),
            'title' => 'Logbook',
            'availableTemplates' => $availableTemplates,
            'userUploads' => $userUploads,
            'activeCardByQso' => $activeCardByQso,
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
    /**
     * M5 T7 — Quick-add: portable-first one-thumb QSO entry.
     *
     * Renders a stripped-down form (callsign, freq, mode, RST sent/recv,
     * notes). On POST:
     *   - date/time UTC defaults to now (operator can override server-side
     *     via a hidden field if they need to backfill — UI doesn't expose
     *     it on mobile for speed),
     *   - band auto-derives from frequency via HamRadio::bandForFrequency,
     *   - transport defaults to 'rf' (RF over the air — the activation
     *     baseline),
     *   - qso_type defaults to 'contact'.
     *
     * After save, the controller re-renders the same form (no redirect)
     * with a success flash and an empty entity, leaving the callsign
     * input focused for the next contact. T7 ships the route + form +
     * basics; T8 adds the pinned "Last 5 QSOs" panel, T9 wires the
     * save-and-refocus loop on the client, T10 adds notes chips, T11
     * makes the submit button sticky-above-keyboard.
     */
    public function quick(): ?\Cake\Http\Response
    {
        $qsos = $this->fetchTable('Qsos');
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $entity = $qsos->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            // Auto-fill date/time UTC if the client didn't send one (mobile
            // form omits the field by default). Server is the timekeeper.
            if (empty($data['qso_datetime_utc'])) {
                $data['qso_datetime_utc'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                    ->format('Y-m-d H:i:s');
            }
            // Auto-derive band from frequency if the client didn't send one.
            if (empty($data['band']) && !empty($data['frequency_mhz'])) {
                $derived = \App\Service\HamRadio::bandForFrequency($data['frequency_mhz']);
                if ($derived !== null) {
                    $data['band'] = $derived;
                }
            }
            // Default transport to RF and qso_type to contact — both are the
            // overwhelming portable-ops baseline. Operators who need
            // internet-mediated or net mode use /qsos/new (more fields).
            if (empty($data['transport'])) {
                $data['transport'] = 'rf';
            }
            if (empty($data['qso_type'])) {
                $data['qso_type'] = 'contact';
            }

            $entity = $qsos->patchEntity($entity, $data);
            $entity->user_id = $userId;
            // M5 T16 — auto-tag with the operator's active activation (if
            // any). Server-side ONLY; activation_id is locked from mass
            // assignment so a request can't spoof which activation tags
            // the row. findActiveForUser is already scoped to $userId, so
            // we can't accidentally tag with another user's activation.
            $activeForTag = $this->fetchTable('Activations')->findActiveForUser($userId);
            if ($activeForTag !== null) {
                $entity->set('activation_id', (int)$activeForTag->id, ['guard' => false]);
            }
            $saved = $qsos->save($entity);
            // T9 — content negotiation. If the client asked for JSON
            // (Alpine fetch submit), return a small payload it can use to
            // prepend to the recents panel and clear the form, without
            // reloading the page.
            if ($this->request->accepts('application/json')) {
                $this->response = $this->response->withType('application/json');
                if ($saved) {
                    $payload = [
                        'ok'  => true,
                        'qso' => [
                            'id'        => (int)$entity->id,
                            'callsign'  => (string)$entity->call_worked,
                            'frequency' => (string)($entity->frequency_mhz ?? ''),
                            'band'      => (string)($entity->band ?? ''),
                            'mode'      => (string)($entity->mode ?? ''),
                            'notes'     => (string)($entity->notes ?? ''),
                            'time'      => $entity->qso_datetime_utc instanceof \DateTimeInterface
                                ? $entity->qso_datetime_utc->format('H:i') : '',
                        ],
                    ];
                } else {
                    $this->response = $this->response->withStatus(422);
                    $payload = [
                        'ok'     => false,
                        'errors' => $entity->getErrors(),
                    ];
                }
                $this->response = $this->response->withStringBody(json_encode($payload, JSON_THROW_ON_ERROR));
                return $this->response;
            }
            if ($saved) {
                $this->Flash->success('Logged ' . $entity->call_worked . '.');
                // Re-render the empty form (HTML fallback for no-JS clients).
                $entity = $qsos->newEmptyEntity();
            } else {
                $this->Flash->error('Could not save QSO. Check fields and try again.');
            }
        }

        // Last 5 QSOs for the pinned context panel (T8). Lightweight: just
        // the fields the panel renders, no `contain()` fan-out.
        $recent = $qsos->find()
            ->select(['id', 'call_worked', 'frequency_mhz', 'band', 'mode',
                      'rst_sent', 'rst_received', 'qso_datetime_utc', 'notes'])
            ->where(['user_id' => $userId])
            ->orderBy(['qso_datetime_utc' => 'DESC', 'id' => 'DESC'])
            ->limit(5)
            ->all();

        // M5 T14 — active activation banner. Cheap lookup (indexed query
        // on user_id, ended_at). Renders a small banner at the top of
        // the quick-add page so the operator sees what they're logging
        // into. T16 will use this same value to auto-tag new QSOs at
        // save time.
        $active = $this->fetchTable('Activations')->findActiveForUser($userId);

        $this->set([
            'qso' => $entity,
            'recent' => $recent,
            'activeActivation' => $active,
            'title' => 'Quick add — log a contact',
        ]);
        $this->render('quick');

        return null;
    }

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

        // operatorCallsign drives the auto-prefill of the NCS field when the
        // user toggles into "Net check-in" mode on the add form — most
        // operators are running the nets they're logging, so 'me' is the
        // 95% default. They can still edit if they're scribing someone
        // else's net.
        $this->set([
            'qso' => $entity,
            'mode' => 'add',
            'operatorCallsign' => (string)($this->Authentication->getIdentity()->getOriginalData()->callsign ?? ''),
        ]);
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
                // If a card was rendered from this QSO, its embedded QSO data
                // is now stale — remove it so the user can render a fresh one.
                $cards = $this->fetchTable('Cards');
                $staleCard = $cards->find()
                    ->where([
                        'Cards.qso_id'      => $entity->id,
                        'Cards.user_id'     => $userId,
                        'Cards.deleted_at IS' => null,
                    ])
                    ->first();

                if ($staleCard !== null) {
                    // Delete the physical image file immediately so disk is
                    // reclaimed without waiting for the M4 admin sweep.
                    $imagePath = WWW_ROOT . $staleCard->png_path;
                    if (is_file($imagePath)) {
                        @unlink($imagePath);
                    }
                    $staleCard->deleted_at = \Cake\I18n\DateTime::now();
                    $cards->save($staleCard);

                    $this->Flash->success('QSO updated. The previous card has been removed — choose a template to render a new one.');

                    return $this->redirect('/qsos/' . $entity->id . '/render');
                }

                $this->Flash->success('QSO updated.');

                return $this->redirect('/qsos/' . $entity->id);
            }
            $this->Flash->error('Could not save QSO.');
        }

        $this->set([
            'qso' => $entity,
            'mode' => 'edit',
            'operatorCallsign' => (string)($this->Authentication->getIdentity()->getOriginalData()->callsign ?? ''),
        ]);
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

        // If this QSO already has a non-deleted card, refuse to render another
        // and bounce the user to the existing one. Repeated clicks of the
        // "Render" button used to silently produce duplicate cards (and burn
        // GD time + disk on identical PNG/PDFs); now the user must explicitly
        // delete the existing card from /cards before they can render again.
        $existingCard = $this->fetchTable('Cards')->find()
            ->where([
                'Cards.qso_id' => $id,
                'Cards.user_id' => $userId,
                'Cards.deleted_at IS' => null,
            ])
            ->orderBy(['Cards.created_at' => 'DESC'])
            ->first();
        if ($existingCard !== null) {
            $this->Flash->info(
                'This QSO already has a rendered card. Delete it first if you want to render a new one.'
            );
            return $this->redirect('/cards/' . $existingCard->id);
        }

        // Templates the user is allowed to pick from: system templates ship
        // with the install, the user's own templates, and any public template
        // an admin has approved. Plus a qso_type filter so a net check-in
        // QSO only sees net templates and vice versa — prevents the
        // wrong-shape render. `is_system DESC` floats the bundled templates
        // to the top of the picker.
        $qsoType = (string)($qso->qso_type ?? 'contact');
        $templates = $this->fetchTable('Templates')->find()
            ->where(['Templates.qso_type' => $qsoType])
            ->where(['OR' => [
                ['Templates.is_system' => true],
                ['Templates.user_id' => $userId],
                ['AND' => ['Templates.is_public' => true, 'Templates.is_approved' => true]],
            ]])
            ->orderBy(['Templates.is_system' => 'DESC', 'Templates.created_at' => 'DESC']);

        // Skip soft-deleted uploads — they're hidden from /uploads and the
        // on-disk JPEG is already gone, so offering them in the picker would
        // surface a 404 on the next step.
        $existingUploads = $this->fetchTable('CardBackgrounds')->find()
            ->where(['user_id' => $userId, 'CardBackgrounds.deleted_at IS' => null])
            ->orderBy(['created_at' => 'DESC'])
            ->limit(20);

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $templateId = (int)($data['template_id'] ?? 0);
            // Authorization: re-apply the same predicate the picker uses
            // (system | owned | public-approved) so a hand-crafted POST
            // can't load another user's private template by guessing the
            // id. firstOrFail() throws RecordNotFoundException → 404,
            // matching the convention for tampered-id form submissions.
            $template = $this->fetchTable('Templates')->find()
                ->where(['Templates.id' => $templateId])
                ->where(['OR' => [
                    ['Templates.is_system' => true],
                    ['Templates.user_id' => $userId],
                    ['AND' => ['Templates.is_public' => true, 'Templates.is_approved' => true]],
                ]])
                ->firstOrFail();

            // Type match: refuse to render a net template against a contact
            // QSO (and vice versa). The picker UI already filters by this
            // up the form, but a crafted POST could still slip through;
            // this is the server-side guarantee.
            if (($template->qso_type ?? 'contact') !== ($qso->qso_type ?? 'contact')) {
                $this->Flash->error(sprintf(
                    'This template is for %s QSOs, but this QSO is a %s. Pick a matching template.',
                    $template->qso_type ?? 'contact',
                    $qso->qso_type ?? 'contact'
                ));

                return $this->redirect('/qsos/' . $id . '/render');
            }

            // Background source: the template owns it now. If the template
            // has no bound background, fall through to the site default and
            // ensure (via SHA-dedup) an uploads row exists for that image so
            // the card row's FK to `uploads` is always satisfied.
            [$upload, $authorName, $license] = $this->resolveTemplateBackground($template, $userId);

            $cardId = $this->renderQsoCard(
                $userId, (int)$qso->id, (int)$template->id, (int)$upload->id,
                $authorName, $license
            );

            $this->Flash->success('Card rendered.');

            return $this->redirect('/cards/' . $cardId);
        }

        // Default template selection. Honour ?template_id= when the user
        // clicked a specific Render link (e.g. from a future "render with X"
        // affordance); otherwise pick the first system template whose
        // qso_type matches this QSO. The hard-coded name lookup we used to
        // do is now redundant — qso_type carries the same intent and is
        // safe across renames of the bundled templates.
        $defaultTemplateId = (int)$this->request->getQuery('template_id', 0);
        if ($defaultTemplateId === 0) {
            $defaultRow = $this->fetchTable('Templates')->find()
                ->where([
                    'Templates.is_system' => true,
                    'Templates.qso_type'  => $qsoType,
                ])
                ->first();
            $defaultTemplateId = $defaultRow ? (int)$defaultRow->id : 0;
        }

        $this->set([
            'qso' => $qso,
            'templates' => $templates,
            'existingUploads' => $existingUploads,
            'defaultTemplateId' => $defaultTemplateId,
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
     * Bulk-render entry point (M2-T11).
     *
     * Accepts a list of `qso_ids`, one `template_id`, and one `upload_id`,
     * mints a session-scoped job token, kicks off the FIRST chunk (up to 5
     * cards) synchronously, and returns a JSON `{job_token, done, total,
     * finished, card_ids}` payload. The frontend is expected to poll the
     * `bulkRenderNext` endpoint until `finished === true`.
     *
     * Why chunking at all: shared hosts cap individual PHP requests at ~30s,
     * and rendering is GD-bound. Slicing the job into 5-card chunks keeps each
     * HTTP call comfortably under the limit without depending on a worker
     * queue we cannot deploy on shared hosting.
     *
     * Why the session as the job store: it's per-user (so cross-tenant theft
     * is impossible), it's free (no extra table), and it auto-expires when
     * the user logs out. The trade-off is that a logout mid-job orphans the
     * job — acceptable for an interactive UI flow.
     *
     * @return mixed Null with JSON view configured; `setResponse` may shape a 4xx.
     */
    public function bulkRender(): mixed
    {
        $this->request->allowMethod('post');
        // Session fixation hardening — fresh job, fresh session id.
        $this->request->getSession()->renew();
        $userId = $this->Authentication->getIdentity()->getIdentifier();

        $data = $this->request->getData();
        $qsoIds = array_map('intval', (array)($data['qso_ids'] ?? []));
        $templateId = (int)($data['template_id'] ?? 0);

        if (empty($qsoIds) || $templateId === 0) {
            $this->setResponse($this->getResponse()->withStatus(400));
            $this->set(['error' => 'qso_ids and template_id are required.']);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['error']);

            return null;
        }

        // Resolve the template's bound background once, up front. All cards
        // in the batch use the same template so they share one upload; this
        // also gives us a stable id to stamp into the job record for the
        // worker to read on every chunk.
        //
        // Authorization: re-apply the same predicate the picker uses
        // (system | owned | public-approved) so a hand-crafted POST can't
        // bulk-render with another user's private template. Fail closed
        // with a JSON 403, matching the 400 envelope above so the
        // frontend handles both consistently.
        $template = $this->fetchTable('Templates')->find()
            ->where(['Templates.id' => $templateId])
            ->where(['OR' => [
                ['Templates.is_system' => true],
                ['Templates.user_id' => $userId],
                ['AND' => ['Templates.is_public' => true, 'Templates.is_approved' => true]],
            ]])
            ->first();
        if ($template === null) {
            $this->setResponse($this->getResponse()->withStatus(403));
            $this->set(['error' => 'Template not found or not allowed.']);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['error']);

            return null;
        }

        [$upload, , ] = $this->resolveTemplateBackground($template, $userId);
        $uploadId = (int)$upload->id;

        // Drop any selected QSO whose qso_type doesn't match the chosen
        // template — silently skipping is safer than half-rendering a wrong-
        // shape card. The skipped count flows into the chunk worker's
        // running total alongside already-rendered ones.
        $templateQsoType = (string)($template->qso_type ?? 'contact');
        $mismatchedIds = $this->fetchTable('Qsos')->find()
            ->select(['id'])
            ->where([
                'Qsos.id IN' => $qsoIds,
                'Qsos.user_id' => $userId,
                'Qsos.qso_type !=' => $templateQsoType,
            ])
            ->all()
            ->extract('id')
            ->toArray();
        $mismatchedIds = array_map('intval', array_unique($mismatchedIds));
        $mismatchedCount = count($mismatchedIds);
        $qsoIds = array_values(array_diff($qsoIds, $mismatchedIds));

        // Skip QSOs that already have a non-deleted card — mirrors the
        // single-render guard. Without this, hitting "Bulk render" twice
        // silently produces duplicate cards for the same QSOs. We report
        // the skipped count so the frontend can surface it ("3 of 5 rendered;
        // 2 already had cards").
        $alreadyRendered = $this->fetchTable('Cards')->find()
            ->select(['qso_id'])
            ->where([
                'Cards.qso_id IN' => $qsoIds,
                'Cards.user_id' => $userId,
                'Cards.deleted_at IS' => null,
            ])
            ->all()
            ->extract('qso_id')
            ->toArray();
        $alreadyRendered = array_map('intval', array_unique($alreadyRendered));
        $skipped = count($alreadyRendered) + $mismatchedCount;
        $qsoIds = array_values(array_diff($qsoIds, $alreadyRendered));

        if (empty($qsoIds)) {
            // Everything in the selection was already rendered. Return a
            // finished job synchronously rather than minting a token.
            $this->set([
                'job_token' => null,
                'done' => 0,
                'total' => 0,
                'finished' => true,
                'card_ids' => [],
                'skipped' => $skipped,
                'message' => 'All selected QSOs already have rendered cards.',
            ]);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', [
                'job_token', 'done', 'total', 'finished', 'card_ids', 'skipped', 'message',
            ]);
            return null;
        }

        $token = bin2hex(random_bytes(16));
        $this->request->getSession()->write("bulk_render.{$token}", [
            'user_id' => $userId,
            'qso_ids' => $qsoIds,
            'template_id' => $templateId,
            'upload_id' => $uploadId,
            'cursor' => 0,
            'card_ids' => [],
            'skipped' => $skipped,
        ]);

        return $this->processBulkRenderChunk($token);
    }

    /**
     * Render the next chunk of an in-flight bulk job (M2-T11).
     *
     * Idempotent on a per-cursor basis — the session record is the single
     * source of truth for `cursor`, and the job is deleted from the session
     * once `finished` flips, so a stale poll after completion 404s.
     *
     * @param string $token The opaque job token returned by `bulkRender()`.
     * @return mixed Null with JSON view configured.
     */
    public function bulkRenderNext(string $token): mixed
    {
        $this->request->allowMethod('post');

        return $this->processBulkRenderChunk($token);
    }

    /**
     * Render up to 5 QSOs from the given job, advance the cursor, return JSON.
     *
     * Per-card failures are recorded as `null` in `card_ids` rather than
     * aborting the whole job — a single bad QSO shouldn't poison the rest of
     * the batch. Authorization is enforced by comparing the job's `user_id`
     * to the current identity.
     *
     * @param string $token The job token to advance.
     * @return mixed Null with JSON view configured.
     */
    private function processBulkRenderChunk(string $token): mixed
    {
        $session = $this->request->getSession();
        $job = $session->read("bulk_render.{$token}");
        if (!$job) {
            $this->setResponse($this->getResponse()->withStatus(404));
            $this->set(['error' => 'job not found']);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['error']);

            return null;
        }

        $userId = $this->Authentication->getIdentity()->getIdentifier();
        if ($job['user_id'] !== $userId) {
            // Defense in depth: session is per-user already, but a hostile
            // session-share scenario should still bounce.
            $this->setResponse($this->getResponse()->withStatus(403));
            $this->set(['error' => 'forbidden']);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['error']);

            return null;
        }

        $chunk = array_slice($job['qso_ids'], $job['cursor'], 5);
        foreach ($chunk as $qsoId) {
            try {
                $cardId = $this->renderQsoCard($userId, (int)$qsoId, $job['template_id'], $job['upload_id']);
                $job['card_ids'][] = $cardId;
            } catch (\Throwable $e) {
                // Mark this slot as failed and keep going; the UI can surface
                // the null entries to the user without losing successful cards.
                $job['card_ids'][] = null;
            }
        }
        $job['cursor'] += count($chunk);
        $finished = $job['cursor'] >= count($job['qso_ids']);

        if ($finished) {
            $session->delete("bulk_render.{$token}");
        } else {
            $session->write("bulk_render.{$token}", $job);
        }

        $this->set([
            'job_token' => $token,
            'done' => $job['cursor'],
            'total' => count($job['qso_ids']),
            'finished' => $finished,
            'card_ids' => $job['card_ids'],
            'skipped' => (int)($job['skipped'] ?? 0),
        ]);
        $this->viewBuilder()->setClassName('Json');
        $this->viewBuilder()->setOption('serialize', [
            'job_token', 'done', 'total', 'finished', 'card_ids', 'skipped',
        ]);

        return null;
    }

    /**
     * Render a single QSO into a Card row + on-disk PNG/PDF.
     *
     * Shared between the interactive `renderCard` action (M2-T10) and the
     * bulk render endpoints (M2-T11). All callers MUST have already verified
     * the upload belongs to `$userId` (we re-check via the predicate here, so
     * a foreign upload id 404s before any GD work runs).
     *
     * @param int $userId Authenticated user id.
     * @param int $qsoId QSO primary key.
     * @param int $templateId Template primary key (system, owned, or public-approved).
     * @param int $uploadId Upload primary key (must belong to `$userId`).
     * @param string|null $authorOverride When set, used for the attribution
     *        footer line instead of the upload row's stored author_name. Lets
     *        the interactive renderCard action express "the admin's
     *        configured default-bg attribution" or "the form-supplied values"
     *        without writing them onto an existing dedup-matched upload row.
     * @param string|null $licenseOverride Same idea for the license code.
     * @return int The newly persisted Card primary key.
     */
    private function renderQsoCard(
        int $userId,
        int $qsoId,
        int $templateId,
        int $uploadId,
        ?string $authorOverride = null,
        ?string $licenseOverride = null,
    ): int {
        $qsos = $this->fetchTable('Qsos');
        $qso = $qsos->find()->where(['id' => $qsoId, 'user_id' => $userId])->firstOrFail();
        $template = $this->fetchTable('Templates')->get($templateId);
        // Upload is no longer user-picked — it's resolved internally by
        // resolveTemplateBackground (or its bulk equivalent), which derives
        // the row from the template's binding or the site-default. That
        // means it may legitimately be owned by another user (e.g. the
        // template author for a system/public template), so we deliberately
        // do NOT re-scope by user_id here. The deleted_at guard remains —
        // a soft-deleted row's file may have been pruned and would 500 GD.
        $upload = $this->fetchTable('CardBackgrounds')->find()
            ->where(['id' => $uploadId, 'CardBackgrounds.deleted_at IS' => null])
            ->firstOrFail();

        $finalPath = WWW_ROOT . $upload->storage_path;
        $layout = json_decode((string)$template->layout_json, true) ?: [];
        $qsoData = $this->qsoToRenderData($qso);

        $renderer = \App\Service\CardRenderer::fromSettings(WWW_ROOT . 'files/fonts/');
        $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
        // WebP output — ~40% smaller than the prior PNG-level-6 baseline. The
        // column name `cards.png_path` is kept for backwards compat with rows
        // persisted before this commit.
        $cardPath = WWW_ROOT . 'files/cards/' . $uuid . '.webp';
        if (!is_dir(dirname($cardPath))) {
            mkdir(dirname($cardPath), 0o775, true);
        }
        // Override > row value > null. This lets renderCard ship freshly-
        // computed attribution (admin defaults / form values) past any stale
        // values on a dedup-matched upload row, while bulk render — which
        // doesn't pass overrides — keeps using the row's stored attribution.
        $attributionLine = \App\Service\ImageLicense::formatLine(
            $authorOverride ?? ($upload->author_name ?? null),
            $licenseOverride ?? ($upload->license ?? null),
            (string)($qsoData['operator_callsign'] ?? '')
        );
        $renderer->renderPng(
            ['canvas_width' => $template->canvas_width, 'canvas_height' => $template->canvas_height,
             'fields' => $layout['fields'] ?? []],
            $finalPath,
            $qsoData,
            $cardPath,
            extraFooterLines: [$attributionLine]
        );
        // No pre-rendered PDF — built on demand by CardsController::downloadPdf
        // when the user clicks "Download PDF". Halves per-card disk usage.

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
            'png_path' => 'files/cards/' . $uuid . '.webp',
            'pdf_path' => null,
        ]));

        // M4-T3: Audit each card produced. Lives in the helper so both the
        // interactive `renderCard` action and the bulk render endpoints
        // emit exactly one `card.generated` row per card. Audit failures
        // must never break the user-facing render flow.
        try {
            (new \App\Service\AuditLogger())->log(
                event: 'card.generated',
                actorUserId: $userId,
                target: ['type' => 'Cards', 'id' => (int)$card->id],
                metadata: ['source' => 'qso_render', 'qso_id' => (int)$qso->id],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        return $card->id;
    }

    /**
     * Move the request's `background_upload` file to a tempdir scratch path.
     *
     * @return string Absolute path to the temp file caller must clean up.
     * @throws \Cake\Http\Exception\BadRequestException When no usable upload was provided.
     */
    /**
     * Resolve the upload + attribution to use for a given template at render
     * time. The template owns its background — if `background_upload_id` is
     * bound and still valid, that upload wins. Otherwise we fall through to
     * the site-default image and ensure (via SHA-dedup) an `uploads` row
     * exists for it so the downstream card row's FK is satisfied.
     *
     * @return array{0:object,1:?string,2:string} [upload entity, author, license]
     */
    private function resolveTemplateBackground(object $template, int $userId): array
    {
        $uploadsTbl = $this->fetchTable('CardBackgrounds');

        // Template-bound bg path. Must be active (deleted_at IS NULL) and
        // still on disk — soft-deleted rows might have had their file pruned.
        $boundId = (int)($template->background_upload_id ?? 0);
        if ($boundId > 0) {
            $bound = $uploadsTbl->find()
                ->where(['id' => $boundId, 'CardBackgrounds.deleted_at IS' => null])
                ->first();
            if ($bound !== null && is_file(WWW_ROOT . $bound->storage_path)) {
                return [$bound, $bound->author_name, (string)$bound->license];
            }
            // Bound bg vanished — fall through to site default rather than
            // 500ing on the user. The render still succeeds.
        }

        // Site-default path: optimise the on-disk default image, content-
        // hash dedup against `uploads`, resurrect a soft-deleted match if
        // present, otherwise insert.
        $tmpUpload = $this->resolveBackgroundUpload();
        $bgInfo = @getimagesize($tmpUpload);
        if ($bgInfo === false) {
            @unlink($tmpUpload);
            throw new \Cake\Http\Exception\BadRequestException('Default background is not a valid image.');
        }
        if ($bgInfo[0] * $bgInfo[1] > 50_000_000) {
            @unlink($tmpUpload);
            throw new \Cake\Http\Exception\BadRequestException('Default background dimensions exceed allowed limit.');
        }

        $optimizer = new \App\Service\ImageOptimizer(maxWidth: 1600, maxHeight: 1200, quality: 78);
        $tmpDest = tempnam(sys_get_temp_dir(), 'eqsl_opt_');
        $info = $optimizer->optimize($tmpUpload, $tmpDest);
        @unlink($tmpUpload);

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

        // Attribution: pull from app_settings for the default bg.
        $settings = new \App\Service\AppSettings();
        $authorName = trim((string)$settings->get('default_background_author', ''));
        $authorName = $authorName !== '' ? $authorName : null;
        $license = (string)$settings->get('default_background_license', 'unknown');

        $upload = $uploadsTbl->find()->where(['sha256_hash' => $sha])->first();
        if (!$upload) {
            $upload = $uploadsTbl->saveOrFail($uploadsTbl->newEntity([
                'user_id'           => $userId,
                'original_filename' => 'site-default.jpg',
                'storage_path'      => 'files/uploads/' . $sha . '.jpg',
                'mime_type'         => 'image/jpeg',
                'width_px'          => $info['width_px'],
                'height_px'         => $info['height_px'],
                'file_size_bytes'   => $info['file_size_bytes'],
                'sha256_hash'       => $sha,
                'author_name'       => $authorName,
                'license'           => $license,
            ]));
        } elseif ($upload->deleted_at !== null) {
            // Resurrect — file was just rewritten above, FK lookups need
            // deleted_at IS NULL.
            $upload->set('deleted_at', null, ['guard' => false]);
            $upload->set('author_name', $authorName, ['guard' => false]);
            $upload->set('license', $license, ['guard' => false]);
            $uploadsTbl->saveOrFail($upload);
        }

        return [$upload, $authorName, $license];
    }

    /**
     * Return a temp copy of the site-default background image. Strictly
     * disk-only — does NOT read $_FILES['background_upload'].
     *
     * Per-render background uploads were removed in 67ee8e7 (backgrounds
     * are now template-bound). Leaving the request-file branch active
     * here would re-introduce that capability via a crafted POST whenever
     * the chosen template has no bound background, which is the exact
     * scenario this helper is invoked for. The returned path is a temp
     * copy so the caller's @unlink doesn't delete the source.
     */
    private function resolveBackgroundUpload(): string
    {
        $candidates = [
            WWW_ROOT . 'files/templates/_default-bg.jpg',
            WWW_ROOT . 'files/templates/_demo-bg.jpg',
        ];
        foreach ($candidates as $abs) {
            if (is_file($abs)) {
                $tmp = tempnam(sys_get_temp_dir(), 'eqsl_');
                copy($abs, $tmp);

                return $tmp;
            }
        }

        throw new \Cake\Http\Exception\BadRequestException(
            'No background available — pick an existing one, upload a new image, or have admin set a default.'
        );
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
            'qso_date_hijri'     => $qso->qso_datetime_utc !== null
                ? \App\Service\HijriDate::fromGregorian($qso->qso_datetime_utc)
                : '',
            'frequency_mhz'      => (string)($qso->frequency_mhz ?? ''),
            'band'               => (string)($qso->band ?? ''),
            'mode'               => (string)($qso->mode ?? ''),
            'rst_sent'           => (string)($qso->rst_sent ?? ''),
            'rst_received'       => (string)($qso->rst_received ?? ''),
            'operator_name'      => (string)($qso->operator_name ?? ''),
            'notes'              => (string)($qso->notes ?? ''),
            // Net check-in fields. Always present (empty string for contact
            // QSOs) so card templates that reference {ncs_callsign} etc. don't
            // surface a literal placeholder when rendered against a contact row.
            'qso_type'           => (string)($qso->qso_type ?? 'contact'),
            'ncs_callsign'       => (string)($qso->ncs_callsign ?? ''),
            'net_title'          => (string)($qso->net_title ?? ''),
            'net_organisation'   => (string)($qso->net_organisation ?? ''),
            // Radioless QSO. {transport} renders the human label
            // ("Echolink", "RF (over the air)") rather than the raw code so
            // cards read naturally; templates that want the code can use
            // {transport_code} ... actually we keep it simple: just label.
            'transport'          => \App\Service\Transport::label($qso->transport ?? null),
            'transport_meta'     => (string)($qso->transport_meta ?? ''),
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
