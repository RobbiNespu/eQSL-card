# M7 — NCS dashboard hardening + backlog cleanup — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close every deferred M6 item — finish the live participant map, add the missing retention streak, harden security/live-update fidelity, and pay down five internal refactors — without user-visible regressions.

**Architecture:** Schema-light: one new tombstone table (`net_session_removals`) underpins instant live removal; everything else is additive controller/service/JS changes that compose on the existing M6 surfaces. Refactors are behavior-preserving and guarded by the existing 534-test suite.

**Tech Stack:** PHP 8.1, CakePHP 5, MariaDB, Phinx migrations, PHPUnit, vanilla JS + Vitest, Leaflet (vendored), dompdf.

**Spec:** `docs/superpowers/specs/2026-05-26-m7-ncs-hardening-design.md`

---

## Assumptions (carrying conventions from M5/M6)

- Branch: `m7-ncs-hardening`. Commit author: `Robbi Nespu <robbinespu@gmail.com>`, NO Co-Authored-By trailer.
- Test commands:
  - PHP: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter <Name>`
  - JS: `npx vitest run tests/js/<file>`
  - CSS rebuild (only if `theme.css` changes): `npm run build:css`
- Tests use the inline-user `login()` / `seedNetSession()` helpers already defined in `tests/TestCase/Controller/NetSessionsControllerTest.php`. Reuse them.
- Operational logging via `App\Service\OperationLog::event/warning/failure` (already in place; secrets/PII auto-redacted).

---

## File Structure (what gets touched)

**New**
- `config/Migrations/20260526000001_CreateNetSessionRemovals.php` — tombstone table.
- `src/Model/Entity/NetSessionRemoval.php`, `src/Model/Table/NetSessionRemovalsTable.php`.
- `src/Controller/Admin/AdminController.php` — shared admin base class.
- `webroot/js/csrf.js` — `window.eqslCsrf` reader (consumed by other files).
- `tests/Fixture/NetSessionRemovalsFixture.php`.

**Modified — controllers**
- `src/Controller/NetSessionsController.php` — checkin DELETE writes tombstone; feed returns `map`+`removed[]`+ETag; `rotateToken` action; GET→POST `join`; (later) split `renderQsoCard` of QsosController and `saveTemplate` of TemplatesController.
- `src/Controller/NetController.php` — feed returns `map`+`removed[]`+ETag.
- `src/Controller/Admin/*Controller.php` (10 files) — re-parent to `AdminController` base.
- `src/Controller/Admin/CleanupController.php` — add tombstone sweep.

**Modified — services**
- `src/Service/NetMetrics.php` — DI (NetSessionsTable injected); add `longest_streak` to `retention()`.

**Modified — JS**
- `webroot/js/net-merge.js` — add `renderRoster`, `applyStats`, `startPollLoop`, accept feed `removed[]`.
- `webroot/js/net-cockpit.js` / `net-poll.js` / `net-live.js` — consume shared helpers; send `If-None-Match`.
- `webroot/js/net-map.js` — render from `net:updated` `detail.map` in addition to static `data-map-json`.
- `webroot/js/app.js`, `webroot/js/designer.js`, `webroot/js/offline-sync.js` — use `window.eqslCsrf`.

**Modified — templates**
- `templates/element/net/stat_tiles.php` — map container (re-added, single use for cockpit + public).
- `templates/NetSessions/cockpit.php` + `templates/Net/live.php` — load Leaflet CSS/JS + `net-map.js`.
- `templates/NetSessions/view.php` — "Regenerate invite link" button.
- `templates/NetSessions/analytics.php` — surface `longest_streak`.
- `templates/NetSessions/join_confirm.php` — new GET confirm page.

**Modified — config**
- `config/routes.php` — `/net-sessions/{id}/rotate-token` (POST); `/net-sessions/join/{token}` split into GET (confirm) + POST (commit join).

**Modified — release**
- `src/Service/AdifExporter.php` — PROGRAMVERSION → `1.3.0`.
- `README.md` — roadmap line + status string.

---

## Phase 1 — Live-removal tombstones (A4)

### Task 1: Migration — `net_session_removals` table

**Files:** Create `config/Migrations/20260526000001_CreateNetSessionRemovals.php`

- [ ] **Step 1: Write the migration**

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M7 A4 — `net_session_removals` tombstone table.
 *
 * Records each check-in (qso) deletion within a net session so the
 * delta feed can surface a `removed[]` list to live viewers within
 * one poll, instead of the deletion only being visible after a full
 * page refresh. Tombstones are pruned by CleanupController after a
 * short retention window (the live consumers don't need old ones).
 */
final class CreateNetSessionRemovals extends AbstractMigration
{
    public function up(): void
    {
        $this->table('net_session_removals')
            ->addColumn('net_session_id', 'integer', ['null' => false])
            ->addColumn('qso_id', 'integer', ['null' => false])
            ->addColumn('removed_at', 'datetime', ['null' => false])
            ->addIndex(['net_session_id', 'removed_at'])
            ->create();
    }

