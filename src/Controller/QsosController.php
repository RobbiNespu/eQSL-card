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
