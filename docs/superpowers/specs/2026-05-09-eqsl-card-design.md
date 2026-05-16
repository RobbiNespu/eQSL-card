# eQSL Card Generator — Design Document

**Date:** 2026-05-09
**Author:** Robbi Nespu
**Status:** Draft for implementation
**Repository:** https://github.com/robbinespu/eQSL-card (intended)

---

## 1. Purpose & Scope

A self-hostable, modern web application that generates eQSL (electronic QSL)
cards for amateur radio operators. The application is built on **CakePHP 5**
with **PHP 8.1** and **MySQL/MariaDB 10.6.25**, deploys to **shared hosting via
FTP without SSH access**, and uses **Docker only for local development**.

The application serves three audiences:

- **Guests** — anonymous visitors who fill in a form, attach a background
  image (upload or device camera), generate a card, and download it as PNG or
  PDF. Guests cannot share, save, or revisit their cards.
- **Logged-in users** — own a callsign profile, manage a QSO logbook
  (manual entry, ADIF import, CSV import), design custom card templates,
  generate cards from any QSO, and share cards via public links with optional
  password protection.
- **Admins** — manage the user base, moderate community-shared templates,
  view all generated cards (including guest output), and run cleanup utilities.

## 2. Non-Goals

- Federation with eQSL.cc, LoTW, or QRZ.com (out of scope; can be added later).
- Real-time radio rig control or logging (this is a card generator, not a
  logger).
- Mobile apps. The web UI must be responsive but is not packaged natively.
- Background job queues. Shared hosting cannot reliably run workers, so all
  long-running operations are synchronous (with chunked progress for bulk
  renders).
- Multi-tenant SaaS. The app is single-tenant per deployment.

## 3. Constraints (driving design)

| Constraint | Implication |
|---|---|
| PHP 8.1 strict | CakePHP 5.x (latest 5.x compatible with 8.1). |
| MySQL/MariaDB 10.6.25 | Stick to widely-supported SQL, JSON columns OK. |
| Shared hosting, no SSH | No `bin/cake` on host → web installer wizard; ship `vendor/` pre-built; pure-PHP libraries only (GD + FPDF, no Imagick, no headless browser). |
| FTP-only deploy | Single zip artifact; preserve `config/app_local.php`, `installed.lock`, and `webroot/files/` across uploads. |
| Docker for dev only | `docker-compose.yml` for php-fpm + mariadb + nginx + mailhog; production runs on bare LAMP. |
| Public GitHub repo, no secrets | `.env` and `app_local.php` gitignored; secrets only in `webroot/files/` or DB; example files committed. |
| Solo authorship | All commits as `Robbi Nespu <robbinespu@gmail.com>` with no co-author trailers. |

## 4. Architecture

### 4.1 High-level component map

```
                ┌──────────────────────────┐
                │   Browser (any device)   │
                │ Bootstrap 5 + Alpine.js  │
                │ Fabric.js (designer only)│
                └─────────────┬────────────┘
                              │ HTTPS
                              ▼
                ┌──────────────────────────┐
                │  CakePHP 5 Application   │
                │  Controllers (Public,    │
                │   Auth, User, Admin)     │
                │  Service layer           │
                │  ORM / Models            │
                └──────┬─────────┬─────────┘
                       │         │
              ┌────────▼──┐  ┌───▼────────────┐
              │   MySQL   │  │ webroot/files  │
              │  10.6.25  │  │  (uploads,     │
              │           │  │   rendered     │
              │           │  │   PNGs/PDFs,   │
              │           │  │   fonts)       │
              └───────────┘  └────────────────┘
```

### 4.2 Architectural approach

Server-rendered CakePHP views (Bootstrap 5) with **Alpine.js** for small
reactivity (form validation, camera preview, modal toggles, live thumbnails)
and **Fabric.js** for the template designer canvas. No Node build step in
production; CSS/JS shipped pre-built inside the release zip.

### 4.3 Render pipeline (single source of truth)

All cards are produced by one path:

```
QSO data + Template JSON + Background image
                ↓
       PHP-GD draws @ 300 DPI
                ↓
        ┌───────┴────────┐
        ▼                ▼
    PNG file       FPDF wraps PNG
                   into PDF page
```