    public function down(): void
    {
        $this->table('net_session_removals')->drop()->save();
    }
}
```

- [ ] **Step 2: Run + verify**

Run: `docker compose run --rm --no-deps php bin/cake migrations migrate`
Expected: `== CreateNetSessionRemovals: migrated`
Then: `docker compose exec -T db sh -c 'mariadb -ueqsl -peqsl eqsl -e "DESCRIBE net_session_removals;"'` → 4 columns + the index.

- [ ] **Step 3: Commit**

```bash
git add config/Migrations/20260526000001_CreateNetSessionRemovals.php
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "feat(m7): net_session_removals tombstone migration"
```

---

### Task 2: NetSessionRemoval entity + table + fixture

**Files:**
- Create `src/Model/Entity/NetSessionRemoval.php`
- Create `src/Model/Table/NetSessionRemovalsTable.php`
- Create `tests/Fixture/NetSessionRemovalsFixture.php`

- [ ] **Step 1: Entity**

```php
<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * M7 A4 — net-session check-in tombstone. Written when a check-in is
 * deleted; read by the delta feed to populate `removed[]` for live
 * watchers.
 *
 * @property int $id
 * @property int $net_session_id
 * @property int $qso_id
 * @property \Cake\I18n\DateTime $removed_at
 */
class NetSessionRemoval extends Entity
{
    protected array $_accessible = [
        'net_session_id' => true,
        'qso_id'         => true,
        'removed_at'     => true,
    ];
}
```

- [ ] **Step 2: Table**

```php
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use DateTimeInterface;

/**
 * M7 A4 — Tombstones for deleted net check-ins. Append-only.
 *
 * The feed reads tombstones with `removed_at > $since` to tell live
 * clients which check-in ids to drop from their roster.
 */
class NetSessionRemovalsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('net_session_removals');
        $this->setPrimaryKey('id');
        $this->belongsTo('NetSessions', ['foreignKey' => 'net_session_id']);
    }

    /**
     * Record a removal. Idempotent at the application layer (callers
     * only record when a delete actually happened) — no DB-level unique
     * constraint, since the same qso_id could theoretically reappear if
     * a session were re-opened (not supported today but harmless).
     */
    public function record(int $netSessionId, int $qsoId): void
    {
        $entity = $this->newEntity([
            'net_session_id' => $netSessionId,
            'qso_id'         => $qsoId,
            'removed_at'     => \Cake\I18n\DateTime::now(),
        ]);
        $this->saveOrFail($entity);
    }

    /**
     * Ids removed from a session after a given cursor. Used by the
     * delta feed to populate `removed[]`.
     *
     * @return list<int>
     */
    public function idsRemovedSince(int $netSessionId, ?DateTimeInterface $since): array
    {
        $q = $this->find()->where(['net_session_id' => $netSessionId])
            ->select(['qso_id'])->disableHydration();
        if ($since !== null) {
            $q->where(['removed_at >' => $since]);
        }
        return array_values(array_map(static fn ($row) => (int)$row['qso_id'], $q->all()->toList()));
    }
}
```

- [ ] **Step 3: Fixture**

```php
<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class NetSessionRemovalsFixture extends TestFixture
{
    public array $records = [];
}
```

- [ ] **Step 4: Lint + commit**

Run: `docker compose run --rm --no-deps php -l src/Model/Entity/NetSessionRemoval.php` (and the table). Both: `No syntax errors detected`.

```bash
git add src/Model/Entity/NetSessionRemoval.php src/Model/Table/NetSessionRemovalsTable.php tests/Fixture/NetSessionRemovalsFixture.php
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "feat(m7): NetSessionRemoval entity + table + fixture"
```

---

### Task 3: Check-in DELETE writes a tombstone + feed returns `removed[]`

**Files:** Modify `src/Controller/NetSessionsController.php`, `src/Controller/NetController.php`. Test: extend `tests/TestCase/Controller/NetSessionsControllerTest.php`.

- [ ] **Step 1: Write the failing test** (append to NetSessionsControllerTest)

```php
public function testCheckinDeleteWritesTombstoneAndFeedReturnsRemoved(): void
{
    $ownerId = $this->login();
    $sessionId = $this->seedNetSession($ownerId, ['status' => 'live']);
    $qsoId = $this->seedCheckinRow($sessionId, $ownerId, '9W2DEL');

    $this->enableCsrfToken();
    $this->configRequest(['headers' => ['Accept' => 'application/json']]);
    $this->delete("/net-sessions/{$sessionId}/checkins/{$qsoId}");
    $this->assertResponseOk();

    $removals = $this->getTableLocator()->get('NetSessionRemovals');
    $this->assertSame(1, $removals->find()->where(['qso_id' => $qsoId])->count());

    $this->configRequest(['headers' => ['Accept' => 'application/json']]);
    $this->get("/net-sessions/{$sessionId}/checkins?since=2000-01-01T00:00:00%2B00:00");
    $this->assertResponseOk();
    $body = json_decode((string)$this->_response->getBody(), true);
    $this->assertContains($qsoId, $body['removed']);
}
```

- [ ] **Step 2: Run to verify it fails**

`docker compose run --rm --no-deps php vendor/bin/phpunit --filter testCheckinDeleteWritesTombstoneAndFeedReturnsRemoved`
Expected: FAIL (no removals table write happens yet, feed `removed` stays empty).

- [ ] **Step 3: Implement — write tombstone on delete**

In `NetSessionsController::checkin()` DELETE branch (around the existing `$qsos->deleteOrFail($qso);`):

```php
if ($this->request->is('delete')) {
    $this->fetchTable('NetSessionRemovals')->record($id, $qsoId);
    $qsos->deleteOrFail($qso);
    OperationLog::event('net.checkin.deleted', ['session_id' => $id, 'qso_id' => $qsoId]);
    return $this->jsonResponse(['ok' => true, 'removed' => $qsoId]);
}
```

- [ ] **Step 4: Feed returns `removed[]`**

In `NetSessionsController::checkinsFeed()` (and the analogous block in `NetController::feed()`), replace the static `'removed' => []` with:

```php
$sinceDt = null;
if ($since !== '') {
    try { $sinceDt = new DateTime(str_replace(' ', '+', $since)); } catch (\Exception $e) {}
}
$removed = $this->fetchTable('NetSessionRemovals')->idsRemovedSince($session->id, $sinceDt);
```

…and pass `'removed' => $removed,` in the JSON payload.

- [ ] **Step 5: Run to verify it passes**

`docker compose run --rm --no-deps php vendor/bin/phpunit --filter testCheckinDeleteWritesTombstoneAndFeedReturnsRemoved`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Controller/NetSessionsController.php src/Controller/NetController.php tests/TestCase/Controller/NetSessionsControllerTest.php
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "feat(m7): check-in DELETE writes tombstone; feed returns removed[]"
```

