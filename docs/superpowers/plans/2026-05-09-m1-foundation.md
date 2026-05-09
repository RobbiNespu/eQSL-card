# M1 Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a working guest-only eQSL generator (single hard-coded card layout) on CakePHP 5 + PHP 8.1 + MariaDB 10.6, deployable to shared hosting via FTP, with auth scaffolding ready for M2.

**Architecture:** Single CakePHP 5 monolith. Server-rendered Bootstrap 5 views with Alpine.js sprinkles. All card rendering via PHP-GD → PNG → FPDF wraps PNG into PDF. Local dev runs in Docker (php-fpm 8.1 + mariadb 10.6 + nginx + mailhog). Production runs on bare LAMP shared hosting with no SSH; first-time setup via a self-locking web installer.

**Tech Stack:** PHP 8.1, CakePHP 5.x, cakephp/migrations 4.x, cakephp/authentication 3.x, setasign/fpdf 1.8, MariaDB 10.6.25, Bootstrap 5 (CDN), Alpine.js 3 (CDN), Docker Compose, nginx, mailhog, PHPUnit 10.

**Spec reference:** [`docs/superpowers/specs/2026-05-09-eqsl-card-design.md`](../specs/2026-05-09-eqsl-card-design.md), Section 11 → M1.

**Commit author:** All commits MUST be authored by `Robbi Nespu <robbinespu@gmail.com>` with NO `Co-Authored-By:` trailer. Verify after each commit:

```bash
git log -1 --pretty='%an <%ae>%n%B' | head -10
```

---

## Repository state assumptions

The repo currently contains only `LICENSE`, `.gitignore`, `docs/superpowers/specs/2026-05-09-eqsl-card-design.md`, and this plan file. No CakePHP code exists yet. Tasks 1–3 establish the dev environment and skeleton; everything else builds on that.

---

## Phase A: Project bootstrap

### Task 1: Docker development environment

**Files:**
- Create: `.docker/php/Dockerfile`
- Create: `.docker/php/php.ini`
- Create: `.docker/nginx/default.conf`
- Create: `docker-compose.yml`
- Create: `.dockerignore`
- Create: `.editorconfig`

- [ ] **Step 1: Write `.docker/php/Dockerfile`**

```dockerfile
FROM php:8.1-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg-dev libfreetype-dev libwebp-dev \
        libicu-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql intl mbstring zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
```

- [ ] **Step 2: Write `.docker/php/php.ini`**

```ini
memory_limit = 256M
upload_max_filesize = 16M
post_max_size = 20M
max_execution_time = 60
date.timezone = UTC
display_errors = On
display_startup_errors = On
error_reporting = E_ALL
log_errors = On
opcache.enable_cli = 1
```

- [ ] **Step 3: Write `.docker/nginx/default.conf`**

```nginx
server {
    listen 80 default_server;
    server_name _;
    root /var/www/html/webroot;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|git|env) { deny all; }
}
```

- [ ] **Step 4: Write `docker-compose.yml`**

```yaml
services:
  php:
    build:
      context: ./.docker/php
    volumes:
      - ./:/var/www/html
    environment:
      - DATABASE_URL=mysql://eqsl:eqsl@db:3306/eqsl?encoding=utf8mb4
      - EMAIL_TRANSPORT_DEFAULT_URL=smtp://mailhog:1025
    depends_on:
      db:
        condition: service_healthy
      mailhog:
        condition: service_started

  nginx:
    image: nginx:1.27-alpine
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html:ro
      - ./.docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - php

  db:
    image: mariadb:10.6.25
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: eqsl
      MYSQL_USER: eqsl
      MYSQL_PASSWORD: eqsl
    volumes:
      - dbdata:/var/lib/mysql
    ports:
      - "127.0.0.1:3306:3306"
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 10
      start_period: 10s

  mailhog:
    image: mailhog/mailhog:v1.0.1
    ports:
      - "8025:8025"

volumes:
  dbdata: {}
```

