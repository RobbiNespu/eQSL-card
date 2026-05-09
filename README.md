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
```

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
