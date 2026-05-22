# NCS Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Net Control Station dashboard — net sessions with a live collaborative cockpit, a public read-only live view, signal/map/retention analytics, and PDF + ADIF export — on top of the existing net-QSO model.

**Architecture:** A new `net_sessions` entity mirrors the proven `activations` session pattern; each check-in is a `qso_type='net'` QSO linked by a new `qsos.net_session_id` FK. Real-time is short polling against a delta JSON endpoint (`?since=` cursor), reusing the app's existing polling idiom — no SSE/WebSocket. Analytics are SQL aggregations in a `NetMetrics` service; exports reuse `AdifExporter` (via a small adapter) and add `dompdf` for the PDF report.

**Tech Stack:** PHP 8.1, CakePHP 5, MariaDB, Phinx migrations, PHPUnit, Vitest, vanilla JS (no framework), Leaflet (vendored) for the map, dompdf for PDF.

**Spec:** `docs/superpowers/specs/2026-05-22-ncs-dashboard-design.md`

---

## Assumptions (finalized unattended; revisit on revamp)

- **Co-loggers** are registered users; added by the owner or via a per-session invite link (`/net-sessions/join/{logger_token}`). No anonymous logging.
- **Public view** is open (no password) when `is_public=1`; per-club private password gate is deferred (spec §17).
- **Charts** are dependency-free inline SVG (signal distribution). Only the map pulls a library (Leaflet, vendored like fabric.js).
- **JSON helper:** add a `protected function jsonResponse(array): Response` to `AppController` for the new controllers (QsosController keeps its own private copy — not refactored, YAGNI).
- **ADIF reuse:** pass an adapter object exposing `code`/`name`/`grid_square`/`started_at`/`ended_at` to the existing `AdifExporter::export()` rather than modifying it.
- **Commits:** solo authorship `Robbi Nespu <robbinespu@gmail.com>`, no Co-Authored-By trailer (project convention). Branch: `m6-ncs-dashboard`.
- **Each phase is a coherent, mergeable slice.** Phases can be split into separate PRs if preferred.

## Running tests (this project)

- PHP: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter <Name>`
- JS: `npm test` (Vitest) or `npx vitest run tests/js/<file>`
- CSS rebuild after theme.css edits: `npm run build:css`
- Migrations: `docker compose run --rm --no-deps php bin/cake migrations migrate`

---

## File Structure

**Migrations** (`config/Migrations/`)
- `20260522000001_CreateNetSessions.php` — `net_sessions` table.
- `20260522000002_CreateNetSessionLoggers.php` — `net_session_loggers` join table.
- `20260522000003_AddNetSessionFieldsToQsos.php` — `qsos.net_session_id`, `logged_by_user_id`, `net_role`.

**Model**
- `src/Model/Entity/NetSession.php` — entity + mass-assignment lockdown.
- `src/Model/Table/NetSessionsTable.php` — validation, finders, `isLogger()`.
- `src/Model/Entity/NetSessionLogger.php`, `src/Model/Table/NetSessionLoggersTable.php`.
- `src/Model/Entity/Qso.php` (modify) — make `net_session_id`, `logged_by_user_id`, `net_role` accessible.

**Services**
- `src/Service/SignalReport.php` — RST → strength digit / bucket.
- `src/Service/NetMetrics.php` — session stats, signal distribution, map points, retention.
- `src/Service/NetReportPdf.php` — HTML→PDF via dompdf.
- `src/Service/NetAdifAdapter.php` — adapts a NetSession to the shape `AdifExporter` expects.

**Controllers**
- `src/Controller/AppController.php` (modify) — add `jsonResponse()`.
- `src/Controller/NetSessionsController.php` — owner/co-logger surface (CRUD, lifecycle, cockpit, check-in JSON CRUD + delta, analytics, exports, logger mgmt).
- `src/Controller/NetController.php` — public read-only view + public delta feed.

**Views**
- `templates/NetSessions/index.php`, `add.php`, `edit.php`, `cockpit.php`, `analytics.php`
- `templates/Net/live.php`
- `templates/element/net/entry_bar.php`, `roster.php`, `stat_tiles.php`, `signal_chart.php`, `map.php`
- `templates/pdf/net_report.php` (dompdf HTML)

**JS** (`webroot/js/`)
- `net-cockpit.js`, `net-live.js`, `net-charts.js`, `net-map.js`
- vendored `webroot/js/vendor/leaflet/` (+ css)

**Routes/Config**
- `config/routes.php` (modify), `composer.json` (add `dompdf/dompdf`), `src/Service/HelpCatalog.php` (modify), `templates/Help/...` (new).

**Tests**
- `tests/TestCase/Model/Table/NetSessionsTableTest.php`
- `tests/TestCase/Service/SignalReportTest.php`, `NetMetricsTest.php`, `NetReportPdfTest.php`
- `tests/TestCase/Controller/NetSessionsControllerTest.php`, `NetControllerTest.php`
- `tests/Fixture/NetSessionsFixture.php`, `NetSessionLoggersFixture.php` (+ existing Qsos/Users fixtures)
- `tests/js/net-merge.test.js`, `tests/js/net-charts.test.js`

---

# Phase 1 — Data model, NetSession CRUD + lifecycle

### Task 1: Migration — `net_sessions` table

**Files:**
- Create: `config/Migrations/20260522000001_CreateNetSessions.php`

- [ ] **Step 1: Write the migration**

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M6 — `net_sessions` table. A first-class net (NCS) session that groups
 * its check-ins via qsos.net_session_id. Mirrors the activations pattern.
 */
final class CreateNetSessions extends AbstractMigration
{
    public function up(): void
    {
        $this->table('net_sessions')
            ->addColumn('owner_id', 'integer', ['null' => false])
            ->addColumn('net_title', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('net_organisation', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('frequency_mhz', 'decimal', ['precision' => 10, 'scale' => 5, 'null' => true])
            ->addColumn('band', 'string', ['limit' => 8, 'null' => true])
            ->addColumn('mode', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 12, 'null' => false, 'default' => 'scheduled'])
            ->addColumn('public_slug', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('is_public', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('logger_token', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('ended_at', 'datetime', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['owner_id', 'status'])
            ->addIndex(['public_slug'], ['unique' => true])
            ->addIndex(['owner_id', 'net_title'])
            ->create();
    }

    public function down(): void
    {
        $this->table('net_sessions')->drop()->save();
    }
}
```

- [ ] **Step 2: Run the migration**

Run: `docker compose run --rm --no-deps php bin/cake migrations migrate`
Expected: `== CreateNetSessions: migrated`

- [ ] **Step 3: Verify schema**

Run: `docker compose exec -T db sh -c 'mariadb -ueqsl -peqsl eqsl -e "DESCRIBE net_sessions;"'`
Expected: all columns above present.

- [ ] **Step 4: Commit**

```bash
git add config/Migrations/20260522000001_CreateNetSessions.php
git commit -m "feat(net): net_sessions table migration"
```

---

### Task 2: Migration — `net_session_loggers` join table

**Files:**
- Create: `config/Migrations/20260522000002_CreateNetSessionLoggers.php`

- [ ] **Step 1: Write the migration**

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/** M6 — co-logger membership for a net session. */
final class CreateNetSessionLoggers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('net_session_loggers')
            ->addColumn('net_session_id', 'integer', ['null' => false])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('added_via', 'string', ['limit' => 10, 'null' => false, 'default' => 'owner'])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['net_session_id', 'user_id'], ['unique' => true])
            ->create();
    }

    public function down(): void
    {
        $this->table('net_session_loggers')->drop()->save();
    }
}
```

- [ ] **Step 2: Migrate + verify**

Run: `docker compose run --rm --no-deps php bin/cake migrations migrate`
Run: `docker compose exec -T db sh -c 'mariadb -ueqsl -peqsl eqsl -e "DESCRIBE net_session_loggers;"'`
Expected: 5 columns, unique index on (net_session_id, user_id).

- [ ] **Step 3: Commit**

```bash
git add config/Migrations/20260522000002_CreateNetSessionLoggers.php
git commit -m "feat(net): net_session_loggers join table migration"
```

---

### Task 3: Migration — net fields on `qsos`

**Files:**
- Create: `config/Migrations/20260522000003_AddNetSessionFieldsToQsos.php`

- [ ] **Step 1: Write the migration**

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M6 — link check-ins to a net session and record who entered each row
 * + the participant's role. All nullable; null for non-net QSOs.
 * net_session_id has ON DELETE SET NULL so deleting a session never
 * destroys the contacts (same policy as activation_id).
 */
final class AddNetSessionFieldsToQsos extends AbstractMigration
{
    public function up(): void
    {
        $this->table('qsos')
            ->addColumn('net_session_id', 'integer', ['null' => true, 'after' => 'activation_id'])
            ->addColumn('logged_by_user_id', 'integer', ['null' => true, 'after' => 'net_session_id'])
            ->addColumn('net_role', 'string', ['limit' => 12, 'null' => true, 'after' => 'logged_by_user_id'])
            ->addIndex(['net_session_id'])
            ->update();
    }

    public function down(): void
    {
        $this->table('qsos')
            ->removeColumn('net_session_id')
            ->removeColumn('logged_by_user_id')
            ->removeColumn('net_role')
            ->update();
    }
}
```

- [ ] **Step 2: Migrate + verify**

Run: `docker compose run --rm --no-deps php bin/cake migrations migrate`
Run: `docker compose exec -T db sh -c 'mariadb -ueqsl -peqsl eqsl -e "SHOW COLUMNS FROM qsos LIKE \"net_%\";"'`
Expected: `net_session_id`, `net_role` rows (and `logged_by_user_id` via a second check).

- [ ] **Step 3: Commit**

```bash
git add config/Migrations/20260522000003_AddNetSessionFieldsToQsos.php
git commit -m "feat(net): add net_session_id/logged_by_user_id/net_role to qsos"
```

---

### Task 4: NetSession entity

**Files:**
- Create: `src/Model/Entity/NetSession.php`

- [ ] **Step 1: Write the entity**

```php
<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * M6 — Net session. owner_id, status, slugs, started/ended are
 * server-controlled and locked out of mass assignment.
 *
 * @property int $id
 * @property int $owner_id
 * @property string $net_title
 * @property string|null $net_organisation
 * @property string|null $frequency_mhz
 * @property string|null $band
 * @property string|null $mode
 * @property string $status
 * @property string $public_slug
 * @property bool $is_public
 * @property string|null $logger_token
 * @property \Cake\I18n\DateTime|null $started_at
 * @property \Cake\I18n\DateTime|null $ended_at
 * @property string|null $notes
 * @property \Cake\I18n\DateTime $created_at
 * @property \Cake\I18n\DateTime $updated_at
 */
class NetSession extends Entity
{
    protected array $_accessible = [
        'net_title'        => true,
        'net_organisation' => true,
        'frequency_mhz'    => true,
        'band'             => true,
        'mode'             => true,
        'is_public'        => true,
        'notes'            => true,
    ];

    public function isLive(): bool
    {
        return $this->status === 'live';
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Model/Entity/NetSession.php
git commit -m "feat(net): NetSession entity"
```