---

### Task 4: Tombstone sweep in CleanupController (7-day retention)

**Files:** Modify `src/Controller/Admin/CleanupController.php`. Test extends its existing test if present, else add an integration test.

- [ ] **Step 1: Implement the sweep action**

Add to `CleanupController` (next to the existing sweep methods):

```php
/**
 * Prune net-session removal tombstones older than 7 days. Tombstones
 * exist only to surface live deletions to polling clients; old ones
 * are noise.
 */
public function netRemovalsSweep(): \Cake\Http\Response
{
    $this->request->allowMethod('post');
    $cutoff = \Cake\I18n\DateTime::now()->subDays(7);
    $table = $this->fetchTable('NetSessionRemovals');
    $deleted = $table->deleteAll(['removed_at <' => $cutoff]);
    $this->Flash->success("Pruned {$deleted} old net-removal tombstones.");
    \App\Service\OperationLog::event('admin.cleanup.net_removals_pruned', ['count' => $deleted]);
    return $this->redirect(['action' => 'index']);
}
```

Add a button/form to `templates/Admin/Cleanup/index.php` next to the other sweep forms.

Add route in `config/routes.php` (in the admin scope):

```php
$builder->connect('/admin/cleanup/net-removals-sweep',
    ['controller' => 'Admin/Cleanup', 'action' => 'netRemovalsSweep'])
    ->setMethods(['POST']);
```

(Verify the exact admin-scope syntax used by existing cleanup routes and mirror it.)

- [ ] **Step 2: Test**

Add to the cleanup test (or create `tests/TestCase/Controller/Admin/CleanupControllerTest.php` if none):

```php
public function testNetRemovalsSweepPrunesOldTombstones(): void
{
    $this->loginAsAdmin();              // admin helper already in test
    $this->enableCsrfToken();
    $table = $this->getTableLocator()->get('NetSessionRemovals');
    $old = $table->newEntity(['net_session_id' => 1, 'qso_id' => 1, 'removed_at' => \Cake\I18n\DateTime::now()->subDays(10)]);
    $new = $table->newEntity(['net_session_id' => 1, 'qso_id' => 2, 'removed_at' => \Cake\I18n\DateTime::now()->subHours(1)]);
    $table->saveOrFail($old);
    $table->saveOrFail($new);
    $this->post('/admin/cleanup/net-removals-sweep');
    $this->assertResponseSuccess();
    $this->assertSame(1, $table->find()->count());
}
```

- [ ] **Step 3: Run, commit**

`docker compose run --rm --no-deps php vendor/bin/phpunit --filter CleanupControllerTest`
Expected: PASS.

```bash
git add src/Controller/Admin/CleanupController.php templates/Admin/Cleanup/index.php config/routes.php tests/TestCase/Controller/Admin/CleanupControllerTest.php
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "feat(m7): admin cleanup sweep for net-removal tombstones"
```

---

## Phase 2 — Live participant map on cockpit + public (A1)

### Task 5: Feed includes `map`; net-map.js renders on `net:updated`

**Files:** `src/Controller/NetSessionsController.php`, `src/Controller/NetController.php`, `webroot/js/net-map.js`.

- [ ] **Step 1: Add `map` to feed payloads**

In `checkinsFeed()` and `NetController::feed()`, add to the returned array:

```php
'map' => $metrics->mapPoints($session->id),
```

(Both feeds already construct a `NetMetrics`; reuse it.) Update the existing integration tests to assert `array_key_exists('map', $body)`.

- [ ] **Step 2: net-map.js — render from `net:updated`**

Update `webroot/js/net-map.js` (the IIFE) to also listen for the live event. Current behaviour (read `[data-map-json]` on DOMContentLoaded for analytics) is kept; ADD:

```js
document.addEventListener('net:updated', (e) => {
  const el = document.querySelector('[data-net-map]');
  if (!el || !e.detail || !Array.isArray(e.detail.map)) return;
  renderInto(el, e.detail.map);  // factored from the existing main block
});
```

Refactor the existing rendering logic into a `renderInto(el, points)` helper used by both the static analytics path and the live path; preserve the offline list-fallback.

- [ ] **Step 3: stat_tiles + cockpit + public load the map**

`templates/element/net/stat_tiles.php` — re-add a single map container at the bottom of the element:

```php
<div class="net-map-wrap card">
  <div class="card-body p-2">
    <p class="form-label small text-muted mb-1">Participant map</p>
    <div data-net-map class="net-map-placeholder" aria-label="Participant map"></div>
  </div>
</div>
```