Three deliberate divergences from a naive first draft, all driven by code-review feedback during T1 execution:
- `mailhog` pinned to `v1.0.1` (avoid `:latest` drift on a stale image).
- `db` exposes `127.0.0.1:3306:3306` (loopback-only — don't expose dev DB to LAN).
- `db` has a healthcheck and `php` waits on `condition: service_healthy` (`depends_on` alone doesn't wait for MySQL readiness; without this, migrations after `docker compose up` flake).

- [ ] **Step 5: Write `.dockerignore`**

```
.git
.idea
.vscode
node_modules
vendor
tmp/cache/*
tmp/sessions/*
logs/*
dist/
.superpowers/
```

- [ ] **Step 6: Write `.editorconfig`**

```
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = space
indent_size = 4

[*.{yml,yaml,json,md}]
indent_size = 2
```

- [ ] **Step 7: Build the PHP image and verify versions**

Run:
```bash
docker compose build php
docker compose run --rm php php -v
docker compose run --rm php php -m | grep -E '^(gd|pdo_mysql|intl|mbstring|zip|opcache)$'
```

Expected: `PHP 8.1.x` printed; all six modules listed (one per line).

- [ ] **Step 8: Smoke-test the database container**

Run:
```bash
docker compose up -d db
sleep 5
docker compose exec db mariadb -ueqsl -peqsl -e 'SELECT VERSION();'
docker compose down
```

Expected: a `10.6.25-MariaDB` row.

- [ ] **Step 9: Commit**

```bash
git add .docker/ docker-compose.yml .dockerignore .editorconfig
git -c commit.gpgsign=false commit -m "build: docker dev environment (php 8.1 + mariadb 10.6 + nginx + mailhog)"
git log -1 --pretty='%an <%ae>'
```

Expected log line: `Robbi Nespu <robbinespu@gmail.com>`. No co-author trailer in body.

---

### Task 2: CakePHP 5 skeleton + Composer dependencies

**Files:**
- Create (via composer): `composer.json`, `composer.lock`, `bin/cake`, `config/*`, `src/*`, `templates/*`, `webroot/*`, `tests/*`
- Modify after generation: `composer.json` to pin extra deps
- Create: `config/app_local.php.example`

- [ ] **Step 1: Generate the CakePHP 5 skeleton**

Run (the composer container brings its own PHP, then we move into our php container):
```bash
docker run --rm -v "$PWD":/app -w /app composer:2 \
  create-project --prefer-dist --no-install --no-scripts cakephp/app:5.2.* .skeleton-tmp
rsync -a --exclude='.git' .skeleton-tmp/ ./
rm -rf .skeleton-tmp
```

Expected: `composer.json`, `bin/cake`, `src/Application.php`, `webroot/index.php`, etc. now exist at the repo root.

**Why `5.2.*` not `^5.0`:** `cakephp/app:^5.0` floats to the latest 5.x, which is 5.3.x at time of writing — and 5.3 requires PHP 8.2. The spec (§3) mandates PHP 8.1 strict, so we pin to `5.2.*` (the highest 5.x line still on PHP 8.1).

**Why `--no-scripts`:** the skeleton's post-install hook (`App\Console\Installer::postInstall`) tries to use `Cake\Utility\Security` to auto-generate a salt, but with `--no-install` the autoloader isn't ready yet, so the script crashes. We skip post-install here and seed the salt manually in Step 5.

- [ ] **Step 2: Add the four extra Composer requirements**

Edit `composer.json`'s `require` section to include (in addition to whatever the skeleton already pinned):

```json
"cakephp/migrations": "^4.0",
"cakephp/authentication": "^3.1",
"setasign/fpdf": "^1.8.6",
"ramsey/uuid": "^4.7"
```

- [ ] **Step 3: Install dependencies inside the php container**

Run:
```bash
docker compose run --rm php composer install
```

Expected: `vendor/` populated; no errors. `composer.lock` written.

- [ ] **Step 4: Write `config/app_local.php.example`**

This is the production-side template that the installer copies to `config/app_local.php` after the user fills in DB credentials. Leave the values as placeholders the installer will overwrite.

```php
<?php
return [
    'debug' => false,

    'Security' => [
        'salt' => '__SECURITY_SALT__',
    ],

    'Datasources' => [
        'default' => [
            'host'     => '__DB_HOST__',
            'port'     => '__DB_PORT__',
            'username' => '__DB_USER__',
            'password' => '__DB_PASS__',
            'database' => '__DB_NAME__',
            'url'      => null,
        ],
    ],

    'EmailTransport' => [
        'default' => [
            'className' => 'Cake\Mailer\Transport\SmtpTransport',
            'host'      => '__SMTP_HOST__',
            'port'      => 587,
            'username'  => '__SMTP_USER__',
            'password'  => '__SMTP_PASS__',
            'tls'       => true,
        ],
    ],

    'Email' => [
        'default' => [
            'transport' => 'default',
            'from'      => '__SMTP_FROM__',
        ],
    ],
];
```

- [ ] **Step 5: Adjust `config/app_local.php` for Docker dev**

Because Step 1 used `--no-scripts`, the auto-salt step was skipped. The skeleton ships `config/app_local.example.php` (note: the skeleton spells it `example`, our installer template at `config/app_local.php.example` is a different file). Copy it to `config/app_local.php` and:

1. Set the `Security.salt` to a 64-char dev value (any random string is fine — file is gitignored, prod salt is created fresh by the installer in Task 13).
2. Replace the `Datasources.default` block with one that reads from the env vars set in `docker-compose.yml`:

```php
'Datasources' => [
    'default' => [
        'host'     => env('DB_HOST', 'db'),
        'port'     => env('DB_PORT', '3306'),
        'username' => env('DB_USER', 'eqsl'),
        'password' => env('DB_PASS', 'eqsl'),
        'database' => env('DB_NAME', 'eqsl'),
        'url'      => env('DATABASE_URL'),
    ],
],
```

This file is gitignored (`.gitignore` already has `config/app_local.php`).

- [ ] **Step 6: Smoke-test the welcome page**

Run:
```bash
docker compose up -d
sleep 3
curl -sS http://localhost:8080/ -o /tmp/eqsl-home.html -w 'HTTP:%{http_code}\n'
grep -o 'CakePHP' /tmp/eqsl-home.html | head -1
docker compose down
```

Expected: `HTTP:200` and `CakePHP` matched in the body.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock bin/ config/ src/ templates/ webroot/ tests/ \
        plugins/ logs/ tmp/ phpunit.xml.dist .htaccess index.php \
        config/app_local.php.example
git -c commit.gpgsign=false commit -m "feat: scaffold cakephp 5 application with required composer dependencies"
```

Note: `config/app_local.php` and `vendor/` are excluded by `.gitignore`. Do not stage them.

---

### Task 3: Repository housekeeping & PHPUnit baseline

**Files:**
- Modify: `.gitignore`
- Modify: `phpunit.xml.dist`
- Create: `tests/bootstrap.php` (if skeleton missing)
- Create: `tests/TestCase/SmokeTest.php`

- [ ] **Step 1: Tighten `.gitignore`**

Append (some may already exist; ensure all are present without duplicating):

```
# CakePHP runtime
config/app_local.php
config/installed.lock

# Build artefacts
dist/
node_modules/
vendor/

# Testing
.phpunit.cache/
.phpunit.result.cache
coverage/
```

- [ ] **Step 2: Configure `phpunit.xml.dist` to use SQLite for tests**

Replace the `<php>` block in `phpunit.xml.dist` with:

```xml
<php>
    <ini name="memory_limit" value="-1"/>
    <env name="FIXTURE_SCHEMA_METADATA" value="./tests/schema.php"/>
    <env name="DATABASE_TEST_URL" value="sqlite:///:memory:"/>
    <env name="EMAIL_TRANSPORT_DEFAULT_URL" value="debug://localhost"/>
</php>
```

The `debug://` scheme maps to `Cake\Mailer\Transport\DebugTransport`, which silently swallows sent messages — the right behavior for tests. (`null:` is NOT a valid CakePHP 5 transport scheme and would throw `BadMethodCallException` the first time any test sends email.)

This means tests run against an in-memory SQLite database — no MySQL container needed for unit/integration tests.

- [ ] **Step 3: Write a smoke test**

Create `tests/TestCase/SmokeTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use Cake\TestSuite\TestCase;

final class SmokeTest extends TestCase
{
    public function testFrameworkBoots(): void
    {
        $this->assertSame('8.1', substr(PHP_VERSION, 0, 3), 'PHP runtime must be 8.1.x');
        $this->assertTrue(extension_loaded('gd'), 'GD must be available');
        $this->assertTrue(extension_loaded('pdo_sqlite'), 'pdo_sqlite must be available for tests');
    }
}
```

- [ ] **Step 4: Run the smoke test**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit --testdox tests/TestCase/SmokeTest.php
```

Expected: `OK (1 test, 3 assertions)`.

- [ ] **Step 5: Commit**

```bash
git add .gitignore phpunit.xml.dist tests/TestCase/SmokeTest.php
git -c commit.gpgsign=false commit -m "test: phpunit baseline with sqlite-in-memory test database"
```

---

## Phase B: Database schema

### Task 4: Migrations for all M1 tables

**Files:**
- Create: `config/Migrations/20260509000001_CreateUsers.php`
- Create: `config/Migrations/20260509000002_CreateGuestVisits.php`
- Create: `config/Migrations/20260509000003_CreateUploads.php`
- Create: `config/Migrations/20260509000004_CreateTemplates.php`
- Create: `config/Migrations/20260509000005_CreateCards.php`
- Create: `config/Migrations/20260509000006_CreateAppSettings.php`
- Create: `config/Migrations/20260509000007_CreatePasswordResets.php`

All migrations follow the spec's data model (Section 6). Order matters because of foreign keys.

- [ ] **Step 1: Write `20260509000001_CreateUsers.php`**

The `role` column is an ENUM on MariaDB but SQLite (used by the test fixture) has no native ENUM type, so we branch on the adapter:

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreateUsers extends AbstractMigration
{
    public function change(): void
    {
        $isSqlite = $this->getAdapter()->getAdapterType() === 'sqlite';
        $roleColumn = $isSqlite
            ? ['type' => 'string', 'options' => ['limit' => 16, 'default' => 'user']]
            : ['type' => 'enum', 'options' => ['values' => ['admin', 'user'], 'default' => 'user']];

        $this->table('users')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('role', $roleColumn['type'], $roleColumn['options'])
            ->addColumn('callsign', 'string', ['limit' => 20])
            ->addColumn('qth', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('grid_square', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('bio', 'text', ['null' => true])
            ->addColumn('email_verified_at', 'datetime', ['null' => true])
            ->addColumn('last_login_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addIndex('email', ['unique' => true])
            ->addIndex('callsign')
            ->create();
    }
}
```

The `'admin'`/`'user'` invariant is enforced at the ORM layer in Task 5's `UsersTable::validationDefault()` either way, so SQLite's lack of native ENUM is harmless.

- [ ] **Step 2: Write `20260509000002_CreateGuestVisits.php`**

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreateGuestVisits extends AbstractMigration
{
    public function change(): void
    {
        $this->table('guest_visits')
            ->addColumn('session_token', 'char', ['limit' => 43])
            ->addColumn('ip_hash', 'char', ['limit' => 64])
            ->addColumn('user_agent_hash', 'char', ['limit' => 64])
            ->addColumn('created_at', 'datetime')
            ->addColumn('last_seen_at', 'datetime')
            ->addIndex('session_token', ['unique' => true])
            ->addIndex('ip_hash')
            ->create();
    }
}
```

- [ ] **Step 3: Write `20260509000003_CreateUploads.php`**

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Background images. The unique sha256_hash constraint deduplicates the
 * physical file on disk: when two users upload the same image, only one
 * `uploads` row exists, owned by the first uploader. Subsequent users'
 * `cards` rows reference the same upload — `uploads.user_id` is "who
 * first introduced this background to the system", NOT "who has rights
 * to use it." On hard-delete of the first uploader, `user_id` becomes
 * NULL but the row remains so other users' cards keep rendering.
 */
final class CreateUploads extends AbstractMigration
{
    public function change(): void
    {
        $this->table('uploads')
            ->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('guest_visit_id', 'integer', ['null' => true])
            ->addColumn('original_filename', 'string', ['limit' => 255])
            ->addColumn('storage_path', 'string', ['limit' => 255])
            ->addColumn('mime_type', 'string', ['limit' => 60])
            ->addColumn('width_px', 'integer')
            ->addColumn('height_px', 'integer')
            ->addColumn('file_size_bytes', 'integer')
            ->addColumn('sha256_hash', 'char', ['limit' => 64])
            ->addColumn('created_at', 'datetime')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addIndex('sha256_hash', ['unique' => true])
            ->addIndex('user_id')
            ->addIndex('guest_visit_id')
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addForeignKey('guest_visit_id', 'guest_visits', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
```

- [ ] **Step 4: Write `20260509000004_CreateTemplates.php`**

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreateTemplates extends AbstractMigration
{
    public function change(): void
    {
        $this->table('templates')
            ->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('canvas_width', 'integer')
            ->addColumn('canvas_height', 'integer')
            ->addColumn('layout_json', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
            ->addColumn('thumbnail_path', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('is_public', 'boolean', ['default' => false])
            ->addColumn('is_approved', 'boolean', ['default' => false])
            ->addColumn('is_system', 'boolean', ['default' => false])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addIndex('user_id')
            ->addIndex(['is_public', 'is_approved'])
            ->addIndex('is_system')
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
```

Add `use Migrations\Db\Adapter\MysqlAdapter;` at the top of the file. (Use the `Migrations\` namespace, not `Phinx\` — `cakephp/migrations` ships its own adapter; coupling to the direct dependency rather than a transitive one keeps us safe across major version bumps.)

`templates.user_id` deletes as `SET_NULL` (matching spec §6.4 "NULL = system template"). A `CASCADE` here would deadlock with `cards.template_id` `RESTRICT` whenever an admin hard-deletes a user who owns a template that has at least one card. (Use the `Migrations\` namespace, not `Phinx\` — `cakephp/migrations` ships its own adapter; coupling to the direct dependency rather than a transitive one keeps us safe across major version bumps.)

- [ ] **Step 5: Write `20260509000005_CreateCards.php`**

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreateCards extends AbstractMigration
{
    public function change(): void
    {
        $this->table('cards')
            ->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('guest_visit_id', 'integer', ['null' => true])
            ->addColumn('qso_id', 'integer', ['null' => true]) // populated in M2
            ->addColumn('template_id', 'integer')
            ->addColumn('upload_id', 'integer')
            ->addColumn('qso_data_json', 'text')
            ->addColumn('png_path', 'string', ['limit' => 255])
            ->addColumn('pdf_path', 'string', ['limit' => 255])
            ->addColumn('share_slug', 'char', ['limit' => 43, 'null' => true])
            ->addColumn('share_password_hash', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('share_revoked_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addIndex('user_id')
            ->addIndex('guest_visit_id')
            ->addIndex('template_id')
            ->addIndex('upload_id')
            ->addIndex('share_slug', ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addForeignKey('guest_visit_id', 'guest_visits', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addForeignKey('template_id', 'templates', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('upload_id', 'uploads', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
```

`qso_id` has no FK in M1 because the `qsos` table is M2. The FK constraint is added in M2's first migration.

- [ ] **Step 6: Write `20260509000006_CreateAppSettings.php`**

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreateAppSettings extends AbstractMigration
{
    public function change(): void
    {
        $this->table('app_settings', ['id' => false, 'primary_key' => 'key'])
            ->addColumn('key', 'string', ['limit' => 80])
            ->addColumn('value', 'text')
            ->addColumn('updated_at', 'datetime')
            ->create();
    }
}
```

- [ ] **Step 7: Write `20260509000007_CreatePasswordResets.php`**

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class CreatePasswordResets extends AbstractMigration
{
    public function change(): void
    {
        $this->table('password_resets')
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('token_hash', 'char', ['limit' => 64])
            ->addColumn('expires_at', 'datetime')
            ->addColumn('used_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex('email')
            ->addIndex('token_hash', ['unique' => true])
            ->create();
    }
}
```

- [ ] **Step 8: Run migrations against the dev MariaDB to confirm they apply cleanly**

```bash
docker compose up -d db
sleep 5
docker compose run --rm php bin/cake migrations migrate
docker compose run --rm php bin/cake migrations status
docker compose down
```

Expected: every migration listed with status `up`.

- [ ] **Step 9: Run migrations against the test SQLite to confirm they apply there too**

```bash
docker compose run --rm php vendor/bin/phpunit --filter testFrameworkBoots tests/TestCase/SmokeTest.php
```

The test's fixture loader will run migrations once it sees fixtures (next task). For now this just makes sure nothing regressed.

- [ ] **Step 10: Commit**

```bash
git add config/Migrations/
git -c commit.gpgsign=false commit -m "feat(db): m1 migrations (users, guest_visits, uploads, templates, cards, app_settings, password_resets)"
```

---

### Task 5: ORM Table classes + entities

**Files:**
- Create: `src/Model/Table/UsersTable.php`
- Create: `src/Model/Entity/User.php`
- Create: `src/Model/Table/GuestVisitsTable.php`
- Create: `src/Model/Entity/GuestVisit.php`
- Create: `src/Model/Table/UploadsTable.php`
- Create: `src/Model/Entity/Upload.php`
- Create: `src/Model/Table/TemplatesTable.php`
- Create: `src/Model/Entity/Template.php`
- Create: `src/Model/Table/CardsTable.php`
- Create: `src/Model/Entity/Card.php`
- Create: `src/Model/Table/AppSettingsTable.php`
- Create: `src/Model/Entity/AppSetting.php`
- Create: `src/Model/Table/PasswordResetsTable.php`
- Create: `src/Model/Entity/PasswordReset.php`
- Create: `tests/TestCase/Model/Table/UsersTableTest.php`
- Create: `tests/Fixture/UsersFixture.php` (and one fixture per table)

Bake can scaffold these, but we write them by hand to control validation rules.

- [ ] **Step 1: Write the failing test for `UsersTable` validation**

`tests/TestCase/Model/Table/UsersTableTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class UsersTableTest extends TestCase
{
    protected array $fixtures = ['app.Users'];

    public function testCallsignRequired(): void
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $entity = $users->newEntity([
            'name' => 'Robbi',
            'email' => 'r@example.com',
            'password_hash' => 'x',
            'role' => 'admin',
            'callsign' => '',
        ]);
        $this->assertNotEmpty($entity->getErrors()['callsign'] ?? []);
    }

    public function testEmailMustBeUnique(): void
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $users->saveOrFail($users->newEntity([
            'name' => 'A', 'email' => 'a@x.com', 'password_hash' => 'h',
            'role' => 'user', 'callsign' => 'AA1AA',
        ]));
        $dupe = $users->newEntity([
            'name' => 'B', 'email' => 'a@x.com', 'password_hash' => 'h',
            'role' => 'user', 'callsign' => 'BB1BB',
        ]);
        $this->assertNotTrue($users->save($dupe));
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Model/Table/UsersTableTest.php
```

Expected: failure ("Class App\Model\Table\UsersTable not found" or similar).

- [ ] **Step 3: Write `src/Model/Entity/User.php`**

```php
<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\Entity;

class User extends Entity
{
    protected array $_accessible = [
        'name' => true, 'email' => true, 'role' => true,
        'callsign' => true, 'qth' => true, 'grid_square' => true, 'bio' => true,
        'password' => true, // virtual; mapped to password_hash via mutator
        'email_verified_at' => true, 'last_login_at' => true,
    ];

    protected array $_hidden = ['password_hash', 'password'];

    protected function _setPassword(string $plain): ?string
    {
        if ($plain === '') {
            return null;
        }
        $this->set('password_hash', (new DefaultPasswordHasher())->hash($plain));
        return null;
    }
}
```

- [ ] **Step 4: Write `src/Model/Table/UsersTable.php`**

```php
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class UsersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('users');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')->maxLength('name', 120)->notEmptyString('name')
            ->email('email')->notEmptyString('email')
            ->scalar('callsign')->maxLength('callsign', 20)->notEmptyString('callsign')
            ->inList('role', ['admin', 'user'])
            ->scalar('qth')->maxLength('qth', 120)->allowEmptyString('qth')
            ->scalar('grid_square')->maxLength('grid_square', 10)->allowEmptyString('grid_square')
            ->scalar('bio')->allowEmptyString('bio');
        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->isUnique(['email']));
        return $rules;
    }
}
```

- [ ] **Step 5: Write `tests/Fixture/UsersFixture.php`**

```php
<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UsersFixture extends TestFixture
{
    public array $records = [];
}
```

(Records empty: tests insert their own.)

- [ ] **Step 6: Run the test again, confirm it passes**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Model/Table/UsersTableTest.php
```

Expected: `OK (2 tests, 2+ assertions)`.

- [ ] **Step 7: Repeat for the other six tables**

For each of `GuestVisits`, `Uploads`, `Templates`, `Cards`, `AppSettings`, `PasswordResets` write a Table + Entity pair. Each Table sets `setTable(...)`, primary key, `Timestamp` behavior where applicable, and a minimal `validationDefault`.

`CardsTable::validationDefault` MUST require exactly one of `user_id` / `guest_visit_id`:

```php
public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
{
    $rules->add(function (\Cake\Datasource\EntityInterface $entity): bool {
        $hasUser = !empty($entity->get('user_id'));
        $hasGuest = !empty($entity->get('guest_visit_id'));
        return ($hasUser xor $hasGuest);
    }, 'ownerExclusive', ['errorField' => 'user_id', 'message' => 'Card must have either user_id OR guest_visit_id, not both.']);
    return $rules;
}
```

`UploadsTable` gets the same `ownerExclusive` rule.

For each Table also create an empty fixture file.

- [ ] **Step 8: Add associations**

In `UsersTable::initialize`:
```php
$this->hasMany('Cards');
$this->hasMany('Templates');
$this->hasMany('Uploads');
```

In `CardsTable::initialize`:
```php
$this->belongsTo('Users');
$this->belongsTo('GuestVisits');
$this->belongsTo('Templates');
$this->belongsTo('Uploads');
```

In `TemplatesTable::initialize`:
```php
$this->belongsTo('Users');
$this->hasMany('Cards');
```

In `UploadsTable::initialize`:
```php
$this->belongsTo('Users');
$this->belongsTo('GuestVisits');
```

- [ ] **Step 9: Run all tests**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit
```

Expected: green across all Model tests.

- [ ] **Step 10: Commit**

```bash
git add src/Model/ tests/TestCase/Model/ tests/Fixture/
git -c commit.gpgsign=false commit -m "feat(orm): table & entity classes for m1 tables with validation rules"
```

---

## Phase C: Domain services

### Task 6: PlaceholderResolver service (TDD)

The `PlaceholderResolver` translates strings like `{callsign}`, `{date_utc:Y-m-d}`, `{rst_sent}` into rendered values from a QSO data array. Used by `CardRenderer`.

**Files:**
- Create: `src/Service/PlaceholderResolver.php`
- Create: `tests/TestCase/Service/PlaceholderResolverTest.php`

- [ ] **Step 1: Write the failing test**

`tests/TestCase/Service/PlaceholderResolverTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\PlaceholderResolver;
use Cake\TestSuite\TestCase;

final class PlaceholderResolverTest extends TestCase
{
    public function testSimpleField(): void
    {
        $r = new PlaceholderResolver();
        $this->assertSame('W1AW', $r->resolve('{callsign}', ['callsign' => 'W1AW']));
    }

    public function testDateFormatting(): void
    {
        $r = new PlaceholderResolver();
        $out = $r->resolve('{qso_datetime_utc:Y-m-d}', ['qso_datetime_utc' => '2026-05-09T14:32:00Z']);
        $this->assertSame('2026-05-09', $out);
    }

    public function testMultipleFieldsInOneString(): void
    {
        $r = new PlaceholderResolver();
        $out = $r->resolve('{callsign} on {band}', ['callsign' => 'W1AW', 'band' => '20m']);
        $this->assertSame('W1AW on 20m', $out);
    }

    public function testMissingFieldRendersEmpty(): void
    {
        $r = new PlaceholderResolver();
        $this->assertSame('', (new PlaceholderResolver())->resolve('{nope}', []));
    }

    public function testCustomLiteralPassesThrough(): void
    {
        $r = new PlaceholderResolver();
        $this->assertSame('Hello W1AW', $r->resolve('Hello {callsign}', ['callsign' => 'W1AW']));
    }
}
```

- [ ] **Step 2: Confirm tests fail**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/PlaceholderResolverTest.php
```

Expected: error "Class App\Service\PlaceholderResolver not found".

- [ ] **Step 3: Implement `src/Service/PlaceholderResolver.php`**

```php
<?php
declare(strict_types=1);

namespace App\Service;

final class PlaceholderResolver
{
    public function resolve(string $template, array $data): string
    {
        return preg_replace_callback(
            '/\{(?<key>[a-z_][a-z0-9_]*)(?::(?<fmt>[^}]+))?\}/i',
            function (array $m) use ($data): string {
                $key = $m['key'];
                if (!array_key_exists($key, $data)) {
                    return '';
                }
                $value = $data[$key];
                if (isset($m['fmt']) && $m['fmt'] !== '') {
                    if ($value instanceof \DateTimeInterface) {
                        return $value->format($m['fmt']);
                    }
                    if (is_string($value) && strtotime($value) !== false) {
                        return (new \DateTimeImmutable($value))->format($m['fmt']);
                    }
                }
                return (string)$value;
            },
            $template
        ) ?? '';
    }
}
```

- [ ] **Step 4: Confirm tests pass**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/PlaceholderResolverTest.php
```

Expected: `OK (5 tests, 5 assertions)`.

- [ ] **Step 5: Commit**

```bash
git add src/Service/PlaceholderResolver.php tests/TestCase/Service/PlaceholderResolverTest.php
git -c commit.gpgsign=false commit -m "feat(service): placeholder resolver for card field substitution"
```

---

### Task 7: ImageOptimizer service (TDD)

Resizes uploads to a max bounding box, strips EXIF, re-encodes as JPEG.

**Files:**
- Create: `src/Service/ImageOptimizer.php`
- Create: `tests/TestCase/Service/ImageOptimizerTest.php`
- Create: `tests/Fixture/files/sample-3000x2000.jpg` (small fixture; generate in test setup)

- [ ] **Step 1: Write the failing test**

`tests/TestCase/Service/ImageOptimizerTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\ImageOptimizer;
use Cake\TestSuite\TestCase;

final class ImageOptimizerTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/eqsl-img-test-' . uniqid();
        mkdir($this->tmp, 0o775, true);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->tmp . '/*') ?: []);
        @rmdir($this->tmp);
        parent::tearDown();
    }

    public function testResizesLargeImageToBoundingBox(): void
    {
        $src = $this->tmp . '/big.jpg';
        $img = imagecreatetruecolor(3000, 2000);
        imagefill($img, 0, 0, imagecolorallocate($img, 100, 150, 200));
        imagejpeg($img, $src, 90);
        imagedestroy($img);

        $optimizer = new ImageOptimizer(maxWidth: 2000, maxHeight: 1500, quality: 82);
        $dst = $this->tmp . '/out.jpg';
        $info = $optimizer->optimize($src, $dst);

        [$w, $h] = getimagesize($dst);
        $this->assertLessThanOrEqual(2000, $w);
        $this->assertLessThanOrEqual(1500, $h);
        $this->assertSame('image/jpeg', $info['mime_type']);
        $this->assertGreaterThan(0, $info['file_size_bytes']);
    }

    public function testReturnsOriginalDimensionsWhenSmaller(): void
    {
        $src = $this->tmp . '/small.jpg';
        $img = imagecreatetruecolor(800, 600);
        imagejpeg($img, $src, 90);
        imagedestroy($img);

        $dst = $this->tmp . '/out.jpg';
        $info = (new ImageOptimizer(maxWidth: 2000, maxHeight: 1500))->optimize($src, $dst);
        $this->assertSame(800, $info['width_px']);
        $this->assertSame(600, $info['height_px']);
    }

    public function testRejectsNonImageContent(): void
    {
        $src = $this->tmp . '/bad.jpg';
        file_put_contents($src, "not an image");

        $this->expectException(\RuntimeException::class);
        (new ImageOptimizer())->optimize($src, $this->tmp . '/out.jpg');
    }
}
```

- [ ] **Step 2: Confirm tests fail**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/ImageOptimizerTest.php
```

Expected: failure / class missing.

- [ ] **Step 3: Implement `src/Service/ImageOptimizer.php`**

```php
<?php
declare(strict_types=1);

namespace App\Service;

final class ImageOptimizer
{
    public function __construct(
        private int $maxWidth = 2000,
        private int $maxHeight = 1500,
        private int $quality = 82,
    ) {}

    /**
     * @return array{width_px:int,height_px:int,file_size_bytes:int,mime_type:string,sha256_hash:string}
     */
    public function optimize(string $sourcePath, string $destinationPath): array
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new \RuntimeException('File is not a recognised image.');
        }

        [$origW, $origH, $type] = $info;
        $img = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            default        => throw new \RuntimeException('Unsupported image type: ' . image_type_to_mime_type($type)),
        };
        if ($img === false) {
            throw new \RuntimeException('Failed to decode image.');
        }

        $scale = min(1.0, $this->maxWidth / $origW, $this->maxHeight / $origH);
        $newW = (int)round($origW * $scale);
        $newH = (int)round($origH * $scale);

        if ($scale < 1.0) {
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($img);
            $img = $resized;
        }

        // Re-encode strips EXIF and any embedded payload.
        if (!imagejpeg($img, $destinationPath, $this->quality)) {
            imagedestroy($img);
            throw new \RuntimeException('Failed to write optimized image.');
        }
        imagedestroy($img);

        return [
            'width_px'        => $newW,
            'height_px'       => $newH,
            'file_size_bytes' => filesize($destinationPath),
            'mime_type'       => 'image/jpeg',
            'sha256_hash'     => hash_file('sha256', $destinationPath),
        ];
    }
}
```

- [ ] **Step 4: Confirm tests pass**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/ImageOptimizerTest.php
```

Expected: `OK (3 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add src/Service/ImageOptimizer.php tests/TestCase/Service/ImageOptimizerTest.php
git -c commit.gpgsign=false commit -m "feat(service): image optimizer (resize + exif strip + jpeg re-encode)"
```

---

### Task 8: CardRenderer — PNG path (TDD)

Renders the card by reading a template's `layout_json` and drawing each field onto a copy of the background using `imagettftext`.

**Files:**
- Create: `src/Service/CardRenderer.php`
- Create: `tests/TestCase/Service/CardRendererTest.php`
- Create: `webroot/files/fonts/Inter-Regular.ttf` (download in this task; see Step 1)
- Create: `webroot/files/fonts/Inter-Bold.ttf`
- Create: `webroot/files/fonts/RobotoSlab-Regular.ttf`
- Create: `webroot/files/fonts/JetBrainsMono-Regular.ttf`
- Create: `webroot/files/fonts/Cinzel-Regular.ttf`
- Create: `webroot/files/fonts/SIL-OFL.txt` (license)
- Create: `webroot/files/fonts/.gitkeep`

- [ ] **Step 1: Bundle the four fonts**

Download from Google Fonts (all SIL OFL) and place in `webroot/files/fonts/`. Add a copy of the OFL license at `webroot/files/fonts/SIL-OFL.txt`.

```bash
mkdir -p webroot/files/fonts
cd webroot/files/fonts
curl -sSL -o Inter-Regular.ttf            https://fonts.gstatic.com/s/inter/v18/UcCO3FwrK3iLTeHuS_nVMrMxCp50Sjw.ttf
curl -sSL -o Inter-Bold.ttf               https://fonts.gstatic.com/s/inter/v18/UcCO3FwrK3iLTeHuS_nVMrMxCp50ojvNeQ.ttf
curl -sSL -o RobotoSlab-Regular.ttf       https://fonts.gstatic.com/s/robotoslab/v34/BngMUXZYTXPIvIBgJJSb6s3BzlRRfKOFbvjojIWf9w.ttf
curl -sSL -o JetBrainsMono-Regular.ttf    https://fonts.gstatic.com/s/jetbrainsmono/v22/tDbY2o-flEEny0FZhsfKu5WU4xD-IQ-PuZJJXxfpAO_BoQ.ttf
curl -sSL -o Cinzel-Regular.ttf           https://fonts.gstatic.com/s/cinzel/v23/8vIJ7ww63mVu7gtR-kwKxNvkNOjw-tbnYa3IcA.ttf
curl -sSL -o SIL-OFL.txt                  https://opensource.org/licenses/OFL-1.1
cd -
```

(The Google Fonts URLs above are static. If any 404, source the equivalent from `https://fonts.google.com/`.)

Adjust `.gitignore` so these specific files are tracked:

```
!webroot/files/fonts/*.ttf
!webroot/files/fonts/SIL-OFL.txt
```

- [ ] **Step 2: Write the failing renderer test**

`tests/TestCase/Service/CardRendererTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\CardRenderer;
use Cake\TestSuite\TestCase;

final class CardRendererTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/eqsl-render-test-' . uniqid();
        mkdir($this->tmp, 0o775, true);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->tmp . '/*') ?: []);
        @rmdir($this->tmp);
        parent::tearDown();
    }

    public function testRendersPngWithFieldsAtCorrectPositions(): void
    {
        $bg = $this->tmp . '/bg.jpg';
        $img = imagecreatetruecolor(1500, 1000);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagejpeg($img, $bg);
        imagedestroy($img);

        $template = [
            'canvas_width' => 1500,
            'canvas_height' => 1000,
            'fields' => [
                ['placeholder' => '{callsign}', 'x' => 100, 'y' => 200,
                 'font' => 'Inter-Bold.ttf', 'size' => 96, 'color' => '#000000', 'rotation' => 0],
                ['placeholder' => 'Confirming QSO with {operator_name}', 'x' => 100, 'y' => 350,
                 'font' => 'Inter-Regular.ttf', 'size' => 36, 'color' => '#222222', 'rotation' => 0],
            ],
        ];
        $qso = ['callsign' => 'W1AW', 'operator_name' => 'Hiram Maxim'];

        $out = $this->tmp . '/card.png';
        $renderer = new CardRenderer(WWW_ROOT . 'files/fonts/');
        $info = $renderer->renderPng($template, $bg, $qso, $out);

        $this->assertFileExists($out);
        [$w, $h] = getimagesize($out);
        $this->assertSame(1500, $w);
        $this->assertSame(1000, $h);
        $this->assertSame('image/png', $info['mime_type']);
    }

    public function testRejectsUnknownFont(): void
    {
        $bg = $this->tmp . '/bg.jpg';
        $img = imagecreatetruecolor(100, 100);
        imagejpeg($img, $bg);
        imagedestroy($img);

        $template = [
            'canvas_width' => 100, 'canvas_height' => 100,
            'fields' => [['placeholder' => 'x', 'x' => 10, 'y' => 50,
                          'font' => 'NotAFont.ttf', 'size' => 12, 'color' => '#000', 'rotation' => 0]],
        ];

        $this->expectException(\RuntimeException::class);
        (new CardRenderer(WWW_ROOT . 'files/fonts/'))
            ->renderPng($template, $bg, [], $this->tmp . '/card.png');
    }
}
```

- [ ] **Step 3: Implement `src/Service/CardRenderer.php` (PNG only for now)**

```php
<?php
declare(strict_types=1);

namespace App\Service;

final class CardRenderer
{
    public function __construct(
        private string $fontDir,
        private ?PlaceholderResolver $resolver = null,
    ) {
        $this->resolver = $resolver ?? new PlaceholderResolver();
        $this->fontDir = rtrim($this->fontDir, '/') . '/';
    }

    /**
     * @return array{width_px:int,height_px:int,file_size_bytes:int,mime_type:string}
     */
    public function renderPng(array $template, string $backgroundPath, array $qso, string $destinationPath): array
    {
        $width = (int)$template['canvas_width'];
        $height = (int)$template['canvas_height'];

        $canvas = imagecreatetruecolor($width, $height);

        $bgInfo = @getimagesize($backgroundPath);
        if ($bgInfo === false) {
            throw new \RuntimeException('Background is not a valid image.');
        }
        $bg = match ($bgInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($backgroundPath),
            IMAGETYPE_PNG  => imagecreatefrompng($backgroundPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($backgroundPath),
            default        => throw new \RuntimeException('Unsupported background image type.'),
        };
        imagecopyresampled($canvas, $bg, 0, 0, 0, 0, $width, $height, imagesx($bg), imagesy($bg));
        imagedestroy($bg);

        foreach ($template['fields'] as $field) {
            $this->drawField($canvas, $field, $qso);
        }

        if (!imagepng($canvas, $destinationPath, 6)) {
            imagedestroy($canvas);
            throw new \RuntimeException('Failed to write rendered PNG.');
        }
        imagedestroy($canvas);

        return [
            'width_px'        => $width,
            'height_px'       => $height,
            'file_size_bytes' => filesize($destinationPath),
            'mime_type'       => 'image/png',
        ];
    }

    private function drawField(\GdImage $canvas, array $field, array $qso): void
    {
        $text = $this->resolver->resolve((string)$field['placeholder'], $qso);
        if ($text === '') {
            return;
        }

        $fontPath = $this->fontDir . basename((string)$field['font']);
        if (!is_file($fontPath)) {
            throw new \RuntimeException("Font not bundled: {$field['font']}");
        }

        [$r, $g, $b] = $this->hexToRgb((string)$field['color']);
        $color = imagecolorallocate($canvas, $r, $g, $b);

        imagettftext(
            $canvas,
            (float)$field['size'],
            (float)($field['rotation'] ?? 0),
            (int)$field['x'],
            (int)$field['y'],
            $color,
            $fontPath,
            $text
        );
    }

    /** @return array{0:int,1:int,2:int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            (int)hexdec(substr($hex, 0, 2)),
            (int)hexdec(substr($hex, 2, 2)),
            (int)hexdec(substr($hex, 4, 2)),
        ];
    }
}
```

- [ ] **Step 4: Confirm tests pass**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/CardRendererTest.php
```

Expected: `OK (2 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add src/Service/CardRenderer.php tests/TestCase/Service/CardRendererTest.php \
        webroot/files/fonts/ .gitignore
git -c commit.gpgsign=false commit -m "feat(service): card renderer png path with bundled OFL fonts"
```

---

### Task 9: CardRenderer — PDF wrapper (TDD)

Wraps the rendered PNG into a single-page PDF via FPDF.

**Files:**
- Modify: `src/Service/CardRenderer.php`
- Modify: `tests/TestCase/Service/CardRendererTest.php`

- [ ] **Step 1: Add the failing PDF test**

Append to `CardRendererTest`:

```php
public function testWrapsPngIntoPdf(): void
{
    $bg = $this->tmp . '/bg.jpg';
    $img = imagecreatetruecolor(1500, 1000);
    imagejpeg($img, $bg);
    imagedestroy($img);

    $template = ['canvas_width' => 1500, 'canvas_height' => 1000, 'fields' => []];
    $renderer = new CardRenderer(WWW_ROOT . 'files/fonts/');

    $png = $this->tmp . '/card.png';
    $pdf = $this->tmp . '/card.pdf';
    $renderer->renderPng($template, $bg, [], $png);
    $renderer->wrapPdf($png, $pdf, $template['canvas_width'], $template['canvas_height']);

    $this->assertFileExists($pdf);
    $bytes = file_get_contents($pdf);
    $this->assertStringStartsWith('%PDF-', (string)$bytes);
}
```

- [ ] **Step 2: Confirm test fails**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit --filter testWrapsPngIntoPdf
```

Expected: failure ("Method wrapPdf not found").

- [ ] **Step 3: Add the `wrapPdf` method**

In `src/Service/CardRenderer.php` add:

```php
public function wrapPdf(string $pngPath, string $destinationPath, int $widthPx, int $heightPx): void
{
    // Convert pixels @ 300 DPI to mm: 1 inch = 25.4 mm; px / 300 * 25.4
    $widthMm  = $widthPx  / 300 * 25.4;
    $heightMm = $heightPx / 300 * 25.4;

    $pdf = new \FPDF($widthMm > $heightMm ? 'L' : 'P', 'mm', [$widthMm, $heightMm]);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $pdf->Image($pngPath, 0, 0, $widthMm, $heightMm, 'PNG');
    $pdf->Output('F', $destinationPath);
}
```

- [ ] **Step 4: Run all renderer tests**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/CardRendererTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add src/Service/CardRenderer.php tests/TestCase/Service/CardRendererTest.php
git -c commit.gpgsign=false commit -m "feat(service): card renderer pdf wrapper via fpdf"
```

---

## Phase D: Web installer wizard

### Task 10: InstallationCheckMiddleware + InstallController scaffold

The middleware checks for the presence of `config/installed.lock`. If missing, every URL except `/install/*` and `/health` is redirected to `/install`.

**Files:**
- Create: `src/Middleware/InstallationCheckMiddleware.php`
- Create: `src/Controller/InstallController.php`
- Modify: `src/Application.php` to register the middleware
- Modify: `config/routes.php` to add install routes
- Create: `templates/Install/index.php` (placeholder, fleshed out in Tasks 11–13)
- Create: `tests/TestCase/Middleware/InstallationCheckMiddlewareTest.php`

- [ ] **Step 1: Write the failing middleware test**

`tests/TestCase/Middleware/InstallationCheckMiddlewareTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\InstallationCheckMiddleware;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\Response;
use Psr\Http\Server\RequestHandlerInterface;

final class InstallationCheckMiddlewareTest extends TestCase
{
    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockFile = sys_get_temp_dir() . '/eqsl-installed-' . uniqid() . '.lock';
    }

    protected function tearDown(): void
    {
        @unlink($this->lockFile);
        parent::tearDown();
    }

    public function testRedirectsToInstallWhenLockMissing(): void
    {
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/some-page']);
        $handler = $this->makeHandler();

        $resp = $mw->process($req, $handler);
        $this->assertSame(302, $resp->getStatusCode());
        $this->assertSame('/install', $resp->getHeaderLine('Location'));
    }

    public function testAllowsInstallRoutesWhenLockMissing(): void
    {
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/install/database']);
        $handler = $this->makeHandler();

        $resp = $mw->process($req, $handler);
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function testPassesThroughWhenLockExists(): void
    {
        touch($this->lockFile);
        $mw = new InstallationCheckMiddleware($this->lockFile);
        $req = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/some-page']);
        $handler = $this->makeHandler();

        $resp = $mw->process($req, $handler);
        $this->assertSame(200, $resp->getStatusCode());
    }

    private function makeHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $r): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200);
            }
        };
    }
}
```

- [ ] **Step 2: Confirm test fails**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Middleware/InstallationCheckMiddlewareTest.php
```

Expected: class missing.

- [ ] **Step 3: Implement `src/Middleware/InstallationCheckMiddleware.php`**

```php
<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class InstallationCheckMiddleware implements MiddlewareInterface
{
    public function __construct(private string $lockFilePath)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (file_exists($this->lockFilePath)) {
            return $handler->handle($request);
        }
        if (str_starts_with($path, '/install') || $path === '/health') {
            return $handler->handle($request);
        }
        return (new Response())->withStatus(302)->withHeader('Location', '/install');
    }
}
```

- [ ] **Step 4: Confirm middleware tests pass**

Run:
```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Middleware/InstallationCheckMiddlewareTest.php
```

Expected: green.

- [ ] **Step 5: Register middleware in `src/Application.php`**

Inside the `middleware()` method, before `RoutingMiddleware`:

```php
->add(new \App\Middleware\InstallationCheckMiddleware(CONFIG . 'installed.lock'))
```

- [ ] **Step 6: Add install routes**

In `config/routes.php` (top-level scope):

```php
$routes->scope('/install', function (\Cake\Routing\RouteBuilder $builder): void {
    $builder->connect('/', ['controller' => 'Install', 'action' => 'index'])->setMethods(['GET']);
    $builder->connect('/system-check', ['controller' => 'Install', 'action' => 'systemCheck'])->setMethods(['GET']);
    $builder->connect('/database', ['controller' => 'Install', 'action' => 'database'])->setMethods(['GET','POST']);
    $builder->connect('/migrate', ['controller' => 'Install', 'action' => 'migrate'])->setMethods(['POST']);
    $builder->connect('/admin', ['controller' => 'Install', 'action' => 'admin'])->setMethods(['GET','POST']);
    $builder->connect('/complete', ['controller' => 'Install', 'action' => 'complete'])->setMethods(['GET']);
});
```

- [ ] **Step 7: Stub `src/Controller/InstallController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;

class InstallController extends Controller
{
    public function index(): void
    {
        $this->set('title', 'Welcome');
    }

    public function systemCheck(): void {}
    public function database(): void {}
    public function migrate(): void {}
    public function admin(): void {}
    public function complete(): void {}
}
```

Each action is fleshed out in Tasks 11–13.

- [ ] **Step 8: Stub view templates**

Create `templates/Install/index.php`:
```php
<h1><?= h($title ?? 'Install') ?></h1>
<p>The setup wizard will configure your database and create the admin account.</p>
<a href="/install/system-check" class="btn btn-primary">Begin</a>
```

(Other install views are written in subsequent tasks.)

- [ ] **Step 9: Commit**

```bash
git add src/Middleware/ src/Controller/InstallController.php src/Application.php \
        config/routes.php templates/Install/index.php \
        tests/TestCase/Middleware/
git -c commit.gpgsign=false commit -m "feat(install): installation check middleware + install controller scaffold"
```

---

### Task 11: Installer step 1 — system checks

**Files:**
- Modify: `src/Controller/InstallController.php` (`systemCheck` action)
- Create: `src/Service/SystemCheck.php`
- Create: `tests/TestCase/Service/SystemCheckTest.php`
- Create: `templates/Install/system_check.php`

- [ ] **Step 1: Write the failing test**

`tests/TestCase/Service/SystemCheckTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\SystemCheck;
use Cake\TestSuite\TestCase;

final class SystemCheckTest extends TestCase
{
    public function testReportsAllRequirements(): void
    {
        $check = new SystemCheck();
        $report = $check->run();
        $this->assertArrayHasKey('php_version', $report);
        $this->assertArrayHasKey('gd', $report);
        $this->assertArrayHasKey('pdo_mysql', $report);
        $this->assertArrayHasKey('writable_config', $report);
        $this->assertArrayHasKey('writable_files', $report);
        foreach ($report as $row) {
            $this->assertArrayHasKey('ok', $row);
            $this->assertArrayHasKey('detail', $row);
        }
    }

    public function testPassesOnCurrentEnvironment(): void
    {
        $report = (new SystemCheck())->run();
        $this->assertTrue($report['php_version']['ok']);
        $this->assertTrue($report['gd']['ok']);
    }
}
```

- [ ] **Step 2: Confirm test fails, then implement**

`src/Service/SystemCheck.php`:

```php
<?php
declare(strict_types=1);

namespace App\Service;

final class SystemCheck
{
    /** @return array<string, array{ok:bool, detail:string}> */
    public function run(): array
    {
        $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        return [
            'php_version' => [
                'ok' => $phpOk,
                'detail' => 'Detected PHP ' . PHP_VERSION . '; require >= 8.1',
            ],
            'gd' => [
                'ok' => extension_loaded('gd'),
                'detail' => extension_loaded('gd') ? 'GD enabled' : 'GD extension missing',
            ],
            'pdo_mysql' => [
                'ok' => extension_loaded('pdo_mysql'),
                'detail' => extension_loaded('pdo_mysql') ? 'pdo_mysql enabled' : 'pdo_mysql missing',
            ],
            'writable_config' => [
                'ok' => is_writable(CONFIG),
                'detail' => CONFIG . ' must be writable',
            ],
            'writable_files' => [
                'ok' => is_writable(WWW_ROOT . 'files'),
                'detail' => WWW_ROOT . 'files must be writable',
            ],
        ];
    }
}
```

- [ ] **Step 3: Wire `InstallController::systemCheck`**

```php
public function systemCheck(): void
{
    $report = (new \App\Service\SystemCheck())->run();
    $allPass = !in_array(false, array_column($report, 'ok'), true);
    $this->set(compact('report', 'allPass'));
}
```

- [ ] **Step 4: Write `templates/Install/system_check.php`**

```php
<h1>Step 1 — System checks</h1>
<table class="table">
<?php foreach ($report as $name => $row): ?>
    <tr>
        <td><?= h($name) ?></td>
        <td><?= $row['ok'] ? '✅' : '❌' ?></td>
        <td><?= h($row['detail']) ?></td>
    </tr>
<?php endforeach; ?>
</table>
<?php if ($allPass): ?>
    <a href="/install/database" class="btn btn-primary">Next: Database</a>
<?php else: ?>
    <p class="text-danger">Resolve the failing checks and reload this page.</p>
<?php endif; ?>
```

- [ ] **Step 5: Run tests + manual smoke**

```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/SystemCheckTest.php
docker compose up -d && curl -sS http://localhost:8080/install/system-check -o /tmp/sc.html && grep -o '<table' /tmp/sc.html
```

Expected: tests green; HTML contains a table.

- [ ] **Step 6: Commit**

```bash
git add src/Service/SystemCheck.php src/Controller/InstallController.php \
        templates/Install/system_check.php tests/TestCase/Service/SystemCheckTest.php
git -c commit.gpgsign=false commit -m "feat(install): step 1 system check (php, gd, pdo, writable dirs)"
```

---

### Task 12: Installer step 2 — DB credentials & write `app_local.php`

**Files:**
- Modify: `src/Controller/InstallController.php` (`database` action)
- Create: `src/Service/AppLocalWriter.php`
- Create: `tests/TestCase/Service/AppLocalWriterTest.php`
- Create: `templates/Install/database.php`

- [ ] **Step 1: Write the failing test**

`tests/TestCase/Service/AppLocalWriterTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AppLocalWriter;
use Cake\TestSuite\TestCase;

final class AppLocalWriterTest extends TestCase
{
    public function testWritesAndReplacesPlaceholders(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'app_local_');
        $exampleSrc = tempnam(sys_get_temp_dir(), 'app_local_example_');
        file_put_contents(
            $exampleSrc,
            "<?php return ['Datasources' => ['default' => ['host' => '__DB_HOST__', 'database' => '__DB_NAME__', 'username' => '__DB_USER__', 'password' => '__DB_PASS__', 'port' => '__DB_PORT__']]];"
        );

        (new AppLocalWriter($exampleSrc))->write($tmp, [
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'DB_USER' => 'eqsl',
            'DB_PASS' => 'secret',
            'DB_NAME' => 'eqsl_db',
            'SECURITY_SALT' => str_repeat('a', 64),
            'SMTP_HOST' => 'mail.example.com',
            'SMTP_USER' => 'me@example.com',
            'SMTP_PASS' => 'p',
            'SMTP_FROM' => 'noreply@example.com',
        ]);

        $written = file_get_contents($tmp);
        $this->assertStringContainsString("'host' => 'localhost'", $written);
        $this->assertStringNotContainsString('__DB_HOST__', $written);

        unlink($tmp);
        unlink($exampleSrc);
    }
}
```

- [ ] **Step 2: Implement `src/Service/AppLocalWriter.php`**

```php
<?php
declare(strict_types=1);

namespace App\Service;

final class AppLocalWriter
{
    public function __construct(private string $examplePath) {}

    /** @param array<string,string> $values */
    public function write(string $destinationPath, array $values): void
    {
        $template = file_get_contents($this->examplePath);
        if ($template === false) {
            throw new \RuntimeException("Cannot read template at {$this->examplePath}");
        }
        foreach ($values as $key => $value) {
            $template = str_replace('__' . $key . '__', addslashes($value), $template);
        }
        if (file_put_contents($destinationPath, $template, LOCK_EX) === false) {
            throw new \RuntimeException("Cannot write to {$destinationPath}");
        }
        chmod($destinationPath, 0o640);
    }
}
```

- [ ] **Step 3: Wire `InstallController::database`**

```php
public function database()
{
    if ($this->request->is('post')) {
        $data = $this->request->getData();
        try {
            $this->testConnection($data);
            $writer = new \App\Service\AppLocalWriter(CONFIG . 'app_local.php.example');
            $writer->write(CONFIG . 'app_local.php', [
                'DB_HOST'        => (string)$data['host'],
                'DB_PORT'        => (string)$data['port'],
                'DB_USER'        => (string)$data['username'],
                'DB_PASS'        => (string)$data['password'],
                'DB_NAME'        => (string)$data['database'],
                'SECURITY_SALT'  => bin2hex(random_bytes(32)),
                'SMTP_HOST'      => (string)($data['smtp_host'] ?? ''),
                'SMTP_USER'      => (string)($data['smtp_user'] ?? ''),
                'SMTP_PASS'      => (string)($data['smtp_pass'] ?? ''),
                'SMTP_FROM'      => (string)($data['smtp_from'] ?? ''),
            ]);
            $this->Flash->success('Database connection saved.');
            return $this->redirect('/install/migrate');
        } catch (\Throwable $e) {
            $this->Flash->error($e->getMessage());
        }
    }
    $this->set('data', $this->request->getData() ?: ['host' => 'localhost', 'port' => '3306']);
}

private function testConnection(array $data): void
{
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $data['host'], $data['port'], $data['database']);
    new \PDO($dsn, $data['username'], $data['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
}
```

- [ ] **Step 4: Write `templates/Install/database.php`**

```php
<h1>Step 2 — Database</h1>
<?= $this->Form->create(null) ?>
<div class="mb-3"><label>Host</label><?= $this->Form->control('host', ['default' => $data['host'] ?? 'localhost', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Port</label><?= $this->Form->control('port', ['default' => '3306', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Database name</label><?= $this->Form->control('database', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>User</label><?= $this->Form->control('username', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Password</label><?= $this->Form->control('password', ['type' => 'password', 'class' => 'form-control', 'label' => false]) ?></div>
<hr>
<h2>SMTP (optional, can be configured later)</h2>
<div class="mb-3"><label>SMTP host</label><?= $this->Form->control('smtp_host', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>SMTP user</label><?= $this->Form->control('smtp_user', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>SMTP password</label><?= $this->Form->control('smtp_pass', ['type' => 'password', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>From address</label><?= $this->Form->control('smtp_from', ['class' => 'form-control', 'label' => false]) ?></div>
<button class="btn btn-primary">Save & continue</button>
<?= $this->Form->end() ?>
```

- [ ] **Step 5: Run tests**

```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/AppLocalWriterTest.php
```

Expected: green.

- [ ] **Step 6: Commit**

```bash
git add src/Service/AppLocalWriter.php src/Controller/InstallController.php \
        templates/Install/database.php tests/TestCase/Service/AppLocalWriterTest.php
git -c commit.gpgsign=false commit -m "feat(install): step 2 database credentials persisted to app_local.php"
```

---

### Task 13: Installer steps 3–6 — migrations, admin, lock, seed default template

**Files:**
- Modify: `src/Controller/InstallController.php` (migrate, admin, complete actions)
- Create: `src/Service/Installer.php`
- Create: `config/seeds/default_system_template.json`
- Create: `templates/Install/migrate.php`
- Create: `templates/Install/admin.php`
- Create: `templates/Install/complete.php`
- Create: `tests/TestCase/Service/InstallerTest.php`

- [ ] **Step 1: Define the default system template JSON**

`config/seeds/default_system_template.json`:

```json
{
    "name": "Classic — bottom panel",
    "description": "Default eQSL layout shipped with the installer.",
    "canvas_width": 1500,
    "canvas_height": 1000,
    "fields": [
        {"placeholder": "{operator_callsign}", "x": 80, "y": 130, "font": "Cinzel-Regular.ttf", "size": 90, "color": "#0b1d3a", "rotation": 0},
        {"placeholder": "to {callsign}",       "x": 80, "y": 210, "font": "Inter-Regular.ttf", "size": 48, "color": "#0b1d3a", "rotation": 0},
        {"placeholder": "Confirming our QSO on {qso_datetime_utc:Y-m-d H:i} UTC",
                                                "x": 80, "y": 760, "font": "Inter-Regular.ttf", "size": 32, "color": "#0b1d3a", "rotation": 0},
        {"placeholder": "Band: {band}  Mode: {mode}  Freq: {frequency_mhz} MHz",
                                                "x": 80, "y": 820, "font": "JetBrainsMono-Regular.ttf", "size": 28, "color": "#0b1d3a", "rotation": 0},
        {"placeholder": "RST sent: {rst_sent}   RST recv: {rst_received}",
                                                "x": 80, "y": 870, "font": "JetBrainsMono-Regular.ttf", "size": 28, "color": "#0b1d3a", "rotation": 0},
        {"placeholder": "{notes}",              "x": 80, "y": 930, "font": "Inter-Regular.ttf", "size": 24, "color": "#374151", "rotation": 0}
    ]
}
```

- [ ] **Step 2: Bundle a demo background**

Place a 1500×1000 JPEG at `webroot/files/templates/_demo-bg.jpg`. Generate one in this task by:

```bash
docker compose run --rm php php -r "\$im=imagecreatetruecolor(1500,1000); \$c=imagecolorallocate(\$im,235,242,250); imagefill(\$im,0,0,\$c); imagejpeg(\$im,'webroot/files/templates/_demo-bg.jpg',88);"
```

Adjust `.gitignore`:
```
!webroot/files/templates/_demo-bg.jpg
```

- [ ] **Step 3: Write the failing installer test**

`tests/TestCase/Service/InstallerTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Installer;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

final class InstallerTest extends TestCase
{
    protected array $fixtures = ['app.Users', 'app.Templates'];

    public function testRunningMigrationsAndSeedingIsIdempotent(): void
    {
        $installer = new Installer();
        $installer->runMigrations(); // should be a no-op once already migrated by fixtures
        $installer->seedDefaultTemplate(CONFIG . 'seeds/default_system_template.json');
        $count1 = TableRegistry::getTableLocator()->get('Templates')->find()->count();

        $installer->seedDefaultTemplate(CONFIG . 'seeds/default_system_template.json');
        $count2 = TableRegistry::getTableLocator()->get('Templates')->find()->count();

        $this->assertSame($count1, $count2, 'seed must be idempotent');
        $this->assertGreaterThanOrEqual(1, $count1);
    }

    public function testCreateAdminUser(): void
    {
        $installer = new Installer();
        $user = $installer->createAdmin([
            'name' => 'Robbi', 'email' => 'r@x.com',
            'callsign' => 'AA1AA', 'password' => 'CorrectHorseBatteryStaple1',
        ]);
        $this->assertSame('admin', $user->role);
        $this->assertNotEmpty($user->password_hash);
    }
}
```

- [ ] **Step 4: Implement `src/Service/Installer.php`**

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;
use Migrations\Migrations;

final class Installer
{
    public function runMigrations(): void
    {
        $migrations = new Migrations(['connection' => 'default']);
        $migrations->migrate();
    }

    /** @return \App\Model\Entity\User */
    public function createAdmin(array $data): object
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $entity = $users->newEntity([
            'name'     => (string)$data['name'],
            'email'    => (string)$data['email'],
            'callsign' => (string)$data['callsign'],
            'role'     => 'admin',
            'password' => (string)$data['password'],
        ]);
        $users->saveOrFail($entity);
        return $entity;
    }

    public function seedDefaultTemplate(string $jsonPath): void
    {
        $templates = TableRegistry::getTableLocator()->get('Templates');
        $existing = $templates->find()->where(['is_system' => true])->first();
        if ($existing !== null) {
            return; // idempotent
        }
        $payload = json_decode((string)file_get_contents($jsonPath), true, flags: JSON_THROW_ON_ERROR);
        $entity = $templates->newEntity([
            'user_id'        => null,
            'name'           => $payload['name'],
            'description'    => $payload['description'] ?? null,
            'canvas_width'   => (int)$payload['canvas_width'],
            'canvas_height'  => (int)$payload['canvas_height'],
            'layout_json'    => json_encode(['fields' => $payload['fields']], JSON_UNESCAPED_SLASHES),
            'thumbnail_path' => null,
            'is_public'      => true,
            'is_approved'    => true,
            'is_system'      => true,
        ]);
        $templates->saveOrFail($entity);
    }

    public function lock(string $lockPath): void
    {
        file_put_contents($lockPath, date(DATE_ATOM) . "\n", LOCK_EX);
    }
}
```

- [ ] **Step 5: Wire the remaining install actions**

```php
public function migrate()
{
    if ($this->request->is('post')) {
        try {
            (new \App\Service\Installer())->runMigrations();
            $this->Flash->success('Schema applied.');
            return $this->redirect('/install/admin');
        } catch (\Throwable $e) {
            $this->Flash->error('Migration failed: ' . $e->getMessage());
        }
    }
}

public function admin()
{
    if ($this->request->is('post')) {
        try {
            $installer = new \App\Service\Installer();
            $installer->createAdmin($this->request->getData());
            $installer->seedDefaultTemplate(CONFIG . 'seeds/default_system_template.json');
            $installer->lock(CONFIG . 'installed.lock');
            return $this->redirect('/install/complete');
        } catch (\Throwable $e) {
            $this->Flash->error($e->getMessage());
        }
    }
}

public function complete(): void
{
    $this->set('loginUrl', '/login');
}
```

- [ ] **Step 6: Add the three remaining views**

`templates/Install/migrate.php`:

```php
<h1>Step 3 — Apply schema</h1>
<?= $this->Form->postLink('Run migrations', '/install/migrate', ['class' => 'btn btn-primary']) ?>
```

`templates/Install/admin.php`:

```php
<h1>Step 4 — Create admin account</h1>
<?= $this->Form->create(null) ?>
<div class="mb-3"><label>Display name</label><?= $this->Form->control('name', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Callsign</label><?= $this->Form->control('callsign', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Email</label><?= $this->Form->control('email', ['class' => 'form-control', 'type' => 'email', 'label' => false]) ?></div>
<div class="mb-3"><label>Password</label><?= $this->Form->control('password', ['class' => 'form-control', 'type' => 'password', 'label' => false]) ?></div>
<button class="btn btn-primary">Create admin & finish</button>
<?= $this->Form->end() ?>
```

`templates/Install/complete.php`:

```php
<h1>Installed!</h1>
<p>Setup is complete. <a href="<?= h($loginUrl) ?>" class="btn btn-primary">Log in</a></p>
```

- [ ] **Step 7: Run tests**

```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/InstallerTest.php
```

Expected: green.

- [ ] **Step 8: Manual smoke test**

```bash
docker compose down -v   # destroy db volume to simulate fresh install
docker compose up -d
sleep 5
# Open http://localhost:8080/install in a browser; walk through.
```

Expected: at end, `config/installed.lock` exists, admin user in DB, one system template seeded.

- [ ] **Step 9: Commit**

```bash
git add src/Service/Installer.php src/Controller/InstallController.php \
        templates/Install/migrate.php templates/Install/admin.php templates/Install/complete.php \
        config/seeds/default_system_template.json webroot/files/templates/_demo-bg.jpg \
        tests/TestCase/Service/InstallerTest.php .gitignore
git -c commit.gpgsign=false commit -m "feat(install): steps 3-6 migrate, seed system template, create admin, lock"
```

---

## Phase E: Authentication

### Task 14: Authentication plugin wiring

**Files:**
- Modify: `src/Application.php`
- Create: `src/Service/AuthIdentifier.php` (if needed) — actually using the plugin's built-in is fine; no service required.
- Modify: `config/routes.php` for `/login`, `/logout`, `/register`, `/password/forgot`, `/password/reset`

- [ ] **Step 1: Register Authentication plugin in `Application.php`**

In `bootstrap()`:
```php
$this->addPlugin('Authentication');
```

In `getAuthenticationService()`:
```php
public function getAuthenticationService(\Psr\Http\Message\ServerRequestInterface $request): \Authentication\AuthenticationServiceInterface
{
    $service = new \Authentication\AuthenticationService();
    $service->setConfig([
        'unauthenticatedRedirect' => '/login',
        'queryParam' => 'redirect',
    ]);
    $fields = ['username' => 'email', 'password' => 'password_hash'];
    $service->loadIdentifier('Authentication.Password', compact('fields'));
    $service->loadAuthenticator('Authentication.Session');
    $service->loadAuthenticator('Authentication.Form', [
        'fields' => $fields,
        'loginUrl' => '/login',
    ]);
    return $service;
}
```

In `middleware()` add (after RoutingMiddleware):
```php
->add(new \Authentication\Middleware\AuthenticationMiddleware($this))
```

The Application class must `implements \Authentication\AuthenticationServiceProviderInterface`.

- [ ] **Step 2: Add auth routes**

```php
$routes->connect('/register', ['controller' => 'Auth', 'action' => 'register'])->setMethods(['GET','POST']);
$routes->connect('/login',    ['controller' => 'Auth', 'action' => 'login'])->setMethods(['GET','POST']);
$routes->connect('/logout',   ['controller' => 'Auth', 'action' => 'logout'])->setMethods(['POST']);
$routes->connect('/password/forgot', ['controller' => 'Auth', 'action' => 'forgot'])->setMethods(['GET','POST']);
$routes->connect('/password/reset/:token', ['controller' => 'Auth', 'action' => 'reset'])
    ->setPass(['token'])->setMethods(['GET','POST']);
```

- [ ] **Step 3: Stub `src/Controller/AuthController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controller;

class AuthController extends \Cake\Controller\Controller
{
    public function register() {}
    public function login()    {}
    public function logout()   {}
    public function forgot()   {}
    public function reset(string $token) {}
}
```

- [ ] **Step 4: Smoke test the plugin loads**

```bash
docker compose run --rm php bin/cake plugin loaded
```

Expected: `Authentication` listed.

- [ ] **Step 5: Commit**

```bash
git add src/Application.php config/routes.php src/Controller/AuthController.php
git -c commit.gpgsign=false commit -m "feat(auth): wire cakephp/authentication plugin and route stubs"
```

---

### Task 15: Register flow

**Files:**
- Modify: `src/Controller/AuthController.php` (`register`)
- Create: `templates/Auth/register.php`
- Create: `tests/TestCase/Controller/AuthControllerRegisterTest.php`

- [ ] **Step 1: Write the integration test**

`tests/TestCase/Controller/AuthControllerRegisterTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class AuthControllerRegisterTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users'];

    public function testGetRegisterPage(): void
    {
        $this->get('/register');
        $this->assertResponseOk();
        $this->assertResponseContains('Create account');
    }

    public function testRegisterCreatesUserAndRedirects(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/register', [
            'name' => 'Robbi',
            'callsign' => 'AA1AA',
            'email' => 'r@x.com',
            'password' => 'CorrectHorseBatteryStaple1',
            'password_confirm' => 'CorrectHorseBatteryStaple1',
        ]);
        $this->assertRedirect('/login');
        $users = $this->getTableLocator()->get('Users');
        $this->assertSame(1, $users->find()->where(['email' => 'r@x.com'])->count());
    }

    public function testRegisterRejectsMismatchedPasswords(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/register', [
            'name' => 'A', 'callsign' => 'A', 'email' => 'a@x.com',
            'password' => 'one', 'password_confirm' => 'two',
        ]);
        $this->assertResponseOk(); // re-renders form
        $this->assertResponseContains('do not match');
    }
}
```

- [ ] **Step 2: Implement `register`**

```php
public function register()
{
    $users = $this->fetchTable('Users');
    $entity = $users->newEmptyEntity();
    if ($this->request->is('post')) {
        $data = $this->request->getData();
        if (($data['password'] ?? null) !== ($data['password_confirm'] ?? null)) {
            $this->Flash->error('Passwords do not match');
            $this->set('user', $entity);
            return null;
        }
        $entity = $users->newEntity([
            'name' => $data['name'] ?? '',
            'callsign' => $data['callsign'] ?? '',
            'email' => $data['email'] ?? '',
            'password' => $data['password'] ?? '',
            'role' => 'user',
        ]);
        if ($users->save($entity)) {
            $this->Flash->success('Account created. Please log in.');
            return $this->redirect('/login');
        }
    }
    $this->set('user', $entity);
    return null;
}
```

- [ ] **Step 3: Write `templates/Auth/register.php`**

```php
<h1>Create account</h1>
<?= $this->Form->create($user) ?>
<div class="mb-3"><label>Name</label><?= $this->Form->control('name', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Callsign</label><?= $this->Form->control('callsign', ['class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Email</label><?= $this->Form->control('email', ['type' => 'email', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Password</label><?= $this->Form->control('password', ['type' => 'password', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Confirm password</label><?= $this->Form->control('password_confirm', ['type' => 'password', 'class' => 'form-control', 'label' => false]) ?></div>
<button class="btn btn-primary">Register</button>
<?= $this->Form->end() ?>
```

- [ ] **Step 4: Run tests, fix until green**

```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Controller/AuthControllerRegisterTest.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Controller/AuthController.php templates/Auth/register.php \
        tests/TestCase/Controller/AuthControllerRegisterTest.php
git -c commit.gpgsign=false commit -m "feat(auth): registration flow with password confirmation"
```

---

### Task 16: Login & logout

**Files:**
- Modify: `src/Controller/AuthController.php` (`login`, `logout`)
- Create: `templates/Auth/login.php`
- Create: `tests/TestCase/Controller/AuthControllerLoginTest.php`

- [ ] **Step 1: Write the integration test**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class AuthControllerLoginTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users'];

    protected function seedUser(string $email, string $password): void
    {
        $users = $this->getTableLocator()->get('Users');
        $users->saveOrFail($users->newEntity([
            'name' => 'X', 'callsign' => 'AA', 'email' => $email, 'role' => 'user',
            'password_hash' => (new DefaultPasswordHasher())->hash($password),
        ], ['accessibleFields' => ['*' => true]]));
    }

    public function testLoginValidCredentials(): void
    {
        $this->seedUser('a@x.com', 'pass1234');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/login', ['email' => 'a@x.com', 'password' => 'pass1234']);
        $this->assertRedirect('/');
    }

    public function testLoginInvalidCredentialsStays(): void
    {
        $this->seedUser('a@x.com', 'pass1234');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/login', ['email' => 'a@x.com', 'password' => 'wrong']);
        $this->assertResponseOk();
        $this->assertResponseContains('Invalid');
    }

    public function testLogoutClearsSession(): void
    {
        $this->seedUser('a@x.com', 'pass1234');
        $this->session(['Auth' => ['email' => 'a@x.com']]);
        $this->enableCsrfToken();
        $this->post('/logout');
        $this->assertRedirect('/login');
    }
}
```

- [ ] **Step 2: Implement actions**

```php
public function login()
{
    $result = $this->Authentication->getResult();
    if ($result?->isValid()) {
        return $this->redirect($this->request->getQuery('redirect') ?? '/');
    }
    if ($this->request->is('post') && (!$result || !$result->isValid())) {
        $this->Flash->error('Invalid email or password');
    }
    return null;
}

public function logout()
{
    $this->Authentication->logout();
    return $this->redirect('/login');
}
```

- [ ] **Step 3: View**

`templates/Auth/login.php`:

```php
<h1>Sign in</h1>
<?= $this->Form->create(null) ?>
<div class="mb-3"><label>Email</label><?= $this->Form->control('email', ['type' => 'email', 'class' => 'form-control', 'label' => false]) ?></div>
<div class="mb-3"><label>Password</label><?= $this->Form->control('password', ['type' => 'password', 'class' => 'form-control', 'label' => false]) ?></div>
<button class="btn btn-primary">Sign in</button>
<a href="/password/forgot" class="btn btn-link">Forgot password?</a>
<?= $this->Form->end() ?>
```

- [ ] **Step 4: Tests pass**

```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Controller/AuthControllerLoginTest.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Controller/AuthController.php templates/Auth/login.php \
        tests/TestCase/Controller/AuthControllerLoginTest.php
git -c commit.gpgsign=false commit -m "feat(auth): login and logout"
```

---

### Task 17: Password reset (token + email)

**Files:**
- Modify: `src/Controller/AuthController.php` (`forgot`, `reset`)
- Create: `src/Service/PasswordResetService.php`
- Create: `templates/Auth/forgot.php`, `templates/Auth/reset.php`
- Create: `templates/email/html/password_reset.php`
- Create: `templates/email/text/password_reset.php`
- Create: `tests/TestCase/Service/PasswordResetServiceTest.php`

- [ ] **Step 1: Test the service**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\PasswordResetService;
use Cake\TestSuite\TestCase;

final class PasswordResetServiceTest extends TestCase
{
    protected array $fixtures = ['app.Users', 'app.PasswordResets'];

    public function testIssueAndConsumeToken(): void
    {
        $svc = new PasswordResetService();
        $token = $svc->issue('a@x.com');
        $this->assertSame(43, strlen($token));
        $email = $svc->consume($token);
        $this->assertSame('a@x.com', $email);
        $this->expectException(\RuntimeException::class);
        $svc->consume($token); // already used
    }

    public function testExpiredTokenRejected(): void
    {
        $svc = new PasswordResetService(ttlSeconds: -1);
        $token = $svc->issue('a@x.com');
        $this->expectException(\RuntimeException::class);
        $svc->consume($token);
    }
}
```

- [ ] **Step 2: Implement**

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;

final class PasswordResetService
{
    public function __construct(private int $ttlSeconds = 3600) {}

    public function issue(string $email): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $resets = TableRegistry::getTableLocator()->get('PasswordResets');
        $resets->saveOrFail($resets->newEntity([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => FrozenTime::now()->addSeconds($this->ttlSeconds),
        ]));
        return $token;
    }

    public function consume(string $token): string
    {
        $resets = TableRegistry::getTableLocator()->get('PasswordResets');
        $row = $resets->find()->where([
            'token_hash' => hash('sha256', $token),
            'used_at IS' => null,
        ])->first();
        if ($row === null || $row->expires_at->lt(FrozenTime::now())) {
            throw new \RuntimeException('Token is invalid or expired.');
        }
        $row->used_at = FrozenTime::now();
        $resets->saveOrFail($row);
        return (string)$row->email;
    }
}
```

- [ ] **Step 3: Wire `forgot` and `reset` actions**

```php
public function forgot()
{
    if ($this->request->is('post')) {
        $email = (string)$this->request->getData('email');
        $svc = new \App\Service\PasswordResetService();
        $token = $svc->issue($email);
        $mailer = new \Cake\Mailer\Mailer('default');
        $mailer->setTo($email)->setSubject('Reset your eQSL password')
               ->setEmailFormat('both')
               ->setViewVars(['link' => env('APP_BASE_URL', 'http://localhost:8080') . '/password/reset/' . $token])
               ->viewBuilder()->setTemplate('password_reset');
        $mailer->deliver();
        $this->Flash->success('If that email exists, a reset link has been sent.');
        return $this->redirect('/login');
    }
}

public function reset(string $token)
{
    if ($this->request->is('post')) {
        $svc = new \App\Service\PasswordResetService();
        try {
            $email = $svc->consume($token);
        } catch (\Throwable $e) {
            $this->Flash->error($e->getMessage());
            return $this->redirect('/password/forgot');
        }
        $users = $this->fetchTable('Users');
        $user = $users->find()->where(['email' => $email])->firstOrFail();
        $user->password = (string)$this->request->getData('password');
        $users->saveOrFail($user);
        $this->Flash->success('Password updated. Please log in.');
        return $this->redirect('/login');
    }
    $this->set('token', $token);
}
```

- [ ] **Step 4: Email & view templates**

`templates/Auth/forgot.php`:
```php
<h1>Forgot password</h1>
<?= $this->Form->create(null) ?>
<?= $this->Form->control('email', ['type' => 'email', 'class' => 'form-control', 'label' => 'Email']) ?>
<button class="btn btn-primary">Send reset link</button>
<?= $this->Form->end() ?>
```

`templates/Auth/reset.php`:
```php
<h1>Reset password</h1>
<?= $this->Form->create(null) ?>
<?= $this->Form->hidden('_token', ['value' => $token]) ?>
<?= $this->Form->control('password', ['type' => 'password', 'class' => 'form-control', 'label' => 'New password']) ?>
<button class="btn btn-primary">Reset</button>
<?= $this->Form->end() ?>
```

`templates/email/text/password_reset.php`:
```
Click this link to reset your password: <?= $link ?>

Link is valid for one hour.
```

`templates/email/html/password_reset.php`:
```php
<p>Click this link to reset your password:</p>
<p><a href="<?= h($link) ?>"><?= h($link) ?></a></p>
<p>Link is valid for one hour.</p>
```

- [ ] **Step 5: Run tests, smoke-test mailhog**

```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/PasswordResetServiceTest.php
docker compose up -d
# POST /password/forgot via curl/browser; open http://localhost:8025 to see captured email.
```

- [ ] **Step 6: Commit**

```bash
git add src/Service/PasswordResetService.php src/Controller/AuthController.php \
        templates/Auth/forgot.php templates/Auth/reset.php templates/email/ \
        tests/TestCase/Service/PasswordResetServiceTest.php
git -c commit.gpgsign=false commit -m "feat(auth): password reset with one-hour token via smtp"
```

---

## Phase F: Guest QSL flow

### Task 18: Layout shell (Bootstrap 5 + Alpine.js)

**Files:**
- Modify: `templates/layout/default.php`
- Create: `webroot/css/app.css`
- Create: `webroot/js/app.js`

- [ ] **Step 1: Replace `templates/layout/default.php`**

```php
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $this->fetch('title') ?: 'eQSL Card' ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $this->Url->build('/css/app.css') ?>">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/">eQSL Card</a>
    <ul class="navbar-nav ms-auto">
      <?php if ($this->getRequest()->getAttribute('identity')): ?>
        <li class="nav-item"><a class="nav-link" href="/dashboard">Dashboard</a></li>
        <li class="nav-item">
          <?= $this->Form->postLink('Logout', '/logout', ['class' => 'nav-link']) ?>
        </li>
      <?php else: ?>
        <li class="nav-item"><a class="nav-link" href="/login">Sign in</a></li>
        <li class="nav-item"><a class="nav-link btn btn-primary text-white" href="/register">Create account</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>
<main class="container py-4">
  <?= $this->Flash->render() ?>
  <?= $this->fetch('content') ?>
</main>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<script src="<?= $this->Url->build('/js/app.js') ?>" defer></script>
</body>
</html>
```

- [ ] **Step 2: Minimal `webroot/css/app.css`**

```css
.card-preview { max-width: 100%; height: auto; box-shadow: 0 4px 16px rgba(0,0,0,.1); border-radius: .5rem; }
.field-error { color: #b91c1c; font-size: .875rem; }
[x-cloak] { display: none !important; }
```

- [ ] **Step 3: Stub `webroot/js/app.js`** (camera helper added next task)

```js
// app entry — extended in subsequent tasks.
```

- [ ] **Step 4: Smoke-test in browser** that the home page now uses the new layout.

- [ ] **Step 5: Commit**

```bash
git add templates/layout/default.php webroot/css/app.css webroot/js/app.js
git -c commit.gpgsign=false commit -m "feat(ui): bootstrap 5 + alpine.js layout shell"
```

---

### Task 19: Guest form (`PublicController::index`)

**Files:**
- Create: `src/Controller/PublicController.php`
- Create: `templates/Public/index.php`
- Modify: `config/routes.php`

- [ ] **Step 1: Add the route**

```php
$routes->connect('/',          ['controller' => 'Public', 'action' => 'index']);
$routes->connect('/generate',  ['controller' => 'Public', 'action' => 'generate'])->setMethods(['POST']);
```

- [ ] **Step 2: Create the controller**

```php
<?php
declare(strict_types=1);

namespace App\Controller;

class PublicController extends \Cake\Controller\Controller
{
    public function index(): void
    {
        $this->Authentication?->allowUnauthenticated(['index', 'generate']);
        $this->set('title', 'Generate an eQSL');
    }

    public function generate()
    {
        // Implemented in Task 20
    }
}
```

- [ ] **Step 3: Write the form view `templates/Public/index.php`**

```php
<div x-data="cameraForm()" class="card p-4">
<h1>Generate an eQSL</h1>
<p class="text-muted">Fill in the QSO, attach a background, and download.</p>

<?= $this->Form->create(null, ['url' => '/generate', 'type' => 'file']) ?>
<div class="row g-3">
  <div class="col-md-6"><?= $this->Form->control('callsign', ['label' => 'Their callsign', 'class' => 'form-control', 'required' => true]) ?></div>
  <div class="col-md-6"><?= $this->Form->control('operator_callsign', ['label' => 'My callsign', 'class' => 'form-control', 'required' => true]) ?></div>
  <div class="col-md-6"><?= $this->Form->control('qso_datetime_utc', ['label' => 'Date/Time UTC', 'type' => 'datetime-local', 'class' => 'form-control', 'required' => true]) ?></div>
  <div class="col-md-6"><?= $this->Form->control('frequency_mhz', ['label' => 'Frequency (MHz)', 'class' => 'form-control']) ?></div>
  <div class="col-md-3"><?= $this->Form->control('band', ['label' => 'Band', 'class' => 'form-control']) ?></div>
  <div class="col-md-3"><?= $this->Form->control('mode', ['label' => 'Mode', 'class' => 'form-control']) ?></div>
  <div class="col-md-3"><?= $this->Form->control('rst_sent', ['label' => 'RST sent', 'class' => 'form-control']) ?></div>
  <div class="col-md-3"><?= $this->Form->control('rst_received', ['label' => 'RST received', 'class' => 'form-control']) ?></div>
  <div class="col-md-6"><?= $this->Form->control('operator_name', ['label' => 'Their name', 'class' => 'form-control']) ?></div>
  <div class="col-md-12"><?= $this->Form->control('notes', ['label' => 'Notes', 'class' => 'form-control', 'type' => 'textarea', 'rows' => 2]) ?></div>
</div>

<hr>
<h2>Background</h2>
<div class="btn-group" role="group">
  <button type="button" class="btn btn-outline-primary" @click="mode='upload'" :class="mode==='upload' && 'active'">Upload</button>
  <button type="button" class="btn btn-outline-primary" @click="startCamera()" :class="mode==='camera' && 'active'">Use camera</button>
</div>

<div class="mt-3" x-show="mode==='upload'">
  <input type="file" name="background_upload" accept="image/jpeg,image/png,image/webp" class="form-control">
</div>
<div class="mt-3" x-show="mode==='camera'" x-cloak>
  <video x-ref="video" autoplay playsinline style="max-width:100%"></video>
  <canvas x-ref="canvas" hidden></canvas>
  <button type="button" class="btn btn-secondary mt-2" @click="capture()">Capture</button>
  <input type="hidden" name="background_capture" x-model="captured">
  <img class="card-preview mt-2" x-show="captured" :src="captured">
</div>

<button class="btn btn-primary mt-4">Generate</button>
<?= $this->Form->end() ?>
</div>
```

- [ ] **Step 4: Add the camera script to `webroot/js/app.js`**

```js
function cameraForm() {
    return {
        mode: 'upload',
        captured: '',
        async startCamera() {
            this.mode = 'camera';
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                this.$refs.video.srcObject = stream;
            } catch (e) {
                alert('Camera unavailable: ' + e.message);
                this.mode = 'upload';
            }
        },
        capture() {
            const v = this.$refs.video, c = this.$refs.canvas;
            c.width = v.videoWidth; c.height = v.videoHeight;
            c.getContext('2d').drawImage(v, 0, 0);
            this.captured = c.toDataURL('image/jpeg', 0.9);
            v.srcObject?.getTracks().forEach(t => t.stop());
        },
    };
}
window.cameraForm = cameraForm;
```

- [ ] **Step 5: Smoke-test the form renders**

```bash
docker compose up -d
curl -sS http://localhost:8080/ | grep -o '<form'
```

Expected: at least one `<form>` match.

- [ ] **Step 6: Commit**

```bash
git add src/Controller/PublicController.php templates/Public/index.php \
        webroot/js/app.js config/routes.php
git -c commit.gpgsign=false commit -m "feat(public): guest qsl form with upload/camera toggle"
```

---

### Task 20: Guest generate POST (the heart)

This is the largest task. We integrate `ImageOptimizer`, `CardRenderer`, persistence of `guest_visits`, `uploads`, `cards`.

**Files:**
- Modify: `src/Controller/PublicController.php` (`generate`)
- Create: `src/Service/GuestSession.php`
- Create: `tests/TestCase/Service/GuestSessionTest.php`
- Create: `tests/TestCase/Controller/PublicControllerGenerateTest.php`
- Create: `templates/Public/preview.php`

- [ ] **Step 1: Test `GuestSession`**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\GuestSession;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;

final class GuestSessionTest extends TestCase
{
    protected array $fixtures = ['app.GuestVisits'];

    public function testCreatesGuestVisitWhenCookieMissing(): void
    {
        $req = ServerRequestFactory::fromGlobals(['REMOTE_ADDR' => '203.0.113.5', 'HTTP_USER_AGENT' => 'curl']);
        $svc = new GuestSession();
        $visit = $svc->ensure($req);
        $this->assertSame(43, strlen($visit->session_token));
    }

    public function testReusesExistingVisit(): void
    {
        $req = ServerRequestFactory::fromGlobals(['REMOTE_ADDR' => '203.0.113.5', 'HTTP_USER_AGENT' => 'curl']);
        $req = $req->withCookieParams(['guest_session' => 'TOK0000000000000000000000000000000000000000']);
        // pre-seed the row so reuse is verified
        $this->getTableLocator()->get('GuestVisits')->saveOrFail(
            $this->getTableLocator()->get('GuestVisits')->newEntity([
                'session_token' => 'TOK0000000000000000000000000000000000000000',
                'ip_hash' => hash('sha256', '203.0.113.5'),
                'user_agent_hash' => hash('sha256', 'curl'),
            ])
        );
        $svc = new GuestSession();
        $visit = $svc->ensure($req);
        $this->assertSame('TOK0000000000000000000000000000000000000000', $visit->session_token);
    }
}
```

- [ ] **Step 2: Implement `src/Service/GuestSession.php`**

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\ServerRequestInterface;

final class GuestSession
{
    public const COOKIE = 'guest_session';

    public function ensure(ServerRequestInterface $req): object
    {
        $table = TableRegistry::getTableLocator()->get('GuestVisits');
        $cookie = $req->getCookieParams()[self::COOKIE] ?? '';
        if ($cookie !== '') {
            $existing = $table->find()->where(['session_token' => $cookie])->first();
            if ($existing) {
                $existing->last_seen_at = FrozenTime::now();
                $table->saveOrFail($existing);
                return $existing;
            }
        }
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $row = $table->newEntity([
            'session_token' => $token,
            'ip_hash' => hash('sha256', (string)($req->getServerParams()['REMOTE_ADDR'] ?? '')),
            'user_agent_hash' => hash('sha256', $req->getHeaderLine('User-Agent')),
        ]);
        $table->saveOrFail($row);
        return $row;
    }
}
```

- [ ] **Step 3: Implement `PublicController::generate`**

```php
public function generate()
{
    $this->Authentication?->allowUnauthenticated(['index', 'generate']);
    $this->request->allowMethod(['post']);

    $data = $this->request->getData();
    $visit = (new \App\Service\GuestSession())->ensure($this->request);

    // Persist cookie if newly created
    $this->response = $this->response->withCookie(
        new \Cake\Http\Cookie\Cookie(
            \App\Service\GuestSession::COOKIE,
            $visit->session_token,
            null, '/', null, true, true, 'Lax'
        )
    );

    // Resolve background source
    $tmpUpload = $this->resolveBackground($data);

    // Optimize once into a scratch path, then dedup by the POST-optimize hash.
    $optimizer = new \App\Service\ImageOptimizer(maxWidth: 2000, maxHeight: 1500, quality: 82);
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

    $uploads = $this->fetchTable('Uploads');
    $upload = $uploads->find()->where(['sha256_hash' => $sha])->first();
    if (!$upload) {
        $upload = $uploads->saveOrFail($uploads->newEntity([
            'guest_visit_id' => $visit->id,
            'original_filename' => 'guest-upload.jpg',
            'storage_path' => 'files/uploads/' . $sha . '.jpg',
            'mime_type' => 'image/jpeg',
            'width_px' => $info['width_px'],
            'height_px' => $info['height_px'],
            'file_size_bytes' => $info['file_size_bytes'],
            'sha256_hash' => $sha,
        ]));
    }

    // Load the system template
    $template = $this->fetchTable('Templates')->find()->where(['is_system' => true])->firstOrFail();
    $layout = json_decode($template->layout_json, true);

    // Build QSO data
    $qso = $this->buildQsoData($data);

    // Render
    $renderer = new \App\Service\CardRenderer(WWW_ROOT . 'files/fonts/');
    $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
    $pngPath = WWW_ROOT . 'files/cards/' . $uuid . '.png';
    $pdfPath = WWW_ROOT . 'files/cards/' . $uuid . '.pdf';
    if (!is_dir(dirname($pngPath))) {
        mkdir(dirname($pngPath), 0o775, true);
    }
    $renderer->renderPng(
        ['canvas_width' => $template->canvas_width, 'canvas_height' => $template->canvas_height,
         'fields' => $layout['fields']],
        $finalPath, $qso, $pngPath
    );
    $renderer->wrapPdf($pngPath, $pdfPath, $template->canvas_width, $template->canvas_height);

    // Persist card
    $cards = $this->fetchTable('Cards');
    $card = $cards->saveOrFail($cards->newEntity([
        'guest_visit_id' => $visit->id,
        'template_id' => $template->id,
        'upload_id' => $upload->id,
        'qso_data_json' => json_encode($qso, JSON_UNESCAPED_SLASHES),
        'png_path' => 'files/cards/' . $uuid . '.png',
        'pdf_path' => 'files/cards/' . $uuid . '.pdf',
    ]));

    $this->set(['cardId' => $card->id, 'pngUrl' => '/' . $card->png_path, 'pdfUrl' => '/' . $card->pdf_path]);
    $this->render('preview');
    return null;
}

private function resolveBackground(array $data): string
{
    $upload = $this->request->getUploadedFile('background_upload');
    if ($upload && $upload->getError() === UPLOAD_ERR_OK) {
        $tmp = tempnam(sys_get_temp_dir(), 'eqsl_');
        $upload->moveTo($tmp);
        return $tmp;
    }
    $capture = (string)($data['background_capture'] ?? '');
    if (str_starts_with($capture, 'data:image/')) {
        $blob = base64_decode((string)preg_replace('#^data:image/[^;]+;base64,#', '', $capture));
        $tmp = tempnam(sys_get_temp_dir(), 'eqsl_');
        file_put_contents($tmp, $blob);
        return $tmp;
    }
    throw new \Cake\Http\Exception\BadRequestException('Background image required.');
}

private function buildQsoData(array $data): array
{
    return [
        'callsign'           => trim((string)($data['callsign'] ?? '')),
        'operator_callsign'  => trim((string)($data['operator_callsign'] ?? '')),
        'qso_datetime_utc'   => (string)($data['qso_datetime_utc'] ?? ''),
        'frequency_mhz'      => (string)($data['frequency_mhz'] ?? ''),
        'band'               => (string)($data['band'] ?? ''),
        'mode'               => (string)($data['mode'] ?? ''),
        'rst_sent'           => (string)($data['rst_sent'] ?? ''),
        'rst_received'       => (string)($data['rst_received'] ?? ''),
        'operator_name'      => (string)($data['operator_name'] ?? ''),
        'notes'              => (string)($data['notes'] ?? ''),
    ];
}
```

- [ ] **Step 4: Preview view `templates/Public/preview.php`**

```php
<h1>Your eQSL is ready</h1>
<img class="card-preview" src="<?= h($pngUrl) ?>" alt="Generated eQSL card">
<div class="mt-3">
  <a class="btn btn-primary" href="<?= h($pngUrl) ?>" download>Download PNG</a>
  <a class="btn btn-secondary" href="<?= h($pdfUrl) ?>" download>Download PDF</a>
  <a class="btn btn-link" href="/">Generate another</a>
</div>
<p class="text-muted mt-3"><small>Refresh and the page will be empty &mdash; create a free account to keep your cards.</small></p>
```

- [ ] **Step 5: Integration test the happy path**

`tests/TestCase/Controller/PublicControllerGenerateTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;

final class PublicControllerGenerateTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.Templates', 'app.GuestVisits', 'app.Uploads', 'app.Cards'];

    public function testGuestCanGenerateAndCardIsPersisted(): void
    {
        // Seed system template
        $templates = $this->getTableLocator()->get('Templates');
        $templates->saveOrFail($templates->newEntity([
            'name' => 'sys', 'canvas_width' => 1500, 'canvas_height' => 1000,
            'layout_json' => json_encode(['fields' => []]), 'is_system' => true,
            'is_public' => true, 'is_approved' => true,
        ]));

        // Build a small JPEG fixture
        $img = imagecreatetruecolor(800, 600);
        $tmp = tempnam(sys_get_temp_dir(), 'fix_');
        imagejpeg($img, $tmp);
        imagedestroy($img);

        $upload = new UploadedFile($tmp, filesize($tmp), UPLOAD_ERR_OK, 'bg.jpg', 'image/jpeg');

        $this->configRequest(['files' => ['background_upload' => $upload]]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/generate', [
            'callsign' => 'W1AW', 'operator_callsign' => 'AA1AA',
            'qso_datetime_utc' => '2026-05-09T14:32',
            'frequency_mhz' => '14.205', 'band' => '20m', 'mode' => 'SSB',
            'rst_sent' => '59', 'rst_received' => '59', 'operator_name' => 'Hiram',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Your eQSL is ready');
        $this->assertSame(1, $this->getTableLocator()->get('Cards')->find()->count());
    }
}
```

- [ ] **Step 6: Run tests**

```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/GuestSessionTest.php
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Controller/PublicControllerGenerateTest.php
```

Expected: green.

- [ ] **Step 7: Commit**

```bash
git add src/Service/GuestSession.php src/Controller/PublicController.php \
        templates/Public/preview.php \
        tests/TestCase/Service/GuestSessionTest.php \
        tests/TestCase/Controller/PublicControllerGenerateTest.php
git -c commit.gpgsign=false commit -m "feat(public): guest generate flow end-to-end with persistence"
```

---

### Task 21: Card download endpoints

In M1, downloads of guest cards happen by hitting the static URL of the rendered file (`webroot/files/cards/{uuid}.png`). The preview page already links these. No controller is required — nginx serves them directly. We only add a tiny test to confirm files are reachable.

**Files:**
- Create: `tests/TestCase/Controller/CardDownloadSmokeTest.php`

- [ ] **Step 1: Test that an existing rendered file is reachable via `webroot/`**

This is a smoke test of the static-asset routing rather than a controller test:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\TestCase;

final class CardDownloadSmokeTest extends TestCase
{
    public function testDirectoryExistsAndIsServable(): void
    {
        $dir = WWW_ROOT . 'files/cards/';
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        $this->assertDirectoryExists($dir);
        $this->assertDirectoryIsWritable($dir);
    }
}
```

- [ ] **Step 2: Run tests**

```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Controller/CardDownloadSmokeTest.php
```

- [ ] **Step 3: Commit**

```bash
git add tests/TestCase/Controller/CardDownloadSmokeTest.php
git -c commit.gpgsign=false commit -m "test: smoke test for card download directory"
```

---

### Task 22: Rate-limit middleware (file cache backed)

**Files:**
- Create: `src/Service/RateLimiter.php`
- Create: `src/Middleware/RateLimitMiddleware.php`
- Modify: `src/Application.php`
- Create: `tests/TestCase/Service/RateLimiterTest.php`

- [ ] **Step 1: Test the limiter**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\RateLimiter;
use Cake\TestSuite\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/eqsl-rl-' . uniqid();
        mkdir($this->dir, 0o775, true);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testAllowsUnderLimitDeniesOver(): void
    {
        $rl = new RateLimiter($this->dir);
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($rl->hit('test', 'abc', limit: 3, windowSeconds: 60));
        }
        $this->assertFalse($rl->hit('test', 'abc', limit: 3, windowSeconds: 60));
    }

    public function testWindowResetsAfterTimeout(): void
    {
        $rl = new RateLimiter($this->dir, clock: fn() => 1000);
        $this->assertTrue($rl->hit('a', 'k', 1, 60));
        $this->assertFalse($rl->hit('a', 'k', 1, 60));
        $rl2 = new RateLimiter($this->dir, clock: fn() => 1100); // +100s
        $this->assertTrue($rl2->hit('a', 'k', 1, 60));
    }
}
```

- [ ] **Step 2: Implement**

```php
<?php
declare(strict_types=1);

namespace App\Service;

final class RateLimiter
{
    /** @var \Closure():int */
    private \Closure $clock;

    public function __construct(
        private string $storageDir,
        ?callable $clock = null,
    ) {
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0o775, true);
        }
        $this->clock = $clock ? \Closure::fromCallable($clock) : static fn(): int => time();
    }

    public function hit(string $action, string $identifier, int $limit, int $windowSeconds): bool
    {
        $file = $this->storageDir . '/' . hash('sha256', $action . ':' . $identifier);
        $now = ($this->clock)();
        $cutoff = $now - $windowSeconds;
        $stamps = is_file($file) ? array_map('intval', explode(',', (string)file_get_contents($file))) : [];
        $stamps = array_values(array_filter($stamps, static fn($t) => $t > $cutoff));
        if (count($stamps) >= $limit) {
            file_put_contents($file, implode(',', $stamps));
            return false;
        }
        $stamps[] = $now;
        file_put_contents($file, implode(',', $stamps), LOCK_EX);
        return true;
    }
}
```

- [ ] **Step 3: Middleware that throttles `/generate` and `/login`**

```php
<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Service\RateLimiter;
use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private RateLimiter $limiter) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $ip = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0');

        $rules = [
            '/generate' => ['limit' => 10, 'window' => 3600, 'method' => 'POST'],
            '/login'    => ['limit' => 5,  'window' => 900,  'method' => 'POST'],
        ];

        if (isset($rules[$path]) && strtoupper($request->getMethod()) === $rules[$path]['method']) {
            $rule = $rules[$path];
            if (!$this->limiter->hit($path, hash('sha256', $ip), $rule['limit'], $rule['window'])) {
                return (new Response())->withStatus(429)->withStringBody('Too many requests. Try again later.');
            }
        }

        return $handler->handle($request);
    }
}
```

- [ ] **Step 4: Register middleware**

In `src/Application.php`'s `middleware()` method:

```php
->add(new \App\Middleware\RateLimitMiddleware(new \App\Service\RateLimiter(TMP . 'cache/rate_limits')))
```

- [ ] **Step 5: Run tests**

```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Service/RateLimiterTest.php
```

Expected: green.

- [ ] **Step 6: Commit**

```bash
git add src/Service/RateLimiter.php src/Middleware/RateLimitMiddleware.php \
        src/Application.php tests/TestCase/Service/RateLimiterTest.php
git -c commit.gpgsign=false commit -m "feat(security): file-cache rate limiter on /generate and /login"
```

---

## Phase G: Security baseline

### Task 23: Security headers middleware

**Files:**
- Create: `src/Middleware/SecurityHeadersMiddleware.php`
- Modify: `src/Application.php`
- Create: `tests/TestCase/Middleware/SecurityHeadersMiddlewareTest.php`

- [ ] **Step 1: Test**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\SecurityHeadersMiddleware;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\Response;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testSetsExpectedHeaders(): void
    {
        $mw = new SecurityHeadersMiddleware();
        $req = ServerRequestFactory::fromGlobals();
        $handler = new class implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $r): \Psr\Http\Message\ResponseInterface
            { return new Response(); }
        };
        $resp = $mw->process($req, $handler);
        $this->assertSame('DENY', $resp->getHeaderLine('X-Frame-Options'));
        $this->assertSame('nosniff', $resp->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $resp->getHeaderLine('Referrer-Policy'));
        $this->assertNotEmpty($resp->getHeaderLine('Content-Security-Policy'));
    }
}
```

- [ ] **Step 2: Implement**

```php
<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Content-Security-Policy', $this->csp());
    }

    private function csp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "img-src 'self' data: blob:",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "script-src 'self' https://cdn.jsdelivr.net",
            "font-src 'self' https://cdn.jsdelivr.net",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }
}
```

- [ ] **Step 3: Register & test**

In `Application::middleware()`:
```php
->add(new \App\Middleware\SecurityHeadersMiddleware())
```

```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Middleware/SecurityHeadersMiddlewareTest.php
```

- [ ] **Step 4: Commit**

```bash
git add src/Middleware/SecurityHeadersMiddleware.php src/Application.php \
        tests/TestCase/Middleware/SecurityHeadersMiddlewareTest.php
git -c commit.gpgsign=false commit -m "feat(security): default security headers + csp middleware"
```

---

### Task 24: `.htaccess` deny rules

**Files:**
- Create: `config/.htaccess`
- Create: `src/.htaccess`
- Create: `vendor/.htaccess` (if vendor is in tree at deploy time — included in release zip)
- Create: `tmp/.htaccess`
- Create: `logs/.htaccess`
- Create: `webroot/files/uploads/.htaccess` (allow images only)

- [ ] **Step 1: Create deny `.htaccess` files**

For `config/`, `src/`, `vendor/`, `tmp/`, `logs/`:

```apache
Require all denied
```

(Apache 2.4+ syntax. If host is on 2.2 we ALSO add a fallback `Order deny,allow` block — but Apache 2.2 is dead in 2026. Skip.)

- [ ] **Step 2: Create `webroot/files/uploads/.htaccess`** (defense in depth — disable PHP execution under uploads)

```apache
<FilesMatch "\.(php|phtml|phar|pl|cgi)$">
    Require all denied
</FilesMatch>
```

Same for `webroot/files/cards/.htaccess`, `webroot/files/templates/.htaccess`.

- [ ] **Step 3: Smoke check**

```bash
docker compose up -d
curl -sS -o /dev/null -w '%{http_code}\n' http://localhost:8080/config/app.php
```

Expected: `403` (or `404` if nginx already strips). The deny rules apply on Apache shared hosting — nginx in dev doesn't honor them, so this test is best done on the production host. Document the limitation.

- [ ] **Step 4: Commit**

```bash
git add config/.htaccess src/.htaccess tmp/.htaccess logs/.htaccess \
        webroot/files/uploads/.htaccess webroot/files/cards/.htaccess webroot/files/templates/.htaccess
git -c commit.gpgsign=false commit -m "feat(security): apache deny rules for sensitive directories"
```

---

### Task 25: CSRF integration sanity check

CakePHP 5 enables CSRF middleware by default in the skeleton. We just verify it's active and write a regression test.

**Files:**
- Create: `tests/TestCase/Controller/CsrfRegressionTest.php`

- [ ] **Step 1: Write the test**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class CsrfRegressionTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users'];

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $this->post('/register', ['email' => 'x@y.com']);
        $this->assertResponseError(); // 403 expected from CSRF middleware
    }
}
```

- [ ] **Step 2: Run + commit**

```bash
docker compose run --rm php vendor/bin/phpunit tests/TestCase/Controller/CsrfRegressionTest.php
git add tests/TestCase/Controller/CsrfRegressionTest.php
git -c commit.gpgsign=false commit -m "test: csrf regression for POST endpoints"
```

---

## Phase H: Build, deploy, document

### Task 26: `scripts/build-release.sh`

**Files:**
- Create: `scripts/build-release.sh`
- Create: `dist/.gitkeep`

- [ ] **Step 1: Write the build script**

```bash
#!/usr/bin/env bash
# Build a shared-hosting-deployable zip.
set -Eeuo pipefail

VERSION="${1:-0.1.0}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE="${ROOT}/dist/stage-${VERSION}"
ZIP="${ROOT}/dist/eqsl-card-${VERSION}.zip"

rm -rf "${STAGE}" "${ZIP}"
mkdir -p "${STAGE}"

# Copy what ships
rsync -a --delete \
  --exclude='.git/' \
  --exclude='.docker/' \
  --exclude='.superpowers/' \
  --exclude='docker-compose.yml' \
  --exclude='dist/' \
  --exclude='node_modules/' \
  --exclude='tests/' \
  --exclude='.phpunit.cache/' \
  --exclude='.phpunit.result.cache' \
  --exclude='coverage/' \
  --exclude='config/app_local.php' \
  --exclude='config/installed.lock' \
  --exclude='webroot/files/uploads/*' \
  --include='webroot/files/uploads/.gitkeep' \
  --exclude='webroot/files/cards/*' \
  --include='webroot/files/cards/.gitkeep' \
  "${ROOT}/" "${STAGE}/"

# Make sure vendor/ exists with prod deps only
if [ ! -d "${STAGE}/vendor" ]; then
  echo "vendor/ not in stage; running composer install --no-dev"
  (cd "${ROOT}" && docker compose run --rm php composer install --no-dev --optimize-autoloader)
  rsync -a "${ROOT}/vendor/" "${STAGE}/vendor/"
fi

# Zip
(cd "${ROOT}/dist" && zip -qr "$(basename "${ZIP}")" "stage-${VERSION}")
rm -rf "${STAGE}"
echo "Built: ${ZIP}"
```

- [ ] **Step 2: Permissions + smoke**

```bash
chmod +x scripts/build-release.sh
docker compose run --rm php composer install --no-dev --optimize-autoloader
./scripts/build-release.sh 0.1.0-test
ls -lh dist/eqsl-card-0.1.0-test.zip
```

Expected: a zip of ~10–25 MB exists. (Run `composer install` again afterward to restore dev deps.)

```bash
docker compose run --rm php composer install
```

- [ ] **Step 3: Add `dist/.gitkeep` and commit**

```bash
mkdir -p dist
touch dist/.gitkeep
git add scripts/build-release.sh dist/.gitkeep
git -c commit.gpgsign=false commit -m "feat(build): release zip script"
```

---

### Task 27: `docs/DEPLOYMENT.md`

**Files:**
- Create: `docs/DEPLOYMENT.md`

- [ ] **Step 1: Write the deployment guide**

Include:
1. Build the zip locally (Docker command).
2. Upload to `public_html/`.
3. chmod requirements (`tmp/`, `logs/`, `webroot/files/`, `config/`).
4. Open `https://yourdomain/` to launch installer.
5. Step-by-step screenshots placeholders (add real screenshots once the UI is live).
6. The "alternative recommended layout" with subfolder + symlink.
7. Update procedure (re-upload, preserve specific files, hit `/admin/upgrade`).
8. Backup & restore (mysqldump + tar `webroot/files/`).
9. Common shared-host caveats (PHP version selector, MariaDB version, file-manager permission flags).

Skip the full content here for brevity — write it during the task; ~600 words; use the spec's Sections 9.1–9.4 as the source of truth.

- [ ] **Step 2: Commit**

```bash
git add docs/DEPLOYMENT.md
git -c commit.gpgsign=false commit -m "docs: shared-hosting deployment guide v1"
```

---

### Task 28: `README.md`

**Files:**
- Create / replace: `README.md`

- [ ] **Step 1: Write the README**

Sections:
1. **What it is** — 2-sentence pitch.
2. **Status** — `M1 / v0.1` foundations released.
3. **Local dev quickstart** — `docker compose up -d`, open `http://localhost:8080/install`.
4. **Tech stack** — bullets.
5. **Project layout** — link to `docs/superpowers/specs/2026-05-09-eqsl-card-design.md` Section 5.
6. **Deployment** — link to `docs/DEPLOYMENT.md`.
7. **Roadmap** — M1 ✅, M2/M3/M4 planned (link the milestone plan files).
8. **License** — MIT.
9. **Author** — Robbi Nespu.

Keep it under 300 lines; no badges yet (CI lands in M4).

- [ ] **Step 2: Commit**

```bash
git add README.md
git -c commit.gpgsign=false commit -m "docs: project readme v1"
```

---

### Task 29: Tag the M1 release

- [ ] **Step 1: Run the full test suite once more**

```bash
docker compose run --rm php vendor/bin/phpunit
```

Expected: all green.

- [ ] **Step 2: Build the official zip**

```bash
docker compose run --rm php composer install --no-dev --optimize-autoloader
./scripts/build-release.sh 0.1.0
docker compose run --rm php composer install   # restore dev deps
```

Expected: `dist/eqsl-card-0.1.0.zip` exists.

- [ ] **Step 3: Tag**

```bash
git tag -a v0.1.0 -m "M1 foundation: guest eQSL generator on cakephp 5"
git tag -l v0.1.0
```

- [ ] **Step 4 (optional): Push & publish**

```bash
git push origin master --tags
# Optionally upload dist/eqsl-card-0.1.0.zip as a GitHub release asset.
```

---

## Self-review (run inline before declaring M1 plan done)

The following spec requirements (Section 11 → M1) MUST each map to at least one task:

| Requirement | Task |
|---|---|
| Docker dev env (php-fpm 8.1 + mariadb 10.6 + nginx + mailhog) | Task 1 |
| CakePHP 5 skeleton + Bootstrap 5 + Alpine.js | Tasks 2, 18 |
| DB schema (users, guest_visits, uploads, templates, cards, app_settings, password_resets) | Task 4 |
| ORM tables + entities | Task 5 |
| Web installer wizard | Tasks 10, 11, 12, 13 |
| Auth (register / login / logout / password reset) | Tasks 14–17 |
| Guest QSL flow (form → upload OR camera → render → PNG/PDF) | Tasks 19, 20, 21 |
| CardRenderer with one hard-coded layout (PNG + PDF) | Tasks 8, 9, plus seeded template in Task 13 |
| ImageOptimizer | Task 7 |
| Rate limiting | Task 22 |
| Security headers + .htaccess deny | Tasks 23, 24, 25 |
| `scripts/build-release.sh` | Task 26 |
| DEPLOYMENT.md | Task 27 |
| Tests: CardRenderer, ImageOptimizer, installer integration, guest happy path, auth happy path | Tasks 7, 8, 9, 13, 15, 16, 20 |

All boxes ticked. If any task is descoped during execution, add a follow-up task before tagging v0.1.

---

## Out of scope for M1 (intentionally deferred to M2+)

- `qsos` table, ADIF/CSV parsers — M2.
- Logged-in user dashboard, card history, share links — M2.
- Template designer + Fabric.js + thumbnail rendering on save — M3.
- Admin moderation queue, email verification, audit logs, cleanup tools — M4.
- GitHub Actions CI — M4.