---

### Task 5: NetSessionsTable — validation + finders + isLogger

**Files:**
- Create: `src/Model/Table/NetSessionsTable.php`
- Test: `tests/TestCase/Model/Table/NetSessionsTableTest.php`
- Fixture: `tests/Fixture/NetSessionsFixture.php`, `tests/Fixture/NetSessionLoggersFixture.php`

- [ ] **Step 1: Write the fixtures**

```php
<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class NetSessionsFixture extends TestFixture
{
    public array $records = [
        [
            'id' => 1, 'owner_id' => 1, 'net_title' => 'MARTS Daily Net',
            'net_organisation' => 'MARTS', 'frequency_mhz' => '7.110', 'band' => '40m',
            'mode' => 'SSB', 'status' => 'live', 'public_slug' => 'live-net-slug',
            'is_public' => true, 'logger_token' => 'logtok1',
            'started_at' => '2026-05-22 12:00:00', 'ended_at' => null, 'notes' => null,
            'created_at' => '2026-05-22 11:59:00', 'updated_at' => '2026-05-22 12:00:00',
        ],
        [
            'id' => 2, 'owner_id' => 1, 'net_title' => 'MARTS Daily Net',
            'net_organisation' => 'MARTS', 'frequency_mhz' => '7.110', 'band' => '40m',
            'mode' => 'SSB', 'status' => 'ended', 'public_slug' => 'ended-net-slug',
            'is_public' => true, 'logger_token' => null,
            'started_at' => '2026-05-21 12:00:00', 'ended_at' => '2026-05-21 13:00:00',
            'notes' => null, 'created_at' => '2026-05-21 11:59:00', 'updated_at' => '2026-05-21 13:00:00',
        ],
    ];
}
```

```php
<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class NetSessionLoggersFixture extends TestFixture
{
    public array $records = [
        ['id' => 1, 'net_session_id' => 1, 'user_id' => 2, 'added_via' => 'owner', 'created_at' => '2026-05-22 12:01:00'],
    ];
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class NetSessionsTableTest extends TestCase
{
    protected array $fixtures = ['app.NetSessions', 'app.NetSessionLoggers', 'app.Users'];

    private function table(): \App\Model\Table\NetSessionsTable
    {
        return TableRegistry::getTableLocator()->get('NetSessions');
    }

    public function testTitleRequired(): void
    {
        $t = $this->table();
        $e = $t->newEntity(['net_title' => '']);
        $this->assertNotEmpty($e->getError('net_title'));
    }

    public function testOwnerIsLogger(): void
    {
        // Owner (user 1) is implicitly a logger of session 1.
        $this->assertTrue($this->table()->isLogger(1, 1));
    }

    public function testCoLoggerIsLogger(): void
    {
        // User 2 is in net_session_loggers for session 1.
        $this->assertTrue($this->table()->isLogger(1, 2));
    }

    public function testStrangerIsNotLogger(): void
    {
        $this->assertFalse($this->table()->isLogger(1, 999));
    }

    public function testFindUpcomingReturnsScheduledOnly(): void
    {
        // No scheduled rows in fixtures → empty.
        $rows = $this->table()->findUpcomingForUser(1)->all()->toList();
        $this->assertSame([], $rows);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetSessionsTableTest`
Expected: FAIL — `NetSessionsTable` not found.

- [ ] **Step 4: Write the table**

```php
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * M6 — Net sessions ORM. Schema: migration 20260522000001.
 */
class NetSessionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('net_sessions');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => ['Model.beforeSave' => [
                'created_at' => 'new',
                'updated_at' => 'always',
            ]],
        ]);
        $this->belongsTo('Owners', ['className' => 'Users', 'foreignKey' => 'owner_id']);
        $this->hasMany('Qsos', ['foreignKey' => 'net_session_id']);
        $this->hasMany('NetSessionLoggers', ['foreignKey' => 'net_session_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('net_title')->maxLength('net_title', 120)
            ->notEmptyString('net_title', 'Net title is required.')
            ->scalar('net_organisation')->maxLength('net_organisation', 120)
            ->allowEmptyString('net_organisation')
            ->numeric('frequency_mhz')->allowEmptyString('frequency_mhz')
            ->scalar('band')->maxLength('band', 8)->allowEmptyString('band')
            ->scalar('mode')->maxLength('mode', 20)->allowEmptyString('mode')
            ->boolean('is_public')
            ->scalar('notes')->allowEmptyString('notes');
        return $validator;
    }

    /** Owner OR co-logger may write to the session. */
    public function isLogger(int $sessionId, int $userId): bool
    {
        $isOwner = $this->exists(['id' => $sessionId, 'owner_id' => $userId]);
        if ($isOwner) {
            return true;
        }
        return $this->NetSessionLoggers->exists([
            'net_session_id' => $sessionId,
            'user_id' => $userId,
        ]);
    }

    public function findUpcomingForUser(int $userId): SelectQuery
    {
        return $this->find()
            ->where(['owner_id' => $userId, 'status' => 'scheduled'])
            ->orderBy(['created_at' => 'DESC']);
    }

    public function findLiveForUser(int $userId): SelectQuery
    {
        return $this->find()
            ->where(['owner_id' => $userId, 'status' => 'live'])
            ->orderBy(['started_at' => 'DESC']);
    }

    public function findRecentForUser(int $userId, int $limit = 50): SelectQuery
    {
        return $this->find()
            ->where(['owner_id' => $userId, 'status' => 'ended'])
            ->orderBy(['ended_at' => 'DESC'])
            ->limit($limit);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetSessionsTableTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Model/Table/NetSessionsTable.php tests/TestCase/Model/Table/NetSessionsTableTest.php tests/Fixture/NetSessionsFixture.php tests/Fixture/NetSessionLoggersFixture.php
git commit -m "feat(net): NetSessionsTable with validation, finders, isLogger + tests"
```

---

### Task 6: NetSessionLogger entity + table

**Files:**
- Create: `src/Model/Entity/NetSessionLogger.php`, `src/Model/Table/NetSessionLoggersTable.php`

- [ ] **Step 1: Write the entity**

```php
<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $net_session_id
 * @property int $user_id
 * @property string $added_via
 * @property \Cake\I18n\DateTime $created_at
 */
class NetSessionLogger extends Entity
{
    protected array $_accessible = [
        'net_session_id' => true,
        'user_id'        => true,
        'added_via'      => true,
    ];
}
```

- [ ] **Step 2: Write the table**

```php
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class NetSessionLoggersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('net_session_loggers');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => ['Model.beforeSave' => ['created_at' => 'new']],
        ]);
        $this->belongsTo('NetSessions', ['foreignKey' => 'net_session_id']);
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Model/Entity/NetSessionLogger.php src/Model/Table/NetSessionLoggersTable.php
git commit -m "feat(net): NetSessionLogger entity + table"
```

---

### Task 7: Make new q.so columns accessible

**Files:**
- Modify: `src/Model/Entity/Qso.php`

- [ ] **Step 1: Add the three keys to `$_accessible`**

In `src/Model/Entity/Qso.php`, inside the `$_accessible` array (next to `activation_id` if present, else with the net block), add:

```php
        'net_session_id'    => true,
        'logged_by_user_id' => true,
        'net_role'          => true,
```

- [ ] **Step 2: Sanity check no syntax error**

Run: `docker compose run --rm --no-deps php -l src/Model/Entity/Qso.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add src/Model/Entity/Qso.php
git commit -m "feat(net): make net_session_id/logged_by_user_id/net_role mass-assignable on Qso"
```

---

### Task 8: AppController JSON helper

**Files:**
- Modify: `src/Controller/AppController.php`

- [ ] **Step 1: Add a protected helper**

Add this method to `AppController`:

```php
    /**
     * Render an array as a JSON response. Shared by the net JSON feeds.
     */
    protected function jsonResponse(array $payload, int $status = 200): \Cake\Http\Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
```

- [ ] **Step 2: Lint**

Run: `docker compose run --rm --no-deps php -l src/Controller/AppController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add src/Controller/AppController.php
git commit -m "feat: shared jsonResponse helper on AppController"
```

---

### Task 9: NetSessionsController — index/add/edit/start/end + delete

**Files:**
- Create: `src/Controller/NetSessionsController.php`
- Create views: `templates/NetSessions/index.php`, `add.php`, `edit.php`
- Test: `tests/TestCase/Controller/NetSessionsControllerTest.php`
- Modify: `config/routes.php`

- [ ] **Step 1: Add routes**

In `config/routes.php`, after the activations block, add:

```php
        // M6 — NCS dashboard (owner/co-logger; auth enforced in controller).
        $builder->connect('/net-sessions', ['controller' => 'NetSessions', 'action' => 'index']);
        $builder->connect('/net-sessions/new', ['controller' => 'NetSessions', 'action' => 'add']);
        $builder->connect('/net-sessions/{id}/edit', ['controller' => 'NetSessions', 'action' => 'edit'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->connect('/net-sessions/{id}/start', ['controller' => 'NetSessions', 'action' => 'start'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->connect('/net-sessions/{id}/end', ['controller' => 'NetSessions', 'action' => 'end'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->connect('/net-sessions/{id}/delete', ['controller' => 'NetSessions', 'action' => 'delete'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->connect('/net-sessions/{id}', ['controller' => 'NetSessions', 'action' => 'view'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
```