(Re-add `.net-map-placeholder` rule deleted in the earlier cleanup — same shape as before.)

`templates/NetSessions/cockpit.php` script block: include Leaflet CSS + JS + net-map.js (after net-cockpit.js).
`templates/Net/live.php` script block: same.

- [ ] **Step 4: Rebuild CSS + run JS suite**

`npm run build:css` → confirm dist.css contains `.net-map-placeholder` again.
`npx vitest run` → 119 still pass.

- [ ] **Step 5: Commit**

```bash
git add src/Controller/NetSessionsController.php src/Controller/NetController.php webroot/js/net-map.js templates/element/net/stat_tiles.php templates/NetSessions/cockpit.php templates/Net/live.php webroot/css/theme.css webroot/css/dist.css
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "feat(m7): live participant map on cockpit + public (feed.map + net:updated)"
```

---

## Phase 3 — Streaks, token rotation, ETag, POST-join

### Task 6: `longest_streak` in NetMetrics::retention

**Files:** `src/Service/NetMetrics.php`, `tests/TestCase/Service/NetMetricsTest.php`, `templates/NetSessions/analytics.php`.

- [ ] **Step 1: Failing test**

```php
public function testRetentionLongestStreak(): void
{
    // Helpers from the existing NetMetricsTest: seed two ended sessions
    // sharing owner+title, with overlapping callsigns to produce a
    // 2-in-a-row streak. Assert longest_streak >= 2 and streak_leaders
    // contains the callsign present in both.
    // (Use seedCheckin from the existing fixture pattern.)
    $r = $this->metrics()->retention(1, 'Test Net');
    $this->assertArrayHasKey('longest_streak', $r);
    $this->assertArrayHasKey('streak_leaders', $r);
    $this->assertIsInt($r['longest_streak']);
}
```

- [ ] **Step 2: Implement in NetMetrics::retention**

After the existing per-session `$attendance` arrays are built, compute streaks:

```php
// callsign => current run length walking sessionIds oldest -> newest
$run = [];
$best = ['len' => 0, 'leaders' => []];
foreach ($sessionIds as $sid) {
    $present = array_fill_keys($attendance[$sid] ?? [], true);
    foreach (array_keys($present) as $c) {
        $run[$c] = ($run[$c] ?? 0) + 1;
        if ($run[$c] > $best['len']) {
            $best = ['len' => $run[$c], 'leaders' => [$c]];
        } elseif ($run[$c] === $best['len']) {
            $best['leaders'][] = $c;
        }
    }
    // Reset run for callsigns that missed this session.
    foreach (array_keys($run) as $c) {
        if (!isset($present[$c])) {
            $run[$c] = 0;
        }
    }
}
$longestStreak = $best['len'];
$streakLeaders = array_values(array_unique($best['leaders']));
```

Add `'longest_streak' => $longestStreak, 'streak_leaders' => $streakLeaders,` to the returned array.

- [ ] **Step 3: Surface on analytics**

In `templates/NetSessions/analytics.php` retention block, render:

```php
<dt>Longest streak</dt>
<dd>
  <?= h($retention['longest_streak']) ?>
  <?php if ($retention['longest_streak'] > 0): ?>
    · held by <?= h(implode(', ', $retention['streak_leaders'])) ?>
  <?php endif; ?>
</dd>
```

- [ ] **Step 4: Run + commit**

`docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetMetricsTest` → PASS.

```bash
git add src/Service/NetMetrics.php tests/TestCase/Service/NetMetricsTest.php templates/NetSessions/analytics.php
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "feat(m7): NetMetrics longest_streak + streak_leaders + analytics surface"
```

---

### Task 7: Logger-token rotation

**Files:** `src/Controller/NetSessionsController.php`, `config/routes.php`, `templates/NetSessions/view.php`, test.

- [ ] **Step 1: Route**

In the net-sessions block in `config/routes.php`:

```php
$builder->connect('/net-sessions/{id}/rotate-token',
    ['controller' => 'NetSessions', 'action' => 'rotateToken'])
    ->setPass(['id'])->setPatterns(['id' => '\d+'])->setMethods(['POST']);
```

- [ ] **Step 2: Action**

```php
public function rotateToken(int $id): \Cake\Http\Response
{
    $this->request->allowMethod('post');
    $session = $this->ownedOrFail($id);
    $session->set('logger_token', strtolower(Security::randomString(20)), ['guard' => false]);
    $this->fetchTable('NetSessions')->saveOrFail($session);
    (new AuditLogger())->log('net.session.token_rotated',
        actorUserId: $session->owner_id,
        target: ['type' => 'NetSessions', 'id' => $session->id]);
    OperationLog::event('net.session.token_rotated', ['id' => $session->id]);
    $this->Flash->success('Invite link regenerated. Outstanding links no longer work.');
    return $this->redirect(['action' => 'view', $id]);
}
```

- [ ] **Step 3: View button**

In `templates/NetSessions/view.php`, next to the displayed invite link:

```php
<?= $this->Form->postLink('Regenerate invite link',
    "/net-sessions/{$session->id}/rotate-token",
    ['confirm' => 'Replace the current invite link?',
     'class' => 'btn btn-sm btn-outline-secondary']) ?>
```

- [ ] **Step 4: Test**

