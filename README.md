# eQSL Card Generator

[![CI](https://github.com/RobbiNespu/eQSL-card/actions/workflows/ci.yml/badge.svg)](https://github.com/RobbiNespu/eQSL-card/actions/workflows/ci.yml)
![Tests](https://img.shields.io/badge/PHPUnit-220%20tests-brightgreen)
![PHP](https://img.shields.io/badge/PHP-8.1-blue)
![CakePHP](https://img.shields.io/badge/CakePHP-5.2-D33C43)
![License](https://img.shields.io/badge/license-MIT-lightgrey)

A self-hostable web app for amateur radio operators to generate eQSL
(electronic QSL) cards from QSO data. Designed to run on commodity shared
hosting (PHP + MariaDB) with no external services required.

## Status

**Status:** v1.0.0 — Foundation through Admin & polish. Production-ready for shared-hosting deployment.

## Quickstart

```bash
git clone https://github.com/RobbiNespu/eQSL-card.git
cd eQSL-card
docker compose up -d
docker compose run --rm --no-deps -u root php \
  chmod -R 0777 config webroot/files tmp logs
```

The chmod is dev-only — PHP-FPM in the container runs as `www-data` (uid 33) but the bind-mounted files are owned by your host uid, so `0775` doesn't grant write access to "others". On real shared hosting, `0775` works because cPanel runs PHP under your own account. See `docs/DEPLOYMENT.md` for production permissions.

Open http://localhost:8080/install in your browser, walk through the wizard, then sign in with the admin account you created.

To run the test suite:
```bash
docker compose run --rm --no-deps php vendor/bin/phpunit
```

## Tech stack

- PHP 8.1 (strict types)
- CakePHP 5.2
- MariaDB 10.6.25
- Bootstrap 5 + Alpine.js (loaded via CDN)
- PHP-GD and [setasign/fpdf](https://packagist.org/packages/setasign/fpdf)
  for PNG / PDF rendering
- Docker for local development

## Project layout

The directory structure and module boundaries are documented in the design
spec — see
[Section 5 of the design spec](docs/superpowers/specs/2026-05-09-eqsl-card-design.md)
for the canonical project layout.

## Building the CSS bundle

The UI ships as a pre-compiled CSS bundle at `webroot/css/dist.css`.
It's regenerated on the developer's machine and committed to git so
the shared host can serve it directly via FTP (no Node on the server).

Install the toolchain once:

    npm install

Rebuild after any change to `templates/**/*.php`, `webroot/js/**/*.js`,
or `webroot/css/theme.css`:

    npm run build:css

Or run in watch mode while iterating:

    npm run watch:css

The minified output is around 138 KB. PurgeCSS strips any utility class
not used by the template / JS content paths. If you introduce a class
name that's only inserted at runtime by Alpine (e.g. `class="something-${kind}"`),
add it to the `safelist` in `tailwind.config.js`.

Note: `watch:css` only rebuilds the Tailwind portion live. If you edit
`webroot/css/theme.css` during a watch session you'll need to stop and
re-run `npm run build:css` once because the script concatenates
theme.css onto dist.css as a post-build step. Theme changes are
infrequent so this is acceptable.

## Documentation

In-app help portal lives at `/help` once the site is running. Covers
getting started, logging QSOs, generating cards, sharing, template
design, admin setup, and a glossary of amateur-radio terms.

Articles are static PHP templates under `templates/Help/{category}/{slug}.php`
driven by `App\Service\HelpCatalog`. Add or edit pages by editing the
catalog + the template file, then committing — no database, no admin UI.

## Deployment

Shared-hosting deployment (cPanel / DirectAdmin / similar) is documented in
[`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## Roadmap

- ✅ **M1 Foundation** (`v0.1.0`) — guest QSL generation, installer, auth scaffolding
- ✅ **M2 Logged-in features** (`v0.2.0`) — QSO library, ADIF/CSV import, share links, render-from-QSO
- ✅ **M3 Template designer** (`v0.3.0`) — Fabric.js drag-and-drop, public template gallery, admin moderation
- ✅ **M4 Admin & polish** (`v1.0.0`) — audit logs, admin tools, email verification, profile, CI

Per-milestone implementation plans live under [`docs/superpowers/plans/`](docs/superpowers/plans/).

## Screenshots

> Screenshots TBD — see `docs/screenshots/` once captured. The included `dist/eqsl-card-*.zip` is FTP-deployable to any shared host running PHP 8.1+ and MariaDB/MySQL.

## License

MIT — see [`LICENSE`](LICENSE).

## Author

Robbi Nespu &lt;robbinespu@gmail.com&gt;