- [ ] **Step 2: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class NetSessionsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.NetSessions', 'app.NetSessionLoggers', 'app.Qsos'];

    private function loginAsOwner(): void
    {
        $this->session(['Auth' => ['id' => 1]]);
    }

    public function testIndexRequiresAuth(): void
    {
        $this->get('/net-sessions');
        $this->assertResponseCode(302); // bounce to /login
    }

    public function testIndexListsForOwner(): void
    {
        $this->loginAsOwner();
        $this->get('/net-sessions');
        $this->assertResponseOk();
        $this->assertResponseContains('MARTS Daily Net');
    }

    public function testCreateSchedulesSession(): void
    {
        $this->loginAsOwner();
        $this->enableCsrfToken();
        $this->post('/net-sessions', [
            'net_title' => 'Test Net', 'net_organisation' => 'TestOrg',
            'frequency_mhz' => '14.300', 'band' => '20m', 'mode' => 'SSB',
        ]);
        $this->assertResponseSuccess();
        $sessions = $this->getTableLocator()->get('NetSessions');
        $row = $sessions->find()->where(['net_title' => 'Test Net'])->first();
        $this->assertNotNull($row);
        $this->assertSame('scheduled', $row->status);
        $this->assertNotEmpty($row->public_slug);
        $this->assertSame(1, $row->owner_id);
    }

    public function testStartTransitionsToLive(): void
    {
        $this->loginAsOwner();
        $this->enableCsrfToken();
        // session 2 is ended; create a scheduled one to start. Use session created above pattern:
        $sessions = $this->getTableLocator()->get('NetSessions');
        $s = $sessions->newEntity(['net_title' => 'Sched']);
        $s->set('owner_id', 1, ['guard' => false]);
        $s->set('status', 'scheduled', ['guard' => false]);
        $s->set('public_slug', 'sched-slug', ['guard' => false]);
        $sessions->saveOrFail($s);
        $this->post("/net-sessions/{$s->id}/start");
        $this->assertResponseSuccess();
        $reloaded = $sessions->get($s->id);
        $this->assertSame('live', $reloaded->status);
        $this->assertNotNull($reloaded->started_at);
    }

    public function testStrangerCannotStart(): void
    {
        $this->session(['Auth' => ['id' => 999]]);
        $this->enableCsrfToken();
        $this->post('/net-sessions/1/start');
        $this->assertResponseCode(404);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetSessionsControllerTest`
Expected: FAIL — controller missing.

- [ ] **Step 4: Write the controller (CRUD + lifecycle portion)**

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;
use Cake\Utility\Security;

/**
 * M6 — NCS dashboard owner/co-logger surface. Every action is scoped:
 * the owner controls lifecycle; owner + co-loggers may log check-ins.
 */
class NetSessionsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    private function ownedOrFail(int $id): \App\Model\Entity\NetSession
    {
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $row = $this->fetchTable('NetSessions')->find()
            ->where(['id' => $id, 'owner_id' => $uid])->first();
        if ($row === null) {
            throw new NotFoundException('Net session not found.');
        }
        return $row;
    }

    public function index(): void
    {
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('NetSessions');
        $this->set([
            'live'     => $tbl->findLiveForUser($uid)->all(),
            'upcoming' => $tbl->findUpcomingForUser($uid)->all(),
            'recent'   => $tbl->findRecentForUser($uid, 50)->all(),
            'newSession' => $tbl->newEmptyEntity(),
            'title'    => 'Net sessions',
        ]);
    }

    public function add(): ?\Cake\Http\Response
    {
        $tbl = $this->fetchTable('NetSessions');
        $session = $tbl->newEmptyEntity();
        if ($this->request->is('post')) {
            $session = $tbl->patchEntity($session, $this->request->getData());
            $uid = $this->Authentication->getIdentity()->getIdentifier();
            $session->set('owner_id', $uid, ['guard' => false]);
            $session->set('status', 'scheduled', ['guard' => false]);
            $session->set('public_slug', $this->uniqueSlug(), ['guard' => false]);
            $session->set('logger_token', strtolower(Security::randomString(20)), ['guard' => false]);
            if ($tbl->save($session)) {
                $this->Flash->success('Net session created.');
                return $this->redirect(['action' => 'view', $session->id]);
            }
            $this->Flash->error('Could not create the net session.');
        }
        $this->set(['session' => $session, 'title' => 'New net session']);
    }

    public function edit(int $id): ?\Cake\Http\Response
    {
        $session = $this->ownedOrFail($id);
        if ($this->request->is(['post', 'put'])) {
            $session = $this->fetchTable('NetSessions')->patchEntity($session, $this->request->getData());
            if ($this->fetchTable('NetSessions')->save($session)) {
                $this->Flash->success('Net session updated.');
                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error('Could not update the net session.');
        }
        $this->set(['session' => $session, 'title' => 'Edit net session']);
    }

    public function start(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $session->set('status', 'live', ['guard' => false]);
        $session->set('started_at', DateTime::now(), ['guard' => false]);
        $this->fetchTable('NetSessions')->saveOrFail($session);
        $this->Flash->success('Net is live.');
        return $this->redirect(['action' => 'cockpit', $id]);
    }

    public function end(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $session->set('status', 'ended', ['guard' => false]);
        $session->set('ended_at', DateTime::now(), ['guard' => false]);
        $this->fetchTable('NetSessions')->saveOrFail($session);
        $this->Flash->success('Net ended.');
        return $this->redirect(['action' => 'view', $id]);
    }

    public function delete(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $session = $this->ownedOrFail($id);
        $this->fetchTable('NetSessions')->deleteOrFail($session);
        $this->Flash->success('Net session deleted.');
        return $this->redirect(['action' => 'index']);
    }

    public function view(int $id): void
    {
        $session = $this->ownedOrFail($id);
        $this->set(['session' => $session, 'title' => $session->net_title]);
    }

    private function uniqueSlug(): string
    {
        $tbl = $this->fetchTable('NetSessions');
        do {
            $slug = strtolower(Security::randomString(16));
        } while ($tbl->exists(['public_slug' => $slug]));
        return $slug;
    }
}
```

> `view.php` can be a thin placeholder for now (title + links to cockpit/analytics/exports); the cockpit (Task 11) is the real surface. Create a minimal `templates/NetSessions/view.php` rendering `$session->net_title`, status, and action links.

- [ ] **Step 5: Write minimal views**

Create `templates/NetSessions/index.php`, `add.php`, `edit.php`, `view.php` following the Activations templates (`templates/Activations/index.php` is the reference). Index lists Live / Upcoming / Recent with start/cockpit/end/export links. `add.php`/`edit.php` are simple `$this->Form` forms over net_title, net_organisation, frequency_mhz, band, mode, is_public, notes. Each must `$this->assign('title', $title)` and use `.form-control` classes (match `templates/Auth/login.php`).

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetSessionsControllerTest`
Expected: PASS (5 tests).

- [ ] **Step 7: Commit**

```bash
git add src/Controller/NetSessionsController.php templates/NetSessions/ config/routes.php tests/TestCase/Controller/NetSessionsControllerTest.php
git commit -m "feat(net): NetSessions CRUD + lifecycle (scheduled/live/ended) + tests"
```

---

# Phase 2 — Check-in logging (QSO rows) + cockpit entry & roster (no realtime yet)

### Task 10: Check-in write/edit/delete JSON actions

**Files:**
- Modify: `src/Controller/NetSessionsController.php`
- Modify: `config/routes.php`
- Modify: `tests/TestCase/Controller/NetSessionsControllerTest.php`

- [ ] **Step 1: Add routes**

```php
        $builder->connect('/net-sessions/{id}/cockpit', ['controller' => 'NetSessions', 'action' => 'cockpit'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->connect('/net-sessions/{id}/checkins', ['controller' => 'NetSessions', 'action' => 'checkins'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->connect('/net-sessions/{id}/checkins/{qsoId}', ['controller' => 'NetSessions', 'action' => 'checkin'])
            ->setPass(['id', 'qsoId'])->setPatterns(['id' => '\d+', 'qsoId' => '\d+']);
```

> CakePHP routes both `.json` extension and plain; the controller branches by HTTP method. `checkins` handles GET (delta feed, Task 13) + POST (create). `checkin` handles PUT + DELETE.

- [ ] **Step 2: Write the failing test**

Add to `NetSessionsControllerTest`:

```php
    public function testCoLoggerCanLogCheckin(): void
    {
        $this->session(['Auth' => ['id' => 2]]); // co-logger of session 1
        $this->enableCsrfToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/net-sessions/1/checkins', [
            'call_worked' => '9W2ABC', 'operator_name' => 'Abu',
            'grid_square' => 'OJ02', 'rst_received' => '59', 'net_role' => 'Check-in',
        ]);
        $this->assertResponseOk();
        $qsos = $this->getTableLocator()->get('Qsos');
        $row = $qsos->find()->where(['call_worked' => '9W2ABC', 'net_session_id' => 1])->first();
        $this->assertNotNull($row);
        $this->assertSame('net', $row->qso_type);
        $this->assertSame(1, $row->user_id);          // owner owns the QSO
        $this->assertSame(2, $row->logged_by_user_id); // co-logger entered it
    }

    public function testStrangerCannotLogCheckin(): void
    {
        $this->session(['Auth' => ['id' => 999]]);
        $this->enableCsrfToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/net-sessions/1/checkins', ['call_worked' => 'X1X']);
        $this->assertResponseCode(404);
    }
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetSessionsControllerTest`
Expected: FAIL — `checkins` action missing.

- [ ] **Step 4: Implement `checkins` (POST branch) + `checkin` (PUT/DELETE)**

Add to `NetSessionsController`:

```php
    private function loggerSessionOrFail(int $id): \App\Model\Entity\NetSession
    {
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $tbl = $this->fetchTable('NetSessions');
        if (!$tbl->isLogger($id, $uid)) {
            throw new NotFoundException('Net session not found.');
        }
        return $tbl->get($id);
    }

    public function checkins(int $id): \Cake\Http\Response
    {
        if ($this->request->is('post')) {
            $session = $this->loggerSessionOrFail($id);
            $uid = $this->Authentication->getIdentity()->getIdentifier();
            $qsos = $this->fetchTable('Qsos');
            $qso = $qsos->newEntity($this->request->getData());
            // Server-controlled net stamping:
            $qso->set('user_id', $session->owner_id, ['guard' => false]);
            $qso->set('logged_by_user_id', $uid, ['guard' => false]);
            $qso->set('net_session_id', $session->id, ['guard' => false]);
            $qso->set('qso_type', 'net', ['guard' => false]);
            $qso->set('ncs_callsign', $this->ncsCallsignFor($session), ['guard' => false]);
            $qso->set('net_title', $session->net_title, ['guard' => false]);
            $qso->set('net_organisation', $session->net_organisation, ['guard' => false]);
            $qso->set('band', $session->band, ['guard' => false]);
            $qso->set('frequency_mhz', $session->frequency_mhz, ['guard' => false]);
            $qso->set('mode', $session->mode, ['guard' => false]);
            $qso->set('qso_datetime_utc', DateTime::now(), ['guard' => false]);
            if (!$qsos->save($qso)) {
                return $this->jsonResponse(['ok' => false, 'errors' => $qso->getErrors()], 422);
            }
            return $this->jsonResponse(['ok' => true, 'checkin' => $this->presentCheckin($qso)]);
        }
        // GET → delta feed (implemented in Task 13).
        return $this->checkinsFeed($id);
    }

    public function checkin(int $id, int $qsoId): \Cake\Http\Response
    {
        $this->loggerSessionOrFail($id);
        $qsos = $this->fetchTable('Qsos');
        $qso = $qsos->find()->where(['id' => $qsoId, 'net_session_id' => $id])->first();
        if ($qso === null) {
            throw new NotFoundException('Check-in not found.');
        }
        if ($this->request->is('delete')) {
            $qsos->deleteOrFail($qso);
            return $this->jsonResponse(['ok' => true, 'removed' => $qsoId]);
        }
        // PUT — edit allowed fields
        $qso = $qsos->patchEntity($qso, $this->request->getData(), [
            'fields' => ['call_worked', 'operator_name', 'grid_square', 'rst_received', 'rst_sent', 'net_role', 'notes'],
        ]);
        if (!$qsos->save($qso)) {
            return $this->jsonResponse(['ok' => false, 'errors' => $qso->getErrors()], 422);
        }
        return $this->jsonResponse(['ok' => true, 'checkin' => $this->presentCheckin($qso)]);
    }

    private function ncsCallsignFor(\App\Model\Entity\NetSession $s): string
    {
        $owner = $this->fetchTable('Users')->get($s->owner_id);
        return (string)$owner->callsign;
    }

    /** Public-safe + logger view share this shape; $includePrivate adds logged_by. */
    private function presentCheckin(\App\Model\Entity\Qso $q, bool $includePrivate = true): array
    {
        $row = [
            'id'        => $q->id,
            'callsign'  => $q->call_worked,
            'name'      => $q->operator_name,
            'grid'      => $q->grid_square,
            'signal'    => \App\Service\SignalReport::strength($q->rst_received),
            'rst'       => $q->rst_received,
            'role'      => $q->net_role,
            'at'        => $q->qso_datetime_utc?->format('c'),
            'updated'   => $q->updated_at?->format('c'),
        ];
        if ($includePrivate) {
            $row['logged_by_user_id'] = $q->logged_by_user_id;
        }
        return $row;
    }
```

> `SignalReport::strength()` is built in Task 14; this task depends on it. **Build Task 14 before running this test, or stub `SignalReport::strength` to `return null;` first.** To keep TDD ordering clean, do Task 14 (SignalReport) immediately before Step 5 here.

- [ ] **Step 5: Run test to verify it passes** (after SignalReport exists)

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetSessionsControllerTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Controller/NetSessionsController.php config/routes.php tests/TestCase/Controller/NetSessionsControllerTest.php
git commit -m "feat(net): check-in write/edit/delete JSON actions with logger authz + tests"
```

---

### Task 11: Cockpit view + entry bar + roster elements (server-rendered shell)

**Files:**
- Create: `templates/NetSessions/cockpit.php`
- Create: `templates/element/net/entry_bar.php`, `roster.php`, `stat_tiles.php`
- Modify: `src/Controller/NetSessionsController.php` (add `cockpit` action)

- [ ] **Step 1: Add the `cockpit` action**

```php
    public function cockpit(int $id): void
    {
        $session = $this->loggerSessionOrFail($id);
        $qsos = $this->fetchTable('Qsos')->find()
            ->where(['net_session_id' => $id])
            ->orderBy(['qso_datetime_utc' => 'DESC', 'id' => 'DESC'])
            ->all();
        $this->set([
            'session' => $session,
            'checkins' => $qsos,
            'title' => $session->net_title . ' — cockpit',
        ]);
    }
```

- [ ] **Step 2: Build the cockpit template**

Create `templates/NetSessions/cockpit.php` matching the mockup at `.superpowers/brainstorm/833013-1779400967/content/cockpit-layout.html`:
- top bar (LIVE badge, title/org/freq/band/mode, elapsed timer `<span data-net-elapsed data-started="...">`, Public link button copying `/net/{public_slug}`, End form posting to `/net-sessions/{id}/end`),
- `<?= $this->element('net/entry_bar', ['session' => $session]) ?>`,
- `<?= $this->element('net/roster', ['checkins' => $checkins]) ?>`,
- `<?= $this->element('net/stat_tiles') ?>`,
- a `<script>` setting `window.NET = { id, feedUrl: '/net-sessions/<id>/checkins', postUrl: '/net-sessions/<id>/checkins', status: '<status>' }` then `<script src="/js/net-cockpit.js" defer></script>` (+ net-charts.js, net-map.js added in Phase 5).

`entry_bar.php`: a `<form data-net-entry>` with inputs `callsign` (uppercase), `name`, `grid`, `rst` (default 59), `role` (`<select>`: NCS/Relay/Check-in/Traffic), and a `+ Log` button. Mark `data-*` hooks the JS reads.

`roster.php`: a `<table data-net-roster>` with a `<tbody>` server-rendered from `$checkins` (so it works without JS), each `<tr data-checkin-id="{id}">` carrying `#`, callsign, name, grid, signal (Sx), role, "by". Newest first.

`stat_tiles.php`: four `<div data-stat="checkins|unique|new|rate">` tiles + placeholders `<div data-signal-chart>` and `<div data-net-map>` (filled in Phase 5).

- [ ] **Step 3: Manual smoke test**

Run: `docker compose up -d` then visit `http://localhost:8090/net-sessions` → create a net → Start → confirm the cockpit renders the entry bar + (empty) roster with no JS errors (DevTools console clean).

- [ ] **Step 4: Commit**

```bash
git add templates/NetSessions/cockpit.php templates/element/net/ src/Controller/NetSessionsController.php
git commit -m "feat(net): cockpit shell — entry bar + server-rendered roster + stat tiles"
```

---

### Task 12: Cockpit JS — entry loop + optimistic insert (no polling yet)

**Files:**
- Create: `webroot/js/net-cockpit.js`
- Test: `tests/js/net-merge.test.js`

- [ ] **Step 1: Write the failing test for the roster store**

```js
import { describe, it, expect } from 'vitest';
import { RosterStore } from '../../webroot/js/net-merge.js';

describe('RosterStore', () => {
  it('inserts newest first', () => {
    const s = new RosterStore();
    s.upsert({ id: 1, callsign: 'A', updated: '2026-05-22T12:00:00Z' });
    s.upsert({ id: 2, callsign: 'B', updated: '2026-05-22T12:01:00Z' });
    expect(s.rows().map(r => r.id)).toEqual([2, 1]);
  });

  it('upsert replaces by id (no duplicates)', () => {
    const s = new RosterStore();
    s.upsert({ id: 1, callsign: 'A', updated: '2026-05-22T12:00:00Z' });
    s.upsert({ id: 1, callsign: 'A2', updated: '2026-05-22T12:05:00Z' });
    expect(s.rows().length).toBe(1);
    expect(s.rows()[0].callsign).toBe('A2');
  });

  it('reconciles an optimistic temp row when the server id arrives', () => {
    const s = new RosterStore();
    s.upsert({ tempId: 't1', callsign: 'A', updated: '2026-05-22T12:00:00Z' });
    s.reconcile('t1', { id: 9, callsign: 'A', updated: '2026-05-22T12:00:01Z' });
    expect(s.rows().length).toBe(1);
    expect(s.rows()[0].id).toBe(9);
    expect(s.rows()[0].tempId).toBeUndefined();
  });

  it('remove deletes by id', () => {
    const s = new RosterStore();
    s.upsert({ id: 1, callsign: 'A', updated: '2026-05-22T12:00:00Z' });
    s.remove(1);
    expect(s.rows().length).toBe(0);
  });
});
```

- [ ] **Step 2: Run to verify fail**

Run: `npx vitest run tests/js/net-merge.test.js`
Expected: FAIL — module not found.

- [ ] **Step 3: Implement the store (`webroot/js/net-merge.js`)**

```js
// Pure, framework-free roster store. Imported by net-cockpit.js / net-live.js
// and unit-tested in isolation.
export class RosterStore {
  constructor() { this._byId = new Map(); this._byTemp = new Map(); }

  upsert(row) {
    if (row.id != null) {
      this._byId.set(row.id, { ...this._byId.get(row.id), ...row });
    } else if (row.tempId != null) {
      this._byTemp.set(row.tempId, row);
    }
  }

  reconcile(tempId, serverRow) {
    this._byTemp.delete(tempId);
    const { tempId: _drop, ...clean } = serverRow;
    this._byId.set(serverRow.id, clean);
  }

  remove(id) { this._byId.delete(id); }

  rows() {
    const all = [...this._byId.values(), ...this._byTemp.values()];
    // newest first by `updated` (ISO) then `at`
    return all.sort((a, b) => String(b.updated || b.at || '').localeCompare(String(a.updated || a.at || '')));
  }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `npx vitest run tests/js/net-merge.test.js`
Expected: PASS (4 tests).

- [ ] **Step 5: Write `net-cockpit.js` (entry loop, uses the store + DOM)**

```js
import { RosterStore } from './net-merge.js';

(function () {
  const cfg = window.NET;
  if (!cfg) return;
  const store = new RosterStore();
  const form = document.querySelector('[data-net-entry]');
  const tbody = document.querySelector('[data-net-roster] tbody');

  function csrf() {
    const m = document.querySelector('meta[name="csrfToken"]');
    return m ? m.getAttribute('content') : '';
  }

  function render() {
    if (!tbody) return;
    tbody.innerHTML = store.rows().map((r, i) => `
      <tr data-checkin-id="${r.id ?? ''}">
        <td>${store.rows().length - i}</td>
        <td class="callsign">${r.callsign ?? ''}</td>
        <td>${r.name ?? ''}</td>
        <td>${r.grid ?? ''}</td>
        <td>${r.signal != null ? 'S' + r.signal : ''}</td>
        <td>${r.role ?? ''}</td>
      </tr>`).join('');
  }

  // Seed from the server-rendered rows so refresh keeps state.
  document.querySelectorAll('[data-net-roster] tbody tr[data-checkin-id]').forEach(tr => {
    store.upsert({ id: Number(tr.dataset.checkinId), callsign: tr.children[1]?.textContent?.trim() });
  });

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(form).entries());
      const tempId = 't' + Date.now();
      store.upsert({ tempId, callsign: (data.callsign || '').toUpperCase(), name: data.name, grid: data.grid, role: data.role, updated: new Date().toISOString() });
      render();
      form.reset();
      form.querySelector('[name="callsign"]')?.focus();
      try {
        const res = await fetch(cfg.postUrl + '.json', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
          body: JSON.stringify(data),
        });
        const json = await res.json();
        if (json.ok) store.reconcile(tempId, json.checkin);
        else store.remove(tempId); // validation failed; drop optimistic row
      } catch (_) { /* offline path handled in Phase 3 */ }
      render();
    });
  }

  render();
  window.__netStore = store; // for net-poll.js (Phase 3) to share
})();
```

> Add `<meta name="csrfToken" content="...">` to `templates/layout/default.php` head if not present (CakePHP exposes the token via `$this->request->getAttribute('csrfToken')`). Verify before relying on it.

- [ ] **Step 6: Commit**

```bash
git add webroot/js/net-merge.js webroot/js/net-cockpit.js tests/js/net-merge.test.js
git commit -m "feat(net): cockpit entry loop + optimistic roster store + unit tests"
```

---

# Phase 3 — Real-time delta feed + collaborative co-loggers

### Task 13: Delta feed endpoint (`?since` cursor)

**Files:**
- Modify: `src/Controller/NetSessionsController.php` (add `checkinsFeed`)
- Modify: `tests/TestCase/Controller/NetSessionsControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
    public function testDeltaFeedReturnsOnlyChangedSince(): void
    {
        $this->loginAsOwner();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        // Fixtures: assume Qsos fixture has a net check-in for session 1 at a known updated_at.
        $this->get('/net-sessions/1/checkins?since=2000-01-01T00:00:00Z');
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('server_time', $body);
        $this->assertArrayHasKey('checkins', $body);
        $this->assertArrayHasKey('removed', $body);
        $this->assertArrayHasKey('stats', $body);
    }