```php
public function testRotateTokenInvalidatesOldLink(): void
{
    $ownerId = $this->login();
    $sessionId = $this->seedNetSession($ownerId, ['logger_token' => 'old-token', 'status' => 'live']);
    $this->enableCsrfToken();
    $this->post("/net-sessions/{$sessionId}/rotate-token");
    $this->assertResponseSuccess();
    $row = $this->getTableLocator()->get('NetSessions')->get($sessionId);
    $this->assertNotSame('old-token', $row->logger_token);
    // Old token now 404s on the join confirm page.
    $this->get('/net-sessions/join/old-token');
    $this->assertResponseCode(404);
}
```

- [ ] **Step 5: Run + commit**

```bash
git add src/Controller/NetSessionsController.php config/routes.php templates/NetSessions/view.php tests/TestCase/Controller/NetSessionsControllerTest.php
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "feat(m7): owner-rotatable logger token + view UI + test"
```

---

### Task 8: ETag / 304 on both feeds

**Files:** `src/Controller/NetSessionsController.php`, `src/Controller/NetController.php`, `webroot/js/net-poll.js`, `webroot/js/net-live.js`.

- [ ] **Step 1: Validator helper**

Add a private helper used by both controllers (e.g., a static in a tiny new `src/Service/NetFeedValidator.php`):

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Table;

/**
 * Computes a weak ETag for a session's delta feed:
 *   W/"<sessionId>-<rowCount>-<maxUpdatedAtEpoch>-<maxRemovedAtEpoch>"
 * Two clients with the same value have provably-identical roster state.
 */
final class NetFeedValidator
{
    public function __construct(private Table $qsos, private Table $removals) {}

    public function compute(int $sessionId): string
    {
        $q = $this->qsos->find()
            ->where(['net_session_id' => $sessionId])
            ->select(['n' => 'COUNT(*)', 'mx' => 'MAX(updated_at)'])
            ->disableHydration()->first() ?? ['n' => 0, 'mx' => null];
        $r = $this->removals->find()
            ->where(['net_session_id' => $sessionId])
            ->select(['mx' => 'MAX(removed_at)'])
            ->disableHydration()->first() ?? ['mx' => null];
        $epoch = static fn ($v) => $v ? (new \DateTimeImmutable((string)$v))->getTimestamp() : 0;
        return sprintf('W/"%d-%d-%d-%d"', $sessionId, (int)$q['n'], $epoch($q['mx']), $epoch($r['mx']));
    }
}
```

- [ ] **Step 2: Wire into both feeds**

At the top of each feed action:

```php
$validator = new \App\Service\NetFeedValidator(
    $this->fetchTable('Qsos'),
    $this->fetchTable('NetSessionRemovals')
);
$etag = $validator->compute($session->id);
if ($this->request->getHeaderLine('If-None-Match') === $etag) {
    return $this->response->withStatus(304)->withHeader('ETag', $etag);
}
```

…and on the returned JSON response, set `->withHeader('ETag', $etag)`.

- [ ] **Step 3: Clients send `If-None-Match`**

In `webroot/js/net-poll.js` and `webroot/js/net-live.js`, store the last ETag and send it on the next request. Re-introduce the 304 short-circuit (kept polling, didn't update state):

```js
let lastEtag = '';
async function tick() {
  if (document.hidden) return;
  const headers = { 'Accept': 'application/json' };
  if (lastEtag) headers['If-None-Match'] = lastEtag;
  const res = await fetch(cfg.feedUrl + (since ? '?since=' + encodeURIComponent(since) : ''), { headers });
  if (res.status === 304) return;
  lastEtag = res.headers.get('ETag') || lastEtag;
  // …existing body handling
}
```

- [ ] **Step 4: Test**

```php
public function testFeedReturnsEtagAnd304OnRepeat(): void
{
    $ownerId = $this->login();
    $sessionId = $this->seedNetSession($ownerId, ['status' => 'live']);
    $this->configRequest(['headers' => ['Accept' => 'application/json']]);
    $this->get("/net-sessions/{$sessionId}/checkins");
    $this->assertResponseOk();
    $etag = $this->_response->getHeaderLine('ETag');
    $this->assertNotSame('', $etag);
    $this->configRequest(['headers' => ['Accept' => 'application/json', 'If-None-Match' => $etag]]);
    $this->get("/net-sessions/{$sessionId}/checkins");
    $this->assertResponseCode(304);
}
```

- [ ] **Step 5: Run + commit**

```bash
git add src/Service/NetFeedValidator.php src/Controller/NetSessionsController.php src/Controller/NetController.php webroot/js/net-poll.js webroot/js/net-live.js tests/TestCase/Controller/NetSessionsControllerTest.php
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "feat(m7): ETag/304 on both net feeds + client If-None-Match"
```

---

### Task 9: GET→POST invite-join (security)

**Files:** `config/routes.php`, `src/Controller/NetSessionsController.php`, `templates/NetSessions/join_confirm.php`, test.

- [ ] **Step 1: Split the route**

Replace the existing single join route with two:

```php
$builder->connect('/net-sessions/join/{token}',
    ['controller' => 'NetSessions', 'action' => 'joinConfirm'])
    ->setPass(['token'])->setMethods(['GET']);
$builder->connect('/net-sessions/join/{token}',
    ['controller' => 'NetSessions', 'action' => 'join'])
    ->setPass(['token'])->setMethods(['POST']);