Why a single GD-based path:

- GD ships with every PHP build on every shared host.
- One renderer means PNG and PDF are byte-for-byte equivalent visually.
- FPDF is pure-PHP, no dependencies, ~5 lines to embed a PNG into a PDF page.
- PDF text is not selectable, but eQSL cards are visual artifacts; this is
  acceptable.

Fonts are bundled in `webroot/files/fonts/` (Inter, Roboto Slab, Cinzel,
JetBrains Mono — all open-source, redistributable). `imagettftext` reads them
by path.

## 5. Project structure

```
eQSL-card/
├── .docker/
│   ├── php/Dockerfile          # PHP 8.1 + GD + PDO-MySQL + Composer
│   └── nginx/default.conf
├── docker-compose.yml          # php-fpm + mariadb 10.6 + nginx + mailhog
├── config/
│   ├── app.php
│   ├── app_local.php.example   # template for production-time copy
│   ├── routes.php
│   └── Migrations/             # schema migrations (run by installer)
├── src/
│   ├── Application.php
│   ├── Controller/
│   │   ├── InstallController.php       # /install wizard (self-locking)
│   │   ├── PublicController.php        # guest QSL form, share landing
│   │   ├── AuthController.php          # login/register/logout/reset
│   │   ├── DashboardController.php
│   │   ├── QsosController.php          # logbook + ADIF/CSV import
│   │   ├── CardsController.php         # render/list/share
│   │   ├── TemplatesController.php     # designer + listing
│   │   └── Admin/
│   │       ├── UsersController.php
│   │       ├── TemplatesController.php # moderation
│   │       └── CardsController.php     # all-cards (incl. guest) view
│   ├── Model/Table/
│   ├── Model/Entity/
│   ├── Service/
│   │   ├── CardRenderer.php    # GD draw → PNG; FPDF wrap → PDF
│   │   ├── AdifParser.php
│   │   ├── CsvParser.php
│   │   ├── ImageOptimizer.php  # resize + EXIF strip + re-encode
│   │   └── Installer.php       # runs migrations + seeds
│   └── View/Helper, View/Cell
├── templates/                  # CakePHP .php views
├── webroot/
│   ├── index.php
│   ├── .htaccess               # root rewrite
│   ├── css/  js/  img/
│   └── files/
│       ├── uploads/            # user-uploaded backgrounds
│       ├── cards/              # rendered PNG + PDF
│       ├── templates/          # gallery thumbnails
│       └── fonts/              # bundled OFL fonts
├── tests/
├── scripts/
│   └── build-release.sh        # produces dist/eqsl-card-X.Y.Z.zip
├── docs/
│   ├── DEPLOYMENT.md
│   └── superpowers/specs/      # this file lives here
└── README.md
```

## 6. Data Model

Nine tables. All timestamps are stored UTC. Soft-delete (`deleted_at`) on
`users`, `templates`, `cards`, `uploads`.

### 6.1 `users`
Logged-in accounts only (admin + user). Guests are NOT in this table.

| column | type | notes |
|---|---|---|
| id | int PK | |
| name | varchar(120) | display name / real name |
| email | varchar(190) UNIQUE | |
| password_hash | varchar(255) | Argon2id |
| role | enum('admin','user') | |
| callsign | varchar(20) INDEXED | |
| qth | varchar(120) | city, region |
| grid_square | varchar(10) | Maidenhead locator |
| bio | text NULL | |
| email_verified_at | datetime NULL | |
| last_login_at | datetime NULL | |
| created_at, updated_at, deleted_at | datetime | |

### 6.2 `guest_visits`
One row per anonymous browser session that generates at least one card.

| column | type | notes |
|---|---|---|
| id | int PK | |
| session_token | char(43) UNIQUE | URL-safe base64, set in HTTPOnly cookie |
| ip_hash | char(64) INDEXED | SHA-256 of remote IP |
| user_agent_hash | char(64) | SHA-256 of UA string |
| created_at, last_seen_at | datetime | |

### 6.3 `qsos`
Logbook entries owned by logged-in users. Imported from ADIF/CSV or entered
manually.