```

> Add at least one net check-in row (`net_session_id=1`, `qso_type='net'`) to `tests/Fixture/QsosFixture.php` if not present.

- [ ] **Step 2: Run to verify fail**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter testDeltaFeedReturnsOnlyChangedSince`
Expected: FAIL.

- [ ] **Step 3: Implement `checkinsFeed`**

```php
    private function checkinsFeed(int $id): \Cake\Http\Response
    {
        $session = $this->loggerSessionOrFail($id);
        $since = (string)$this->request->getQuery('since', '');
        $now = DateTime::now();
        $qsos = $this->fetchTable('Qsos');

        $q = $qsos->find()->where(['net_session_id' => $id]);
        if ($since !== '') {
            $q->where(['updated_at >' => new DateTime($since)]);
        }
        $q->orderBy(['qso_datetime_utc' => 'ASC', 'id' => 'ASC']);

        $checkins = [];
        foreach ($q->all() as $row) {
            $checkins[] = $this->presentCheckin($row, true);
        }

        return $this->jsonResponse([
            'server_time' => $now->format('c'),
            'status'      => $session->status,
            'stats'       => (new \App\Service\NetMetrics($qsos))->sessionStats($id),
            'checkins'    => $checkins,
            'removed'     => [], // hard-deletes are reflected by absence on full refresh; see note
        ]);
    }
```