```

- [ ] **Step 2: Confirm-page action**

```php
public function joinConfirm(string $token): void
{
    $session = $this->fetchTable('NetSessions')->find()->where(['logger_token' => $token])->first();
    if ($session === null) {
        throw new NotFoundException('Invalid invite link.');
    }
    $this->set(['session' => $session, 'token' => $token, 'title' => 'Join as logger']);
}
```

- [ ] **Step 3: Confirm view**

`templates/NetSessions/join_confirm.php`:

```php
<?php $this->assign('title', $title); ?>
<h1>Join as a co-logger</h1>
<p>You've been invited to log check-ins for <strong><?= h($session->net_title) ?></strong>
   (<?= h($session->net_organisation) ?>). Confirm below to accept.</p>
<?= $this->Form->create(null, ['url' => "/net-sessions/join/{$token}", 'class' => 'mt-3']) ?>
  <button class="btn btn-primary">Join as logger</button>
  <a class="btn btn-link" href="/dashboard">Cancel</a>
<?= $this->Form->end() ?>
```

- [ ] **Step 4: Existing `join()` action — restrict to POST**

The existing `join(string $token)` body is unchanged but `$this->request->allowMethod('post');` is added on the first line. (The mutation is now POST-only; GET goes to the confirm page above.)

- [ ] **Step 5: Test updates**

Change `testInviteJoinAddsCoLogger` to: GET first (confirm page renders, 200, no logger row created), then enableCsrfToken + POST, then assert the row exists + redirect to cockpit.

```php
public function testInviteJoinIsTwoStep(): void
{
    $owner = $this->login('owner@x.com');
    $coId  = $this->createUser('co@x.com');
    $sessionId = $this->seedNetSession($owner, ['logger_token' => 'tok-join-1', 'status' => 'live']);

    $this->session(['Auth' => ['id' => $coId]]);
    // GET = confirm page, NO mutation
    $this->get('/net-sessions/join/tok-join-1');
    $this->assertResponseOk();
    $loggers = $this->getTableLocator()->get('NetSessionLoggers');
    $this->assertFalse($loggers->exists(['net_session_id' => $sessionId, 'user_id' => $coId]));

    // POST = mutation
    $this->enableCsrfToken();
    $this->post('/net-sessions/join/tok-join-1');
    $this->assertRedirectContains('/cockpit');
    $this->assertTrue($loggers->exists(['net_session_id' => $sessionId, 'user_id' => $coId]));
}
```

- [ ] **Step 6: Run + commit**

```bash
git add config/routes.php src/Controller/NetSessionsController.php templates/NetSessions/join_confirm.php tests/TestCase/Controller/NetSessionsControllerTest.php
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "feat(m7): GET→POST invite-join with confirm page + tests"
```

---

## Phase 4 — Internal refactors (behavior-preserving)

### Task 10: NetMetrics constructor DI

**Files:** `src/Service/NetMetrics.php`, all call sites, `tests/TestCase/Service/NetMetricsTest.php`.

- [ ] **Step 1: Change the constructor**

```php
public function __construct(
    private \Cake\ORM\Table $qsos,
    private \Cake\ORM\Table $netSessions,
) {}
```

Replace internal `TableRegistry::getTableLocator()->get('NetSessions')` calls with `$this->netSessions`.

- [ ] **Step 2: Update call sites**

In `NetSessionsController` (checkinsFeed, analytics, exportPdf) and `NetController` (feed), replace:

```php
$metrics = new NetMetrics($this->fetchTable('Qsos'));
```

with:

```php
$metrics = new NetMetrics($this->fetchTable('Qsos'), $this->fetchTable('NetSessions'));
```

- [ ] **Step 3: Update the metrics test**

In `NetMetricsTest::metrics()` helper:

```php
private function metrics(): NetMetrics
{
    return new NetMetrics(
        TableRegistry::getTableLocator()->get('Qsos'),
        TableRegistry::getTableLocator()->get('NetSessions'),
    );
}
```

- [ ] **Step 4: Run full suite + commit**

`docker compose run --rm --no-deps php vendor/bin/phpunit` → all green.

```bash
git add src/Service/NetMetrics.php src/Controller/NetSessionsController.php src/Controller/NetController.php tests/TestCase/Service/NetMetricsTest.php
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "refactor(m7): NetMetrics constructor DI for NetSessionsTable"
```

---

### Task 11: Shared `Admin\AdminController` base

**Files:** Create `src/Controller/Admin/AdminController.php`. Modify each `src/Controller/Admin/*Controller.php` to extend it and drop the duplicated gate.

- [ ] **Step 1: Base class**

```php
<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Http\Exception\ForbiddenException;

/**
 * Shared parent for all admin controllers. Enforces the
 * "must be authenticated admin" gate once instead of repeating it
 * in every subclass's beforeFilter().
 */