| column | type | notes |
|---|---|---|
| id | int PK | |
| user_id | int FK → users | |
| call_worked | varchar(20) INDEXED | their callsign |
| qso_datetime_utc | datetime | |
| frequency_mhz | decimal(10,5) NULL | |
| band | varchar(8) | e.g. `20m`, `2m` |
| mode | varchar(20) | `SSB`, `CW`, `FT8`, ... |
| rst_sent | varchar(8) | |
| rst_received | varchar(8) | |
| operator_name | varchar(120) NULL | their name |
| operator_qth | varchar(120) NULL | |
| grid_square | varchar(10) NULL | |
| notes | text NULL | |
| created_at, updated_at | datetime | |

UNIQUE INDEX on `(user_id, call_worked, qso_datetime_utc, band)` to stop
double imports.

### 6.4 `templates`
Card layouts. `user_id` NULL means a system template seeded by the installer.

| column | type | notes |
|---|---|---|
| id | int PK | |
| user_id | int FK NULL | NULL = system template |
| name | varchar(120) | |
| description | text NULL | |
| canvas_width | int | px @ 300 DPI |
| canvas_height | int | px @ 300 DPI |
| layout_json | longtext | Fabric.js field positions |
| thumbnail_path | varchar(255) NULL | `webroot/files/templates/...` |
| is_public | boolean | user requested community share |
| is_approved | boolean | admin moderation flag |
| is_system | boolean | shipped with installer |
| created_at, updated_at, deleted_at | datetime | |

### 6.5 `uploads`
Background images. Deduplicated by SHA-256 hash. Reusable across cards.

| column | type | notes |
|---|---|---|
| id | int PK | |
| user_id | int FK NULL | |
| guest_visit_id | int FK NULL | |
| original_filename | varchar(255) | |
| storage_path | varchar(255) | `webroot/files/uploads/{sha256}.jpg` |
| mime_type | varchar(60) | post-optimize MIME |
| width_px, height_px | int | post-optimize dimensions |
| file_size_bytes | int | post-optimize size |
| sha256_hash | char(64) UNIQUE | |
| created_at, deleted_at | datetime | |

CHECK: exactly one of `user_id` / `guest_visit_id` set.

### 6.6 `cards`
The generated eQSL card record. References EITHER a user OR a guest_visit.

| column | type | notes |
|---|---|---|
| id | int PK | |
| user_id | int FK NULL | |
| guest_visit_id | int FK NULL | |
| qso_id | int FK NULL | NULL when generated from a free-form form |
| template_id | int FK | |
| upload_id | int FK | background |
| qso_data_json | json | snapshot of QSO fields at render time |
| png_path | varchar(255) | `webroot/files/cards/{uuid}.png` |
| pdf_path | varchar(255) | |
| share_slug | char(43) UNIQUE NULL | NULL until first shared |
| share_password_hash | varchar(255) NULL | optional Argon2id |
| share_revoked_at | datetime NULL | |
| created_at, updated_at, deleted_at | datetime | |

CHECK: exactly one of `user_id` / `guest_visit_id` set.

### 6.7 `password_resets`

| column | type | notes |
|---|---|---|
| id | int PK | |
| email | varchar(190) INDEXED | |
| token_hash | char(64) | SHA-256 of issued token |
| expires_at | datetime | now + 1h |
| used_at | datetime NULL | |
| created_at | datetime | |

### 6.8 `app_settings`
Key-value store for runtime config managed via admin UI (site name, default
template id, max upload size, share base URL, SMTP config).

| column | type | notes |
|---|---|---|
| key | varchar(80) PK | |
| value | text | JSON-encoded scalar/object |
| updated_at | datetime | |

### 6.9 `audit_logs`

| column | type | notes |
|---|---|---|
| id | int PK | |
| actor_user_id | int FK NULL | |
| actor_guest_visit_id | int FK NULL | |
| event | varchar(80) | e.g. `card.generated`, `template.approved` |
| target_type | varchar(40) NULL | model name |
| target_id | int NULL | |
| metadata_json | json NULL | |
| created_at | datetime | |

