# eQSL Card Generator

A self-hostable web app for amateur radio operators to generate eQSL
(electronic QSL) cards from QSO data. Designed to run on commodity shared
hosting (PHP + MariaDB) with no external services required.

## Status

**M1 / v0.1.0 — Foundation** released. Guest QSL generation works end-to-end:
fill in QSO details, pick a built-in template, and download a rendered PNG
or PDF card without an account.

## Local dev quickstart

Requires Docker and Docker Compose.

```bash
docker compose up -d
```

Then open <http://localhost:8080/install>, follow the install wizard
(database check, admin user, site settings), and log in.

The default dev URL is `http://localhost:8080`. Database, app container, and
volumes are all defined in `docker-compose.yml`.

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

- ✅ **M1: Foundation** — guest QSL generation, installer, auth scaffolding
  (`v0.1.0`).
- ⏳ **M2: Logged-in features** — QSO library, ADIF/CSV import, share links.
  Planned, see
  [M2 plan](docs/superpowers/plans/2026-05-09-m2-logged-in-features.md).
- ⏳ **M3: Template designer** — Fabric.js drag-drop editor. Planned, see
  [M3 plan](docs/superpowers/plans/2026-05-09-m3-template-designer.md).
- ⏳ **M4: Admin & polish** — admin tools, observability, CI. Planned, see
  [M4 plan](docs/superpowers/plans/2026-05-09-m4-admin-polish.md).

## License

MIT — see [`LICENSE`](LICENSE).

## Author

Robbi Nespu &lt;robbinespu@gmail.com&gt;