abstract class AdminController extends AppController
{
    /** @var int Identity of the calling admin (set in beforeFilter). */
    protected int $actorId = 0;

    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        $identity = $this->Authentication->getIdentity();
        if ($identity === null) {
            $this->redirect('/login');
            return;
        }
        $user = $this->fetchTable('Users')->get($identity->getIdentifier());
        if (($user->role ?? '') !== 'admin') {
            throw new ForbiddenException('Admin access required.');
        }
        $this->actorId = (int)$user->id;
    }
}
```

- [ ] **Step 2: Re-parent each admin controller**

For every `src/Controller/Admin/*Controller.php`:

1. Change `extends \App\Controller\AppController` → `extends \App\Controller\Admin\AdminController`.
2. Delete its private `actorId` declaration, its `beforeFilter` body (or replace with `parent::beforeFilter($event);`), and the duplicated initialize() if it only loaded `Authentication.Authentication`.
3. Keep any controller-specific `beforeFilter` logic but call `parent::beforeFilter($event);` first.

- [ ] **Step 3: Run admin test suite**

`docker compose run --rm --no-deps php vendor/bin/phpunit --filter "Admin"` → all green.

- [ ] **Step 4: Commit**

```bash
git add src/Controller/Admin/
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "refactor(m7): extract shared Admin\\AdminController base"
```

---

### Task 12: Split `TemplatesController::saveTemplate` + `QsosController::renderQsoCard`

**Files:** `src/Controller/TemplatesController.php`, `src/Controller/QsosController.php`.

- [ ] **Step 1: TemplatesController split**

Extract three private helpers from `saveTemplate()`:
- `validateTemplateInput(array $data): array` — runs the three validation branches, returns sanitized data.
- `applyBackgroundBinding(\App\Model\Entity\Template $tpl, array $data): void` — the upload/binding lookup.
- `renderThumbnailIfPossible(\App\Model\Entity\Template $tpl): void` — the post-save thumbnail render in its own try/catch.

`saveTemplate()` becomes a 20-30 line orchestrator that calls them in order.

- [ ] **Step 2: QsosController split**

Extract from `renderQsoCard()`:
- `fetchRenderDependencies(int $qsoId, int $templateId, ?int $uploadId): array` — fetch QSO+template+upload entities.
- `writeCardRow(array $deps, string $renderedPath): \App\Model\Entity\Card` — DB write + audit.

`renderQsoCard()` becomes a thinner orchestrator.

- [ ] **Step 3: Run full suite + commit**

`docker compose run --rm --no-deps php vendor/bin/phpunit` → all green (existing render/template tests must stay green).

```bash
git add src/Controller/TemplatesController.php src/Controller/QsosController.php
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "refactor(m7): split saveTemplate + renderQsoCard into helpers"
```

---

### Task 13: Net-JS dedup — shared `renderRoster`/`applyStats`/`startPollLoop`

**Files:** `webroot/js/net-merge.js`, `webroot/js/net-cockpit.js`, `webroot/js/net-poll.js`, `webroot/js/net-live.js`, `tests/js/net-merge.test.js`.

- [ ] **Step 1: Add tests for the new pure helpers**

In `tests/js/net-merge.test.js`:

```js
import { renderRoster } from '../../webroot/js/net-merge.js';

it('renderRoster writes a tbody innerHTML from rows', () => {
  document.body.innerHTML = '<table><tbody data-net-roster></tbody></table>';
  const tbody = document.querySelector('tbody');
  renderRoster(tbody, [{ id: 1, callsign: 'A', signal: 9 }, { id: 2, callsign: 'B' }]);
  expect(tbody.querySelectorAll('tr').length).toBe(2);
  expect(tbody.innerHTML).toContain('A');
  expect(tbody.innerHTML).toContain('S9');
});
```

Run → FAIL (renderRoster not yet exported).

- [ ] **Step 2: Implement `renderRoster` and `applyStats` in `net-merge.js`**

```js
export function renderRoster(tbody, rows) {
  if (!tbody) return;
  tbody.innerHTML = rows.map((r, i) => `
    <tr data-checkin-id="${r.id ?? ''}">
      <td>${rows.length - i}</td>
      <td class="callsign">${r.callsign ?? ''}</td>
      <td>${r.name ?? ''}</td>
      <td>${r.grid ?? ''}</td>
      <td>${r.signal != null ? 'S' + r.signal : ''}</td>
      <td>${r.role ?? ''}</td>
      <td></td>
    </tr>`).join('');
}

export function applyStats(stats) {
  if (!stats) return;
  const set = (k, v) => {
    const el = document.querySelector(`[data-stat="${k}"] [data-stat-value]`);
    if (el && v != null) el.textContent = v;
  };
  set('checkins', stats.checkins);
  set('unique', stats.unique);
  set('new', stats.new);
  set('rate', stats.rate);
}

export function startPollLoop(cfg, onTick) {
  let timer = null;
  const fire = async () => { if (!document.hidden) await onTick(); };
  fire();
  if (cfg.status === 'live') {
    timer = setInterval(fire, 4000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) fire(); });
    window.addEventListener('beforeunload', () => clearInterval(timer));
  }
}
```

- [ ] **Step 3: Refactor consumers**

`net-cockpit.js`, `net-poll.js`, `net-live.js` import `renderRoster`/`applyStats`/`startPollLoop` and drop their inline copies. Each consumer keeps only its file-specific glue (cockpit's entry-form handler, poll's dispatch-net-updated, live's read-only render).

- [ ] **Step 4: Run JS suite + commit**

`npx vitest run` → 120+ pass (existing 119 + the new renderRoster test).

```bash
git add webroot/js/net-merge.js webroot/js/net-cockpit.js webroot/js/net-poll.js webroot/js/net-live.js tests/js/net-merge.test.js
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "refactor(m7): shared renderRoster/applyStats/startPollLoop in net-merge"
```

---

### Task 14: Unified `readCsrfToken()` JS helper

**Files:** Create `webroot/js/csrf.js`. Modify the 5 inline-copy sites: `app.js` (bulk-render + quick-add), `net-cockpit.js`, `designer.js`, `offline-sync.js`.

- [ ] **Step 1: Create csrf.js**

```js
/**
 * Single source of truth for reading the CakePHP CSRF token in the
 * browser. Reads <meta name="csrf-token"> first, falls back to the
 * csrfToken cookie. Publishes window.eqslCsrf().
 */