### 6.10 Migrations
One migration per feature slice, run by the installer wizard via the
`cakephp/migrations` plugin's PHP API. Idempotent: re-running the installer is
safe and skips already-applied migrations.

## 7. Key Flows

### 7.1 Installer wizard (one-time, self-locking)

Triggers when `config/app_local.php` is missing OR `config/installed.lock`
does not exist.

1. **System checks** — PHP ≥ 8.1, GD enabled, PDO_MySQL enabled,
   `config/`, `tmp/`, `logs/`, `webroot/files/` writable.
2. **DB credentials** — host, port, name, user, password, with a "Test
   connection" button.
3. **Run migrations** via the Migrations plugin's PHP API.
4. **Seed system templates** — three starter layouts inserted (`is_system=1`).
5. **Create admin** — name, email, password, callsign, QTH.
6. **Lock installer** — write `config/app_local.php`, write
   `config/installed.lock`, redirect to `/login`.

After lock, `/install` returns 404 unless the lock file is removed via FTP
(intentional friction).

### 7.2 Guest QSL flow

```
GET /
  → public form: callsign, date, freq, mode, RST, notes,
                 template picker (public+approved only),
                 image source: [Upload] | [Use camera]

POST /generate
  → validate + sanitize
  → ensure guest_visits row (set HTTPOnly cookie if absent)
  → ImageOptimizer: resize, strip EXIF, re-encode JPEG
  → insert uploads row
  → CardRenderer: GD draws PNG → FPDF wraps PDF
  → insert cards row (guest_visit_id set, share_slug = NULL)
  → preview page with [Download PNG] [Download PDF]

Refresh / leave  → no record visible to the guest
                   cards row stays in DB for admin
```

### 7.3 Auth

- **Register** — email + password, email verification link required before
  login (mailhog in dev, host SMTP in prod).
- **Login** — CakePHP Authentication plugin, session-based, Argon2id hashing.
- **Password reset** — `password_resets` row with hashed token, 1-hour TTL.
- **Profile** — name, callsign, QTH, grid_square, bio, avatar.

### 7.4 QSO library + ADIF/CSV import

- `/qsos` — list with search and filter (band, mode, date range).
- `/qsos/import` — upload `.adi` or `.csv`. Parser emits `{valid, invalid,
  duplicate}` summary; user confirms; transactional batch insert.
- `/qsos/{id}/render` — pick template + background → card created from QSO.
- `/qsos?select=multi` — multi-select → bulk render with one chosen template.
  Renders sequentially (synchronous), chunked POSTs (5 per chunk) with a
  progress modal. No background workers.

### 7.5 Template designer

`/templates/new` and `/templates/{id}/edit` — Fabric.js canvas with:

- A field palette: Callsign (worked), My Callsign, Date/Time UTC, Frequency,
  Band, Mode, RST sent, RST received, Operator Name, Their QTH, Grid Square,
  Notes, Custom text.
- Right pane format controls: position (x, y), font (from bundled fonts),
  size, color, alignment, rotation, opacity.
- Background uploader.
- "Make public" checkbox → submits to admin moderation queue.

On save, the server stores `layout_json` and renders a 400-px thumbnail using
sample QSO data via `CardRenderer`.

### 7.6 Render pipeline

```
CardRenderer::render($templateJson, $background, $qsoData) :
  1. imagecreatetruecolor(width, height) at 300 DPI
  2. imagecopyresampled the background to fill canvas
  3. for each field in layout_json:
       resolve placeholder ({callsign} → "W1AW")
       imagettftext at (x, y) with font/size/color/rotation
  4. imagepng → webroot/files/cards/{uuid}.png
  5. FPDF: AddPage; Image($pngPath, 0, 0, $w, $h); Output('F')
  6. update cards row: png_path, pdf_path
```

### 7.7 Share page

```
GET /qsl/{slug}
  share_revoked_at NOT NULL          → 410 Gone
  share_password_hash NOT NULL
       and no slug-session cookie    → /qsl/{slug}/unlock
  else:
       render share page with:
         - the card (PNG embedded, og:image meta tags)
         - QSO summary (call, date, freq, mode, RST)
         - [Download PNG] [Download PDF]
         - "Generated by {operator callsign}"
```

