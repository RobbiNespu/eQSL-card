<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Qsos Controller (M2-T2)
 *
 * Logbook surface for the authenticated user. T2 ships the paginated `index`
 * with callsign/band/mode/date-range filters. T3 will add manual CRUD on top.
 *
 * Authorization model: every query is scoped by `user_id = current identity`,
 * so a user can only ever see their own QSOs (verified by
 * `testListsOnlyOwnQsos`).
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
     * Placeholder QSO detail view.
     *
     * T3 (CRUD) will flesh this out; T8 may also drive it from the card flow.
     * Kept here so the index page's "View" buttons resolve to a route rather
     * than a 404 in dev.
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
}