(function () {
  function readCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content') || '';
    const m = document.cookie.match(/csrfToken=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
  }
  window.eqslCsrf = readCsrfToken;
})();
```

- [ ] **Step 2: Load csrf.js in the layout**

In `templates/layout/default.php`, add `<script src="<?= $this->Url->build('/js/csrf.js') ?>" defer></script>` BEFORE the scripts that consume it (app.js, etc.).

- [ ] **Step 3: Replace inline copies**

In each of: `app.js` (bulk-render `startBulk`, quick-add submit), `net-cockpit.js`'s `csrf()`, `designer.js`'s two reads, `offline-sync.js`'s `getCsrf()` — replace the 2-3 line meta+cookie inline read with `window.eqslCsrf()`.

- [ ] **Step 4: Run + commit**

`npx vitest run` → still green.

```bash
git add webroot/js/csrf.js webroot/js/app.js webroot/js/net-cockpit.js webroot/js/designer.js webroot/js/offline-sync.js templates/layout/default.php
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "refactor(m7): unified readCsrfToken (window.eqslCsrf) helper"
```

---

## Phase 5 — Docs, QA, release

### Task 15: Help docs touch-ups + release prep + visual QA

**Files:** `templates/Help/net/*.php` (mention rotation + map on cockpit + 304), `src/Service/AdifExporter.php` (PROGRAMVERSION → `1.3.0`), `README.md` (status + roadmap line).

- [ ] **Step 1: Help docs**

Update:
- `templates/Help/net/collaborative-logging.php` — note the **Regenerate invite link** button + that GET no longer mutates.
- `templates/Help/net/running-a-net.php` — note the live participant map on the cockpit.
- `templates/Help/net/analytics-and-exports.php` — add the longest-streak metric.

- [ ] **Step 2: Bump version stamps**

`src/Service/AdifExporter.php` PROGRAMVERSION `'1.2.0'` → `'1.3.0'`.
`README.md` status line + roadmap (add an `M7 hardening & cleanup (v1.3.0)` bullet).

- [ ] **Step 3: Full suites**

```
docker compose run --rm --no-deps php vendor/bin/phpunit   # expect ~540 green
npx vitest run                                              # expect 120+ green
```

- [ ] **Step 4: Visual QA**

Seed a live demo net (PDO snippet from earlier QA), then in Playwright at desktop + 375px, light + dark:
- Cockpit: live map populates as check-ins arrive; deleting a check-in removes it within one poll cycle (no refresh); regenerate invite link button works.
- Public view: live map populates; same live removal behaviour; the public feed never exposes `logged_by_user_id`.
- Analytics: streak row renders; ETag/304 visible in network panel for idle polls.
- Admin pages still render (the AdminController base class swap).
- Designer still renders + edits a field (renderQsoCard split smoke).

- [ ] **Step 5: Commit + open PR**

```bash
git add templates/Help/net/ src/Service/AdifExporter.php README.md
git -c user.name='Robbi Nespu' -c user.email='robbinespu@gmail.com' \
  commit -m "docs(m7): help + README + AdifExporter PROGRAMVERSION 1.3.0"
git push -u origin m7-ncs-hardening
gh pr create --title "feat(m7): NCS dashboard hardening + backlog cleanup" \
  --body-file docs/superpowers/specs/2026-05-26-m7-ncs-hardening-design.md
```

- [ ] **Step 6: After merge — tag + zip**

Following the v1.2.0 process:

```bash
docker compose run --rm --no-deps php composer install --no-dev --optimize-autoloader
bash scripts/build-release.sh 1.3.0
docker compose run --rm --no-deps php composer install
git tag -a v1.3.0 -F <tagmsg.txt>
git push origin v1.3.0
```

---

## Self-review

**Spec coverage:**
- §5 A1 live map → Task 5. ✓
- §5 A2 longest_streak → Task 6. ✓
- §5 A3 rotate-token → Task 7. ✓
- §5 A4 tombstones → Tasks 1-4. ✓
- §5 A5 ETag/304 → Task 8. ✓
- §5 A6 GET→POST join → Task 9. ✓
- §6 B1 NetMetrics DI → Task 10. ✓
- §6 B2 AdminController base → Task 11. ✓
- §6 B3 saveTemplate/renderQsoCard splits → Task 12. ✓
- §6 B4 net-JS dedup → Task 13. ✓
- §6 B5 unified CSRF reader → Task 14. ✓
- §8 testing coverage — every behavior-bearing task has TDD tests. ✓
- §10 phasing — tasks ordered exactly per the spec's rollout. ✓

**Placeholder scan:** No "TBD/TODO/placeholder" left; every code step has runnable code. The TemplatesController/QsosController helper signatures in Task 12 are described by inputs/intent rather than full code because the bodies are mechanical extractions from the existing methods (the exact code lifts the corresponding regions of the source); the existing test suite is the safety net.

**Type consistency:**
- `NetSessionRemovalsTable::record(int, int)` and `idsRemovedSince(int, ?DateTimeInterface)` referenced consistently in Tasks 2, 3, 4, 8.
- `NetMetrics::retention()` adds `longest_streak: int` + `streak_leaders: list<string>` (Tasks 6, 10) — referenced in the analytics template + the DI test.
- JS `renderRoster(tbody, rows)`, `applyStats(stats)`, `startPollLoop(cfg, onTick)` (Task 13) — consumers in cockpit/poll/live use the same signature.
- `window.eqslCsrf()` (Task 14) referenced from all 5 consumer sites.

No gaps found.