> **Deletion handling:** with hard deletes, a removed row simply stops appearing. For the live client this means a deleted row lingers until refresh. Acceptable for MVP; if exact live removal matters, switch check-in delete to a soft-delete flag and include those ids in `removed`. Noted in spec §17-adjacent. `NetMetrics` comes from Task 15 — stub `sessionStats` to `[]` if building feed first, then fill in.

- [ ] **Step 4: Run to verify pass**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter testDeltaFeedReturnsOnlyChangedSince`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Controller/NetSessionsController.php tests/TestCase/Controller/NetSessionsControllerTest.php tests/Fixture/QsosFixture.php
git commit -m "feat(net): delta feed endpoint with ?since cursor + stats"
```

---

### Task 14: SignalReport service

> **Build this before Task 10 Step 5 / Task 13** (both reference it). Placed here for narrative grouping; in execution order, do it right after Task 9.

**Files:**
- Create: `src/Service/SignalReport.php`
- Test: `tests/TestCase/Service/SignalReportTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\SignalReport;
use Cake\TestSuite\TestCase;

final class SignalReportTest extends TestCase
{
    public function testParsesStrengthFromRst(): void
    {
        $this->assertSame(9, SignalReport::strength('59'));
        $this->assertSame(7, SignalReport::strength('57'));
    }

    public function testParsesRsAndRstn(): void
    {
        $this->assertSame(9, SignalReport::strength('599')); // CW RST
        $this->assertSame(5, SignalReport::strength('55'));  // phone RS
    }

    public function testNullForUnparseable(): void
    {
        $this->assertNull(SignalReport::strength(''));
        $this->assertNull(SignalReport::strength(null));
        $this->assertNull(SignalReport::strength('abc'));
    }

    public function testDistributionBuckets(): void
    {
        $dist = SignalReport::distribution(['59', '57', '57', 'xx']);
        $this->assertSame(2, $dist[7]);
        $this->assertSame(1, $dist[9]);
        $this->assertArrayHasKey('unknown', $dist);
        $this->assertSame(1, $dist['unknown']);
    }
}
```

- [ ] **Step 2: Run to verify fail**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter SignalReportTest`
Expected: FAIL.

- [ ] **Step 3: Implement**

```php
<?php
declare(strict_types=1);

namespace App\Service;

/**
 * M6 — extract signal strength (1–9) from an RST/RS report string.
 * RST = Readability(1-5) Strength(1-9) [Tone]. The strength is the
 * SECOND character. RS (phone) is two chars: R then S. Either way the
 * strength digit is index 1.
 */
final class SignalReport
{
    public static function strength(?string $rst): ?int
    {
        if ($rst === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $rst);
        if ($digits === '' || strlen($digits) < 2) {
            return null;
        }
        $s = (int)$digits[1];
        return ($s >= 1 && $s <= 9) ? $s : null;
    }