## 8. Security

- **CSRF**: CakePHP `CsrfProtectionMiddleware` on every POST.
- **XSS**: auto-escape on all user data; `{placeholder}` rendering in
  `CardRenderer` is plain text via GD, no HTML surface.
- **SQL injection**: ORM-only access; no raw SQL. ADIF parser whitelists tag
  names.
- **Password hashing**: Argon2id via `DefaultPasswordHasher`.
- **Share-link entropy**: 43-char URL-safe base64 slug from
  `random_bytes(32)` → 256 bits. Same encoding for `guest_visits.session_token`.
- **Share password**: Argon2id; unlock sets a per-slug session cookie.
- **Rate-limit storage**: CakePHP file cache (`tmp/cache/rate_limits/`) keyed
  by `{action}:{identifier}`, TTL-pruned. No Redis dependency.
- **File upload validation**:
  - finfo MIME sniff — accept only `image/jpeg`, `image/png`, `image/webp`.
  - Re-encode through GD (strips EXIF + polyglot payloads).
  - Size cap: 8 MB pre-encode, 2 MB post-encode (configurable).
  - Stored as `webroot/files/uploads/{sha256}.jpg`; original filename
    discarded.
- **Rate limits**:
  - guest `/generate` — 10/hour per IP-hash.
  - login — 5/15min per email.
  - share password unlock — 5/15min per slug.
- **Installer self-lock**: `config/installed.lock` blocks re-runs.
- **Deny rules**: `.htaccess` `Deny from all` in `config/`, `src/`, `vendor/`,
  `tmp/`, `logs/`.
- **Privacy**: guest IPs and UAs stored as SHA-256 hashes only.
- **Headers**: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: strict-origin-when-cross-origin`, CSP allowing
  `script-src 'self'` plus the Fabric.js CDN hash.

## 9. Deployment

### 9.1 Build the release bundle (local Docker)

```
$ docker compose run --rm php composer install --no-dev --optimize-autoloader
$ docker compose run --rm php scripts/build-release.sh
  → dist/eqsl-card-1.0.0.zip
```

The bundle excludes `.git`, `.docker`, `tests/`, `node_modules`,
`docker-compose.yml`, `.env`, `app_local.php`, `.superpowers/`. It includes
`src/`, `config/`, `templates/`, `webroot/` (with built CSS/JS), `vendor/`
(no dev deps), `config/app_local.php.example`, `config/Migrations/`,
`DEPLOYMENT.md`, `README.md`.

### 9.2 First-time install on shared host

1. Extract zip to `public_html/`.
2. Ensure `tmp/`, `logs/`, `webroot/files/` are writable (chmod `0775` via
   file manager).
3. Open `https://yourdomain/` → installer wizard.
4. Wizard creates `config/app_local.php` and `config/installed.lock`.
5. Done — log in as admin.

### 9.3 Layout in `public_html/`

```
public_html/
├── index.php           # redirects to webroot/index.php
├── .htaccess           # root rewrite
├── webroot/
│   ├── index.php       # CakePHP front controller
│   └── .htaccess       # Cake routing
├── config/             # .htaccess Deny from all
├── src/                # .htaccess Deny from all
├── vendor/             # .htaccess Deny from all
└── tmp/, logs/         # .htaccess Deny from all
```

For hosts that allow custom document roots, an alternative layout
(`/home/user/eqsl/` with `webroot/` symlinked to `public_html/`) is
documented in `DEPLOYMENT.md` as the recommended layout.

### 9.4 Updating a live host

1. Build a new release zip locally.
2. FTP-upload, overwriting everything **except** `config/app_local.php`,
   `config/installed.lock`, and `webroot/files/`.
3. Visit `/admin/upgrade` to run any pending migrations and clear caches.

## 10. Testing strategy

```
        Unit  ←─── 70% of tests
      ──────────
    Integration  ←─── 25%
  ────────────────
    E2E (smoke)   ←─── 5%
────────────────────
```

**Unit (PHPUnit, no DB)**

- `CardRenderer`: render fixture inputs, assert pixel hash equals snapshot.
- `AdifParser`: parse fixture `.adi` files from N1MM, Log4OM, FT8, assert
  QSO array.