    /**
     * @param iterable<?string> $rsts
     * @return array<int|string,int> keys 1..9 plus 'unknown'
     */
    public static function distribution(iterable $rsts): array
    {
        $dist = ['unknown' => 0];
        for ($i = 1; $i <= 9; $i++) {
            $dist[$i] = 0;
        }
        foreach ($rsts as $rst) {
            $s = self::strength($rst);
            if ($s === null) {
                $dist['unknown']++;
            } else {
                $dist[$s]++;
            }
        }
        return $dist;
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter SignalReportTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Service/SignalReport.php tests/TestCase/Service/SignalReportTest.php
git commit -m "feat(net): SignalReport service (RST→strength, distribution) + tests"
```

---

### Task 15: Polling client + collaborative co-logger management

**Files:**
- Create: `webroot/js/net-poll.js`
- Modify: `templates/NetSessions/cockpit.php` (load net-poll.js)
- Modify: `src/Controller/NetSessionsController.php` (`addLogger`, `removeLogger`, `join`)
- Modify: `config/routes.php`
- Modify: `tests/TestCase/Controller/NetSessionsControllerTest.php`

- [ ] **Step 1: Write `net-poll.js`** (shares `window.__netStore`)

```js
import { RosterStore } from './net-merge.js';

(function () {
  const cfg = window.NET;
  if (!cfg || cfg.status !== 'live') return;
  const store = window.__netStore || new RosterStore();
  let since = '';
  let timer = null;

  async function tick() {
    if (document.hidden) return; // pause when tab hidden
    try {
      const res = await fetch(cfg.feedUrl + '.json' + (since ? ('?since=' + encodeURIComponent(since)) : ''), {
        headers: { 'Accept': 'application/json' },
      });
      if (res.status === 304) return;
      const json = await res.json();
      since = json.server_time || since;
      (json.checkins || []).forEach(r => store.upsert(r));
      (json.removed || []).forEach(id => store.remove(id));
      document.dispatchEvent(new CustomEvent('net:updated', { detail: json }));
    } catch (_) { /* keep polling */ }
  }

  document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
  timer = setInterval(tick, 4000);
  tick();
  window.addEventListener('beforeunload', () => clearInterval(timer));
})();
```

> `net-cockpit.js` should listen for `net:updated` and re-render the roster + stat tiles. Add an event listener at the end of net-cockpit.js: `document.addEventListener('net:updated', render);` and update the stat tiles from `e.detail.stats`.

- [ ] **Step 2: Add co-logger routes + actions**

Routes:
```php
        $builder->connect('/net-sessions/{id}/loggers', ['controller' => 'NetSessions', 'action' => 'addLogger'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->connect('/net-sessions/{id}/loggers/{userId}', ['controller' => 'NetSessions', 'action' => 'removeLogger'])
            ->setPass(['id', 'userId'])->setPatterns(['id' => '\d+', 'userId' => '\d+']);
        $builder->connect('/net-sessions/join/{token}', ['controller' => 'NetSessions', 'action' => 'join'])
            ->setPass(['token']);
```

Actions:
```php
    public function addLogger(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $this->ownedOrFail($id); // owner-only
        $userId = (int)$this->request->getData('user_id');
        $loggers = $this->fetchTable('NetSessionLoggers');
        if ($userId > 0 && !$loggers->exists(['net_session_id' => $id, 'user_id' => $userId])) {
            $row = $loggers->newEntity(['net_session_id' => $id, 'user_id' => $userId, 'added_via' => 'owner']);
            $loggers->saveOrFail($row);
        }
        $this->Flash->success('Co-logger added.');
        return $this->redirect(['action' => 'view', $id]);
    }

    public function removeLogger(int $id, int $userId): \Cake\Http\Response
    {
        $this->request->allowMethod('post');
        $this->ownedOrFail($id);
        $loggers = $this->fetchTable('NetSessionLoggers');
        $row = $loggers->find()->where(['net_session_id' => $id, 'user_id' => $userId])->first();
        if ($row) {
            $loggers->deleteOrFail($row);
        }
        $this->Flash->success('Co-logger removed.');
        return $this->redirect(['action' => 'view', $id]);
    }

    public function join(string $token): \Cake\Http\Response
    {
        $uid = $this->Authentication->getIdentity()->getIdentifier();
        $session = $this->fetchTable('NetSessions')->find()->where(['logger_token' => $token])->first();
        if ($session === null) {
            throw new NotFoundException('Invalid invite link.');
        }
        $loggers = $this->fetchTable('NetSessionLoggers');
        if ($session->owner_id !== $uid && !$loggers->exists(['net_session_id' => $session->id, 'user_id' => $uid])) {
            $loggers->saveOrFail($loggers->newEntity([
                'net_session_id' => $session->id, 'user_id' => $uid, 'added_via' => 'invite',
            ]));
        }
        $this->Flash->success('You can now log check-ins for this net.');
        return $this->redirect(['action' => 'cockpit', $session->id]);
    }
```

- [ ] **Step 3: Write the failing test**

```php
    public function testInviteJoinAddsCoLogger(): void
    {
        $this->session(['Auth' => ['id' => 3]]);
        $this->enableCsrfToken();
        $this->get('/net-sessions/join/logtok1'); // session 1's logger_token
        $this->assertRedirectContains('/net-sessions/1/cockpit');
        $loggers = $this->getTableLocator()->get('NetSessionLoggers');
        $this->assertTrue($loggers->exists(['net_session_id' => 1, 'user_id' => 3]));
    }
```

- [ ] **Step 4: Run to verify pass**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetSessionsControllerTest`
Expected: PASS.

- [ ] **Step 5: JS merge regression**

Run: `npx vitest run tests/js/net-merge.test.js`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add webroot/js/net-poll.js templates/NetSessions/cockpit.php src/Controller/NetSessionsController.php config/routes.php tests/TestCase/Controller/NetSessionsControllerTest.php
git commit -m "feat(net): polling client + collaborative co-logger management (add/remove/join) + tests"
```

---

# Phase 4 — Public read-only live view

### Task 16: NetController public view + public delta feed

**Files:**
- Create: `src/Controller/NetController.php`
- Create: `templates/Net/live.php`
- Create: `webroot/js/net-live.js`
- Modify: `config/routes.php`
- Test: `tests/TestCase/Controller/NetControllerTest.php`

- [ ] **Step 1: Add public routes (no auth)**

```php
        $builder->connect('/net/{slug}', ['controller' => 'Net', 'action' => 'live'])->setPass(['slug']);
        $builder->connect('/net/{slug}/live', ['controller' => 'Net', 'action' => 'feed'])->setPass(['slug']);
```

- [ ] **Step 2: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class NetControllerTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.NetSessions', 'app.NetSessionLoggers', 'app.Qsos'];

    public function testPublicViewRendersWhenPublic(): void
    {
        $this->get('/net/live-net-slug'); // session 1, is_public=1, live
        $this->assertResponseOk();
        $this->assertResponseContains('MARTS Daily Net');
    }

    public function testPublicFeedHidesLoggedBy(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/net/live-net-slug/live');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertStringNotContainsString('logged_by_user_id', $body);
    }

    public function testScheduledSessionNotPublic(): void
    {
        // Make a scheduled, public session and confirm 404 on public view.
        $t = $this->getTableLocator()->get('NetSessions');
        $s = $t->newEntity(['net_title' => 'Future']);
        $s->set('owner_id', 1, ['guard' => false]);
        $s->set('status', 'scheduled', ['guard' => false]);
        $s->set('public_slug', 'future-slug', ['guard' => false]);
        $s->set('is_public', true, ['guard' => false]);
        $t->saveOrFail($s);
        $this->get('/net/future-slug');
        $this->assertResponseCode(404);
    }
}
```

- [ ] **Step 3: Run to verify fail**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetControllerTest`
Expected: FAIL.

- [ ] **Step 4: Implement `NetController`**

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;

/**
 * M6 — public read-only live net view. No auth. Serves only public,
 * non-scheduled sessions and a whitelisted field subset.
 */
class NetController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
        $this->Authentication->allowUnauthenticated(['live', 'feed']);
    }

    private function publicSessionOrFail(string $slug): \App\Model\Entity\NetSession
    {
        $row = $this->fetchTable('NetSessions')->find()
            ->where(['public_slug' => $slug, 'is_public' => true, 'status !=' => 'scheduled'])
            ->first();
        if ($row === null) {
            throw new NotFoundException('Net not found.');
        }
        return $row;
    }

    public function live(string $slug): void
    {
        $session = $this->publicSessionOrFail($slug);
        $this->set(['session' => $session, 'title' => $session->net_title]);
    }

    public function feed(string $slug): \Cake\Http\Response
    {
        $session = $this->publicSessionOrFail($slug);
        $since = (string)$this->request->getQuery('since', '');
        $qsos = $this->fetchTable('Qsos');
        $q = $qsos->find()->where(['net_session_id' => $session->id]);
        if ($since !== '') {
            $q->where(['updated_at >' => new DateTime($since)]);
        }
        $q->orderBy(['qso_datetime_utc' => 'ASC', 'id' => 'ASC']);

        $checkins = [];
        foreach ($q->all() as $row) {
            $checkins[] = [
                'id'       => $row->id,
                'callsign' => $row->call_worked,
                'name'     => $row->operator_name,
                'grid'     => $row->grid_square,
                'signal'   => \App\Service\SignalReport::strength($row->rst_received),
                'role'     => $row->net_role,
                'at'       => $row->qso_datetime_utc?->format('c'),
                'updated'  => $row->updated_at?->format('c'),
            ];
        }
        return $this->jsonResponse([
            'server_time' => DateTime::now()->format('c'),
            'status'      => $session->status,
            'stats'       => (new \App\Service\NetMetrics($qsos))->sessionStats($session->id),
            'checkins'    => $checkins,
            'removed'     => [],
        ]);
    }
}
```

- [ ] **Step 5: Build `templates/Net/live.php` + `net-live.js`**

`live.php`: read-only — header (title/org/freq/band/mode + LIVE/ENDED badge), `<?= $this->element('net/roster', ['checkins' => []]) ?>` (JS fills it), `<?= $this->element('net/stat_tiles') ?>`, and a script setting `window.NET = { feedUrl: '/net/<slug>/live', status: '<status>' }` then loads `net-merge.js`-based `net-live.js` (a read-only variant of net-poll.js: no entry form, polls feed, renders roster + tiles). No entry bar, no edit controls.

- [ ] **Step 6: Run to verify pass**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetControllerTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Add public-feed rate limiting**

In `src/Middleware/RateLimitMiddleware.php` (or its config), add `/net/*/live` to the rate-limited path set keyed on IP+path. Follow the existing dupe-check rate-limit registration. Verify the existing middleware tests still pass:
Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter RateLimit`

- [ ] **Step 8: Commit**

```bash
git add src/Controller/NetController.php templates/Net/ webroot/js/net-live.js config/routes.php tests/TestCase/Controller/NetControllerTest.php src/Middleware/RateLimitMiddleware.php
git commit -m "feat(net): public read-only live view + delta feed (field-whitelisted, rate-limited) + tests"
```

---

# Phase 5 — Analytics

### Task 17: NetMetrics service (session stats + signal distribution + map points)

**Files:**
- Create: `src/Service/NetMetrics.php`
- Test: `tests/TestCase/Service/NetMetricsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\NetMetrics;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class NetMetricsTest extends TestCase
{
    protected array $fixtures = ['app.Users', 'app.NetSessions', 'app.Qsos'];

    private function metrics(): NetMetrics
    {
        return new NetMetrics(TableRegistry::getTableLocator()->get('Qsos'));
    }

    public function testSessionStatsCountsCheckins(): void
    {
        $stats = $this->metrics()->sessionStats(1);
        $this->assertArrayHasKey('checkins', $stats);
        $this->assertArrayHasKey('unique', $stats);
        $this->assertArrayHasKey('signal', $stats); // distribution map
        $this->assertIsInt($stats['checkins']);
    }

    public function testMapPointsHaveLatLon(): void
    {
        // Requires a fixture check-in with grid_square set (e.g. OJ02).
        $points = $this->metrics()->mapPoints(1);
        foreach ($points as $p) {
            $this->assertArrayHasKey('lat', $p);
            $this->assertArrayHasKey('lon', $p);
            $this->assertArrayHasKey('callsign', $p);
        }
    }
}
```

- [ ] **Step 2: Run to verify fail**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetMetricsTest`
Expected: FAIL.

- [ ] **Step 3: Implement `NetMetrics`** (uses `App\Service\GridSquare` for lat/lon)

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Table;

/**
 * M6 — net analytics. All values are computed from qsos scoped by
 * net_session_id (per-session) or owner_id+net_title (cross-session).
 */
final class NetMetrics
{
    /** Cross-session window + regular threshold (spec §9.3). */
    public const WINDOW = 8;
    public const REGULAR_THRESHOLD = 0.5;

    public function __construct(private Table $qsos) {}

    public function sessionStats(int $sessionId): array
    {
        $rows = $this->qsos->find()
            ->where(['net_session_id' => $sessionId])
            ->select(['call_worked', 'rst_received', 'qso_datetime_utc'])
            ->disableHydration()->all()->toList();

        $calls = array_column($rows, 'call_worked');
        $unique = count(array_unique(array_filter($calls)));
        $signal = SignalReport::distribution(array_column($rows, 'rst_received'));

        return [
            'checkins' => count($rows),
            'unique'   => $unique,
            'signal'   => $signal,
        ];
    }

    /** @return list<array{callsign:string,grid:string,lat:float,lon:float,signal:?int}> */
    public function mapPoints(int $sessionId): array
    {
        $rows = $this->qsos->find()
            ->where(['net_session_id' => $sessionId, 'grid_square IS NOT' => null])
            ->select(['call_worked', 'grid_square', 'rst_received'])
            ->disableHydration()->all();

        $points = [];
        foreach ($rows as $r) {
            $ll = GridSquare::toLatLon((string)$r['grid_square']);
            if ($ll === null) {
                continue;
            }
            $points[] = [
                'callsign' => (string)$r['call_worked'],
                'grid'     => (string)$r['grid_square'],
                'lat'      => $ll['lat'],
                'lon'      => $ll['lon'],
                'signal'   => SignalReport::strength($r['rst_received']),
            ];
        }
        return $points;
    }

    /** Cross-session retention for an owner's named net. */
    public function retention(int $ownerId, string $netTitle, int $window = self::WINDOW): array
    {
        // Pull the last N ended sessions for this owner+title, oldest→newest.
        $sessions = $this->qsos->getAssociation('NetSessions') // requires belongsTo on Qsos; else inject NetSessionsTable
            ->find()
            ->where(['owner_id' => $ownerId, 'net_title' => $netTitle, 'status' => 'ended'])
            ->orderBy(['ended_at' => 'DESC'])->limit($window)->all()->toList();
        $sessionIds = array_reverse(array_column($sessions, 'id'));

        $attendance = []; // sessionId => [callsigns]
        foreach ($sessionIds as $sid) {
            $calls = $this->qsos->find()
                ->where(['net_session_id' => $sid])
                ->select(['call_worked'])->distinct(['call_worked'])
                ->disableHydration()->all()->extract('call_worked')->toList();
            $attendance[$sid] = array_values(array_filter($calls));
        }

        // Regulars: appear in >= threshold of sessions.
        $counts = [];
        foreach ($attendance as $calls) {
            foreach ($calls as $c) {
                $counts[$c] = ($counts[$c] ?? 0) + 1;
            }
        }
        $n = max(count($attendance), 1);
        $regulars = array_keys(array_filter($counts, fn ($c) => $c / $n >= self::REGULAR_THRESHOLD));

        // Retention: share of previous session's calls present in the latest.
        $retention = null;
        if (count($sessionIds) >= 2) {
            $prev = $attendance[$sessionIds[count($sessionIds) - 2]] ?? [];
            $last = $attendance[$sessionIds[count($sessionIds) - 1]] ?? [];
            $retention = count($prev) ? round(count(array_intersect($prev, $last)) / count($prev), 3) : null;
        }

        return [
            'sessions'  => array_map(fn ($sid) => ['id' => $sid, 'unique' => count($attendance[$sid])], $sessionIds),
            'regulars'  => $regulars,
            'retention' => $retention,
        ];
    }
}
```

> **Dependency note:** `retention()` needs to read net_sessions. Cleanest: inject `NetSessionsTable` into the constructor too, or add `belongsTo('NetSessions')` on `QsosTable`. Pick one in implementation; the test for `retention` (Task 18) will pin the signature. **Verify `App\Service\GridSquare::toLatLon()` exists** (created for M5 GPS auto-fill); if the method name differs, adapt — `grep -rn "function .*LatLon\|fromLatLon\|toLatLon" src/Service/GridSquare.php`.

- [ ] **Step 4: Run to verify pass**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetMetricsTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Service/NetMetrics.php tests/TestCase/Service/NetMetricsTest.php
git commit -m "feat(net): NetMetrics — session stats, signal distribution, map points + tests"
```

---

### Task 18: Retention metric test + analytics page

**Files:**
- Modify: `tests/TestCase/Service/NetMetricsTest.php`
- Modify: `src/Controller/NetSessionsController.php` (add `analytics`)
- Create: `templates/NetSessions/analytics.php`

- [ ] **Step 1: Write the failing retention test**

```php
    public function testRetentionComputesRegularsAndRate(): void
    {
        // Fixtures: sessions 1 (live) + 2 (ended) share net_title 'MARTS Daily Net',
        // with overlapping callsigns across check-ins. Add ended sessions + qsos to fixtures.
        $r = $this->metrics()->retention(1, 'MARTS Daily Net');
        $this->assertArrayHasKey('regulars', $r);
        $this->assertArrayHasKey('retention', $r);
        $this->assertArrayHasKey('sessions', $r);
    }
```

- [ ] **Step 2: Run, confirm pass** (implementation already in Task 17; this pins the signature)

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetMetricsTest`
Expected: PASS.

- [ ] **Step 3: Add `analytics` action**

```php
    public function analytics(int $id): void
    {
        $session = $this->ownedOrFail($id);
        $metrics = new \App\Service\NetMetrics($this->fetchTable('Qsos'));
        $this->set([
            'session' => $session,
            'stats' => $metrics->sessionStats($id),
            'mapPoints' => $metrics->mapPoints($id),
            'retention' => $metrics->retention($session->owner_id, $session->net_title),
            'title' => $session->net_title . ' — analytics',
        ]);
    }
```

Add route:
```php
        $builder->connect('/net-sessions/{id}/analytics', ['controller' => 'NetSessions', 'action' => 'analytics'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
```

- [ ] **Step 4: Build `analytics.php`** — renders signal chart (`net/signal_chart` element fed `$stats['signal']`), the map (`net/map` element fed `$mapPoints` as JSON), and retention (regulars list + retention % + per-session unique sparkline). Server-renders the data as `<script type="application/json" data-...>` blocks the JS reads.

- [ ] **Step 5: Commit**

```bash
git add tests/TestCase/Service/NetMetricsTest.php src/Controller/NetSessionsController.php templates/NetSessions/analytics.php config/routes.php
git commit -m "feat(net): retention metrics + analytics page"
```

---

### Task 19: Signal chart (SVG, dependency-free)

**Files:**
- Create: `webroot/js/net-charts.js`
- Create: `templates/element/net/signal_chart.php`
- Test: `tests/js/net-charts.test.js`

- [ ] **Step 1: Write the failing test**

```js
import { describe, it, expect } from 'vitest';
import { signalBars } from '../../webroot/js/net-charts.js';

describe('signalBars', () => {
  it('returns a bar per non-zero bucket scaled to max', () => {
    const bars = signalBars({ 7: 2, 9: 4, unknown: 1 });
    const s9 = bars.find(b => b.label === 'S9');
    expect(s9.heightPct).toBe(100);
    const s7 = bars.find(b => b.label === 'S7');
    expect(s7.heightPct).toBe(50);
  });

  it('omits zero buckets', () => {
    const bars = signalBars({ 1: 0, 5: 3 });
    expect(bars.every(b => b.count > 0)).toBe(true);
  });
});
```

- [ ] **Step 2: Run to verify fail**

Run: `npx vitest run tests/js/net-charts.test.js`
Expected: FAIL.

- [ ] **Step 3: Implement `net-charts.js`**

```js
// Dependency-free signal-distribution bars. Pure fn is unit-tested;
// renderSignalChart() paints SVG/divs into a container.
export function signalBars(dist) {
  const entries = Object.entries(dist)
    .filter(([, c]) => c > 0)
    .map(([k, c]) => ({ label: k === 'unknown' ? '?' : 'S' + k, key: k, count: c }));
  const max = Math.max(1, ...entries.map(e => e.count));
  return entries.map(e => ({ ...e, heightPct: Math.round((e.count / max) * 100) }));
}

export function renderSignalChart(container, dist) {
  const bars = signalBars(dist);
  container.innerHTML = `<div class="net-chart">${bars.map(b => `
    <div class="net-chart__col">
      <div class="net-chart__bar" style="height:${b.heightPct}%" title="${b.label}: ${b.count}"></div>
      <div class="net-chart__lbl">${b.label}</div>
    </div>`).join('')}</div>`;
}

// Auto-wire on analytics/cockpit pages.
document.addEventListener('DOMContentLoaded', () => {
  const el = document.querySelector('[data-signal-chart]');
  const data = document.querySelector('[data-signal-json]');
  if (el && data) renderSignalChart(el, JSON.parse(data.textContent));
});
document.addEventListener('net:updated', (e) => {
  const el = document.querySelector('[data-signal-chart]');
  if (el && e.detail?.stats?.signal) renderSignalChart(el, e.detail.stats.signal);
});
```

- [ ] **Step 4: Add `.net-chart` styles to `webroot/css/theme.css`** (bars flex row, colour-graded; rebuild `npm run build:css`).

- [ ] **Step 5: Run to verify pass**

Run: `npx vitest run tests/js/net-charts.test.js`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add webroot/js/net-charts.js templates/element/net/signal_chart.php webroot/css/theme.css webroot/css/dist.css tests/js/net-charts.test.js
git commit -m "feat(net): dependency-free SVG signal-distribution chart + tests"
```

---

### Task 20: Participant map (Leaflet, vendored, with list fallback)

**Files:**
- Create: `webroot/js/vendor/leaflet/leaflet.js`, `leaflet.css` (+ marker assets) — vendored
- Create: `webroot/js/net-map.js`
- Create: `templates/element/net/map.php`

- [ ] **Step 1: Vendor Leaflet**

Download Leaflet 1.9.x dist into `webroot/js/vendor/leaflet/` (js + css + images). Mirrors how fabric.js is vendored (`grep -rn "fabric" templates/ | head` for the include pattern).

- [ ] **Step 2: Implement `net-map.js`**

```js
// Renders participant grid squares on a Leaflet map; falls back to a
// grouped list if Leaflet/tiles are unavailable (offline/constrained net).
(function () {
  const el = document.querySelector('[data-net-map]');
  const data = document.querySelector('[data-map-json]');
  if (!el || !data) return;
  let points = [];
  try { points = JSON.parse(data.textContent) || []; } catch (_) {}

  function listFallback() {
    const byGrid = {};
    points.forEach(p => { (byGrid[p.grid] = byGrid[p.grid] || []).push(p.callsign); });
    el.innerHTML = '<ul class="net-map-fallback">' +
      Object.entries(byGrid).map(([g, cs]) => `<li><strong>${g}</strong>: ${cs.join(', ')}</li>`).join('') +
      '</ul>';
  }

  if (typeof L === 'undefined' || points.length === 0) { listFallback(); return; }
  try {
    const map = L.map(el).setView([points[0].lat, points[0].lon], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap', maxZoom: 12,
    }).addTo(map);
    points.forEach(p => L.marker([p.lat, p.lon]).addTo(map)
      .bindPopup(`<strong>${p.callsign}</strong><br>${p.grid}${p.signal ? ' · S' + p.signal : ''}`));
  } catch (_) { listFallback(); }
})();
```

- [ ] **Step 3: `map.php` element** — outputs `<div data-net-map style="height:240px"></div>` + `<script type="application/json" data-map-json><?= json_encode($mapPoints) ?></script>`, and includes the vendored Leaflet css/js (cockpit loads the mini version; analytics the full map).

- [ ] **Step 4: Manual smoke test** — analytics page shows markers for check-ins with grids; with network blocked, shows the list fallback. (No unit test for the Leaflet path; the data prep is covered by `NetMetrics::mapPoints` in Task 17.)

- [ ] **Step 5: Commit**

```bash
git add webroot/js/vendor/leaflet/ webroot/js/net-map.js templates/element/net/map.php
git commit -m "feat(net): Leaflet participant map with offline list fallback"
```

---

# Phase 6 — Exports

### Task 21: ADIF export (reuse AdifExporter via adapter)

**Files:**
- Create: `src/Service/NetAdifAdapter.php`
- Modify: `src/Controller/NetSessionsController.php` (add `exportAdif`)
- Modify: `config/routes.php`
- Modify: `tests/TestCase/Controller/NetSessionsControllerTest.php`

- [ ] **Step 1: Write the adapter**

```php
<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Adapts a NetSession to the shape AdifExporter::export() expects
 * (code, name, grid_square, started_at, ended_at, notes) without
 * modifying the exporter. `code` is left blank (no POTA/SOTA ref for
 * a net); `name` carries the net title.
 */
final class NetAdifAdapter
{
    public string $code = '';
    public string $name;
    public ?string $grid_square = null;
    public mixed $started_at;
    public mixed $ended_at;
    public ?string $notes;

    public function __construct(\App\Model\Entity\NetSession $s)
    {
        $this->name = $s->net_title . ($s->net_organisation ? ' (' . $s->net_organisation . ')' : '');
        $this->started_at = $s->started_at;
        $this->ended_at = $s->ended_at;
        $this->notes = $s->notes;
    }
}
```

- [ ] **Step 2: Write the failing test**

```php
    public function testAdifExportScopedToSession(): void
    {
        $this->loginAsOwner();
        $this->get('/net-sessions/1/export.adi');
        $this->assertResponseOk();
        $this->assertContentType('text/plain');
        $this->assertResponseContains('<EOH>');
    }
```

- [ ] **Step 3: Run to verify fail**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter testAdifExportScopedToSession`
Expected: FAIL.

- [ ] **Step 4: Add `exportAdif`**

```php
    public function exportAdif(int $id): \Cake\Http\Response
    {
        $session = $this->loggerSessionOrFail($id);
        $qsos = $this->fetchTable('Qsos')->find()
            ->where(['net_session_id' => $id])
            ->orderBy(['qso_datetime_utc' => 'ASC'])->all();
        $owner = $this->fetchTable('Users')->get($session->owner_id);
        $adif = (new \App\Service\AdifExporter())->export(
            new \App\Service\NetAdifAdapter($session),
            $qsos,
            (string)$owner->callsign
        );
        return $this->response
            ->withType('text/plain')
            ->withDownload('net-' . $session->id . '.adi')
            ->withStringBody($adif);
    }
```

Route:
```php
        $builder->connect('/net-sessions/{id}/export.adi', ['controller' => 'NetSessions', 'action' => 'exportAdif'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
```

- [ ] **Step 5: Run to verify pass**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter testAdifExportScopedToSession`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Service/NetAdifAdapter.php src/Controller/NetSessionsController.php config/routes.php tests/TestCase/Controller/NetSessionsControllerTest.php
git commit -m "feat(net): ADIF export per net session (reuses AdifExporter via adapter) + test"
```

---

### Task 22: PDF report (dompdf)

**Files:**
- Modify: `composer.json` (add `dompdf/dompdf`)
- Create: `src/Service/NetReportPdf.php`
- Create: `templates/pdf/net_report.php`
- Modify: `src/Controller/NetSessionsController.php` (add `exportPdf`)
- Modify: `config/routes.php`
- Test: `tests/TestCase/Service/NetReportPdfTest.php`

- [ ] **Step 1: Add dompdf**

Run: `docker compose run --rm --no-deps php composer require dompdf/dompdf`
Expected: dompdf added to `composer.json`/`composer.lock`.

- [ ] **Step 2: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\NetReportPdf;
use Cake\TestSuite\TestCase;

final class NetReportPdfTest extends TestCase
{
    public function testRendersNonEmptyPdf(): void
    {
        $html = '<h1>MARTS Daily Net</h1><p>2 check-ins</p>';
        $pdf = (new NetReportPdf())->fromHtml($html);
        $this->assertNotEmpty($pdf);
        $this->assertSame('%PDF', substr($pdf, 0, 4)); // PDF magic bytes
    }
}
```

- [ ] **Step 3: Run to verify fail**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetReportPdfTest`
Expected: FAIL.

- [ ] **Step 4: Implement `NetReportPdf`**

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * M6 — render an HTML net report to a PDF byte string via dompdf.
 * Pure-PHP; no system binaries (shared-host friendly).
 */
final class NetReportPdf
{
    public function fromHtml(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false); // no external fetches
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return (string)$dompdf->output();
    }
}
```

- [ ] **Step 5: Run to verify pass**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter NetReportPdfTest`
Expected: PASS.

- [ ] **Step 6: Add `exportPdf` action + report template**

```php
    public function exportPdf(int $id): \Cake\Http\Response
    {
        $session = $this->loggerSessionOrFail($id);
        $metrics = new \App\Service\NetMetrics($this->fetchTable('Qsos'));
        $checkins = $this->fetchTable('Qsos')->find()
            ->where(['net_session_id' => $id])
            ->orderBy(['qso_datetime_utc' => 'ASC'])->all();
        // Render the HTML report template to a string (no layout).
        $html = $this->createView()
            ->setTemplatePath('pdf')->setLayout(false)
            ->set([
                'session' => $session,
                'stats' => $metrics->sessionStats($id),
                'checkins' => $checkins,
            ])
            ->render('net_report');
        $pdf = (new \App\Service\NetReportPdf())->fromHtml($html);
        return $this->response
            ->withType('application/pdf')
            ->withDownload('net-' . $session->id . '-report.pdf')
            ->withStringBody($pdf);
    }
```

Route:
```php
        $builder->connect('/net-sessions/{id}/export.pdf', ['controller' => 'NetSessions', 'action' => 'exportPdf'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
```

`templates/pdf/net_report.php`: a self-contained HTML doc (inline `<style>`, no external assets) with net header (title/org/freq/band/mode/date/duration), summary stats, an inline-styled signal-distribution bar block (server-rendered from `$stats['signal']` — divs with `height` %), and the full check-in roster `<table>`.

- [ ] **Step 7: Manual smoke test**

Visit `http://localhost:8090/net-sessions/{id}/export.pdf` for an ended net → a valid PDF downloads and opens.

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock src/Service/NetReportPdf.php templates/pdf/net_report.php src/Controller/NetSessionsController.php config/routes.php tests/TestCase/Service/NetReportPdfTest.php
git commit -m "feat(net): PDF net report via dompdf + test"
```

---

# Phase 7 — Help docs, navigation, mobile polish, final checks

### Task 23: Help docs + catalog registration

**Files:**
- Create: `templates/Help/net/index.php`, `running-a-net.php`, `collaborative-logging.php`, `public-view.php`, `analytics-and-exports.php`
- Modify: `src/Service/HelpCatalog.php`
- Modify: `tests/TestCase/Service/HelpCatalogTest.php` (count assertion) if it pins a number

- [ ] **Step 1: Add a `net` category to `HelpCatalog::TREE`**

```php
        'net' => [
            'label' => 'Net control (NCS)',
            'pages' => [
                'index'                 => 'Net control dashboard',
                'running-a-net'         => 'Running a net',
                'collaborative-logging' => 'Collaborative logging',
                'public-view'           => 'The public live view',
                'analytics-and-exports' => 'Analytics & exports',
            ],
        ],
```

- [ ] **Step 2: Write the five articles**

Follow the existing article shape (`templates/Help/mobile/quick-add.php` is the reference: `$this->extend('/Help/view')`, `ui/page_header`, `ui/callout`). Cover: create/start/end + cockpit; adding co-loggers + invite link; sharing the public link + what viewers see; signal distribution / map / retention + PDF/ADIF export.

- [ ] **Step 3: Verify routes render**

Run: `docker compose up -d` then:
```bash
for p in index running-a-net collaborative-logging public-view analytics-and-exports; do
  echo -n "/help/net/$p → "; curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:8090/help/net/$p"; done
```
Expected: all `200`.

- [ ] **Step 4: Run help tests**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit --filter "HelpCatalogTest|HelpControllerTest"`
Expected: PASS (bump the `allPages` count assertion if it asserts an exact total).

- [ ] **Step 5: Commit**

```bash
git add templates/Help/net/ src/Service/HelpCatalog.php tests/TestCase/Service/HelpCatalogTest.php
git commit -m "docs(help): NCS dashboard help articles + catalog registration"
```

---

### Task 24: Navigation entry + dashboard tile

**Files:**
- Modify: `templates/layout/default.php` (nav + mobile More sheet)
- Modify: `templates/Dashboard/index.php` (quick action / live-net banner)

- [ ] **Step 1: Add nav links**

Add "Net control" to the desktop nav and the mobile "More" sheet (follow the existing menu item pattern in `templates/layout/default.php`), linking `/net-sessions`. If the user has a live net, show a "● LIVE" pill linking to its cockpit.

- [ ] **Step 2: Dashboard hook**

In `templates/Dashboard/index.php` add a "Net control" quick action; if `DashboardController` exposes a live session, show a banner linking to the cockpit. (Add the lookup to `DashboardController::index()` via `NetSessionsTable::findLiveForUser`.)

- [ ] **Step 3: Manual smoke + commit**

```bash
git add templates/layout/default.php templates/Dashboard/index.php src/Controller/DashboardController.php
git commit -m "feat(net): navigation entry + dashboard live-net banner"
```

---

### Task 25: Full suite + mobile pass + final commit

- [ ] **Step 1: Run the whole PHP suite**

Run: `docker compose run --rm --no-deps php vendor/bin/phpunit`
Expected: all green (existing + new net tests).

- [ ] **Step 2: Run the whole JS suite**

Run: `npm test`
Expected: all green (existing + net-merge + net-charts).

- [ ] **Step 3: Mobile check at 375 px**

Load the cockpit, public view, and analytics at 375 px (DevTools). Confirm: entry bar usable one-thumb, roster readable (stacked if needed), stat tiles wrap, map height sane, no horizontal overflow. Fix CSS in `theme.css` (rebuild `npm run build:css`).

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "test(net): full-suite green + 375px mobile polish for NCS dashboard"
```

- [ ] **Step 5: Open PR**

```bash
git push -u origin m6-ncs-dashboard
gh pr create --title "feat(m6): NCS dashboard" --body "Implements docs/superpowers/specs/2026-05-22-ncs-dashboard-design.md — net sessions, live collaborative cockpit, public live view, analytics (signal/map/retention), PDF + ADIF export. Help docs included."
```

---

## Self-Review

**Spec coverage:**
- §5 data model → Tasks 1–7. ✅
- §6 lifecycle (scheduled/live/ended) → Task 9. ✅
- §7 real-time polling + delta → Tasks 12, 13, 15. ✅
- §8 cockpit UI → Tasks 11, 12. ✅
- §9.1 signal distribution → Tasks 14, 19. ✅
- §9.2 geomap + fallback → Tasks 17, 20. ✅
- §9.3 retention → Tasks 17, 18. ✅
- §10 ADIF + PDF → Tasks 21, 22. ✅
- §11 component map → matches task files. ✅
- §12 routes → Tasks 9,10,15,16,18,21,22. ✅
- §13 security (owner/co-logger/public scoping, mass-assignment lockdown, rate-limit, audit) → Tasks 9,10,15,16; **audit-log events (AuditLogger) — add to start/end/checkin actions during Task 9/10** (called out here so it isn't missed). ✅
- §14 testing → tests in every logic task. ✅
- §15 help docs → Task 23. ✅

**Placeholder scan:** Views (index/add/edit/cockpit/analytics/live/pdf) are described by structure rather than full markup — intentional, as they follow named existing reference templates (Activations, Help/mobile/quick-add) and carry no business logic; all logic-bearing units (services, controllers, JS store/chart) have complete code. No "TBD/handle errors" placeholders remain.

**Type consistency:** `SignalReport::strength()`/`distribution()`, `NetMetrics::sessionStats()/mapPoints()/retention()`, `RosterStore.upsert/reconcile/remove/rows`, `signalBars()`, `NetSessionsTable::isLogger/findUpcomingForUser/findLiveForUser/findRecentForUser`, `jsonResponse()` are used consistently across tasks. Two dependency-ordering notes flagged inline: build **Task 14 (SignalReport) right after Task 9**, before Tasks 10/13 that call it; and decide `NetMetrics::retention()` net-session access (inject `NetSessionsTable` or add `belongsTo` on Qsos) when implementing Task 17.

**Known MVP simplification:** live deletion uses absence-on-refresh (hard delete) rather than a `removed[]` stream; upgrade to soft-delete if exact live removal is required (noted at Task 13).