- `CsvParser`: handle quoted fields, BOMs, varying delimiters.
- `ImageOptimizer`: resize, EXIF strip, re-encode round-trip.
- Placeholder resolver: `{callsign}`, `{date_utc}`, `{custom:foo}`.

**Integration (PHPUnit + IntegrationTestTrait, in-memory SQLite)**

- Guest happy path: POST `/generate` → cards row + files exist on disk.
- Auth: register → verify email link → login → dashboard.
- ADIF import: upload fixture → expected QSO count in DB.
- Share unlock with password.
- Installer wizard: fresh state → migrations applied → lock written.
- Permission boundaries: user cannot view another user's cards; guest
  cannot reach `/qsos`.

**E2E smoke (Playwright via Docker)**

- Designer drag-drop: place fields, save, verify thumbnail matches.
- Camera capture flow with mocked `getUserMedia`.

**Coverage gates**: services 90 %+, controllers 70 %+. Designer JS gets a
small Vitest harness for the Fabric.js helpers.

## 11. Phasing

Strictly sequential. Each milestone tags a release and produces a deployable
zip.

### M1 — Foundation (≈ 30–40 % of total work, target tag `v0.1`)

Ships a working guest-only generator. Real users can use this in production
immediately.

- Docker dev environment (php-fpm 8.1 + mariadb 10.6 + nginx + mailhog).
- CakePHP 5 skeleton + Bootstrap 5 + Alpine.js.
- DB schema for `users`, `guest_visits`, `cards`, `uploads`,
  `app_settings`, `password_resets`, **and `templates`** (with one seeded
  `is_system=1` row whose `layout_json` is M1's hard-coded layout). **Not
  yet:** `qsos`, `audit_logs`.
- Guest form in M1 has **no template picker** — every guest card uses the
  single seeded system template. The picker UI lands in M3 once multiple
  templates exist.
- Template thumbnails use a bundled demo background at
  `webroot/files/templates/_demo-bg.jpg`, shipped with the release zip; no
  `uploads` row is created for it.
- Web installer wizard.
- Auth: register / login / logout / password reset.
- Guest QSL flow end-to-end.
- `CardRenderer` with one hard-coded built-in layout (callsigns top, QSO
  panel bottom).
- `ImageOptimizer`.
- Rate limiting, security headers, `.htaccess` deny rules.
- `scripts/build-release.sh` — first FTP-deployable zip.
- `DEPLOYMENT.md` v1.
- Tests: `CardRenderer`, `ImageOptimizer`, installer integration, guest
  happy path, auth happy path.

### M2 — Logged-in features (≈ 20–25 %, target tag `v0.2`)

- `qsos` table + manual QSO CRUD.
- Dashboard.
- Card history (list / detail / re-download / delete).
- `AdifParser` + ADIF import flow with validation summary.
- `CsvParser` + CSV import (with column mapping).
- Multi-select bulk render (chunked progress).
- Render-from-QSO flow.
- Public share links (slug + password + revoke + landing page).
- OG meta tags on share page.
- `/admin/upgrade` route for migrations on update.
- Tests: parsers (real-world fixtures), share unlock flow, permission
  boundaries.

### M3 — Template designer (≈ 25–30 %, target tag `v0.3`)

- `templates` table + migration.
- Fabric.js canvas designer (drag, format pane, save).
- Field placeholder palette + render-time resolver.
- Server-side thumbnail generation on save.
- Template gallery (My / Public-approved / System).
- Clone-and-edit flow.
- "Mark public" + admin moderation queue.
- Migrate M1's hard-coded layout into the first system template.
- Tests: layout-JSON renderer parity; designer JS via Vitest.

### M4 — Admin & polish (≈ 10–15 %, target tag `v1.0`)

- `audit_logs` table + migration; instrument admin actions and key user
  events (card.generated, template.approved, user.role_changed).
- Admin dashboard (counts, storage, recent activity).
- Admin: users CRUD, role changes, soft-delete.
- Admin: all-cards browser (incl. guest cards).
- Admin: audit log viewer.
- Admin: cleanup tools (purge old guest cards, prune orphaned uploads).
- Email verification on register.
- Profile page with avatar upload.
- App settings panel (site name, max upload size, share base URL, SMTP).
- README, screenshots, GitHub Actions CI (lint + tests in Docker).
- v1.0 release tag and bundle.

## 12. Open Questions

None blocking. The following are reasonable defaults that can be adjusted
during implementation:

- **Guest data retention.** Default: kept indefinitely; M4 ships an admin
  "purge guest cards older than N days" tool.
- **Share-link analytics.** Default: out of scope for v1; track only that a
  share exists, not who viewed it.
- **Internationalization.** Default: English only for v1; CakePHP's i18n
  facilities are wired up but only one locale is shipped.
- **Avatar storage.** Default: same `uploads/` flow with separate row type;
  avatars are never used as card backgrounds.

---

## 13. Mobile & portable operating *(M5, v1.1)*

Sections 1–12 describe a desktop-first web app. §13 extends the design
for amateur radio operators logging from a phone while operating
portable — POTA / SOTA / IOTA activations, field day, mobile contacts —
where the operator has one hand on the radio and one hand on the phone,
likely without reliable cell coverage.

### 13.1 Target environment

- **Primary viewport:** 375 × 667 px (iPhone SE / 8 baseline). Common
  Malaysian Android phones cluster around 360–414 px wide.
- **Minimum viewport:** 320 px (older devices, accessibility zoom).
- **Tap targets:** ≥ 44 × 44 CSS px per Apple HIG / WCAG 2.5.5.
- **Input:** virtual keyboard takes ~50 % of the viewport on focus —
  primary actions must remain reachable above it.
- **Network:** intermittent or absent. The app must remain usable
  through full network loss and reconcile cleanly on reconnect.
- **Light source:** outdoor activations mean glare. Dark-mode toggle
  is already shipped (M4); high-contrast text in both themes is a
  non-negotiable requirement for portable use.

### 13.2 Navigation

Below the `lg` Bootstrap breakpoint (< 992 px), the existing top
navbar collapses into a **sticky bottom-tab bar** with the five core
screens reachable in one thumb tap:

| Tab        | Route                | Notes                                    |
|------------|----------------------|------------------------------------------|
| Dashboard  | `/dashboard`         | At-a-glance stats; entry point.         |
| Logbook    | `/qsos`              | Full QSO list.                          |
| Quick add  | `/qsos/quick`        | Default deep-link target for the PWA.   |
| Cards      | `/cards`             | Generated card library.                 |
| More       | menu drawer          | Templates, Backgrounds, Help, Admin.    |

Desktop (≥ 992 px) keeps the existing top navbar — bottom tabs only
appear when the viewport is mobile-sized.

### 13.3 Activations

A new first-class entity that groups consecutive QSOs logged at a
single portable site. Without activations, batch-exporting "all QSOs
from yesterday's Bukit Larut SOTA" requires manual SQL.

Schema (M5 T12, T13):

```
activations
  id              integer PK
  user_id         integer FK users(id)
  code            varchar(60)   e.g. "9M2/PR-001", "POTA-K-1234", or free text
  name            varchar(120)  human label e.g. "Bukit Larut SOTA"
  grid_square     varchar(8)    Maidenhead, 4 or 6 chars; nullable
  started_at      datetime
  ended_at        datetime nullable  null = currently active
  notes           text nullable
  created_at      datetime

qsos
  + activation_id  integer FK activations(id) nullable
```

Backfill on the migration is intentional: `activation_id` stays NULL
for every historic QSO. Activations only apply forward.

Behaviour:

- An "active" activation (`ended_at IS NULL`) is shown as a banner
  across the top of `/qsos/quick`. New QSOs auto-tag with its
  `activation_id`.
- Grid square auto-fills from browser geolocation on start (T15) and
  flows through to every QSO logged in the activation as the
  operator's `tx_grid`. Override allowed.
- ADIF export endpoint `/activations/{id}/export.adi` returns a
  POTA/SOTA-upload-ready file (T17).

### 13.4 Quick-add form

Route `/qsos/quick` (M5 T7). Designed for one-thumb operation:

- Single column, full viewport width.
- Pinned "Last 5 QSOs" panel above the form for context.
- Inputs in this order, top to bottom: callsign, frequency, mode,
  RST sent, RST received, notes.
- Frequency uses `inputmode="decimal"`; band auto-derives via the
  existing `App\Service\HamRadio::BAND_RANGES` table.
- Mode picker is a chip row (CW / SSB / FT8 / FM / Other) instead of
  a `<select>`.
- "Quick-fill chips" for the notes field — user-configurable
  shortcuts (default: Net, POTA, SOTA, Contest, Ragchew).
- Save & next behaviour: POST, clear, refocus callsign. No redirect.
- Sticky full-width primary button at viewport bottom, positioned
  above the virtual keyboard via `env(keyboard-inset-height)` or
  fallback `inset-area`.

### 13.5 Offline-first via PWA

The defining capability for portable use. Three layers:

**Manifest** (`webroot/manifest.webmanifest`, T18). Standalone display,
`start_url: /qsos/quick`, brand colours, 192/512 icons. Wired in the
default layout `<head>`.

**Service worker** (`webroot/sw.js`, T19). Three strategies:

| Path pattern              | Strategy        | Rationale                          |
|---------------------------|-----------------|------------------------------------|
| `/css/*`, `/js/*`, `/files/*` | `cache-first` | Static assets — long-lived.    |
| HTML / JSON (`/qsos*`, `/dashboard`, etc.) | `network-first` | Fresh data when online; cached fallback offline. |
| `/admin/*`, `/login`, `/install*` | `network-only` | No caching — admin work happens at a desk. |

**Offline queue** (`webroot/js/sync.js`, T20–T22). When
`navigator.onLine === false`:

- `/qsos/quick` POST is intercepted by the service worker.
- The QSO is stashed in IndexedDB (`eqsl-card-offline.qsos`) with
  `pending_sync = true` and a client-side UUID.
- UI shows "Queued offline · will sync when reconnected" toast.

On `online` event:

- Drain the pending queue chronologically.
- POST each row to `/qsos/quick.json`.
- Server responds with the canonical row (its real integer ID).
- Client deletes the local pending row.
- Conflict rule: server is authoritative. If a duplicate
  `(callsign, datetime, band)` is detected server-side, server upserts
  (last-write-wins on the *client* timestamps) and returns the
  canonical row — the client should not treat this as an error.

**Status pill** (T23). Top-of-screen indicator: "Online · 0 queued",
"Offline · 3 queued", "Syncing · 1 of 5". Tap opens the pending list
with per-row retry/delete.

### 13.6 Dupe checking

Real-time feedback as the operator types a callsign. The signal is
"have I worked this station before, and if so when and on what band".

Endpoint (T25): `GET /api/qsos/dupe-check?callsign=X&band=Y`. Returns:

```json
{
  "last_worked_at": "2026-05-14T08:21:00Z",
  "same_band_today": false,
  "same_band_this_activation": false,
  "total_qsos": 3
}
```

Debounced 200 ms on the client. Renders as a coloured pill under the
callsign input (T26):

| State                          | Colour  | Behaviour                          |
|--------------------------------|---------|------------------------------------|
| Never worked                   | grey    | "First contact"                    |
| Worked before (any band)       | blue    | "Worked 3× · last 2026-05-14"      |
| Same band today                | yellow  | "Worked today on 40m at 08:21"     |
| Same band this activation      | red     | "Duplicate of QSO #421"            |

Optional user preference `block_dupes_in_activation` (T27) — when ON,
the red state disables Save and inline-shows the conflicting QSO.

### 13.7 Out of scope for §13

Explicit non-goals so future contributors don't slip these into M5:

- **CAT control** (WebSerial bridge to a rig for auto-grabbing
  frequency/mode). Real-world value, but a different engineering
  domain — M6 candidate.
- **Two-way eQSL exchange** (receiving cards from other operators
  via LoTW / eQSL.cc). Different milestone direction entirely.
- **DXCC / award progress dashboards**. Was an alternative pitch
  for M5; keep as M6+ candidate.
- **Multi-station support** (one user owning multiple callsigns).
  Conflates with activation grouping; revisit only if operator
  feedback demands it.
