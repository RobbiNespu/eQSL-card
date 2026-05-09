# M4 Admin & Polish — Plan Outline

> **Status:** OUTLINE ONLY. Expand into a fully detailed TDD plan before starting execution.

**Prerequisite:** M3 complete and tagged `v0.3.0`.

**Goal:** Ship `v1.0`. Audit logs, admin tooling, email verification, profile, app settings, README polish, GitHub Actions CI.

**Spec reference:** Sections 6.9, 11 → M4.

---

## Task list

### Phase A: Audit logs
- **T1** — Migration `audit_logs` (matches spec §6.9) + ORM Table/Entity.
- **T2** — `AuditLogger` service with `log(event, actor, target, metadata)` method; write-through to DB.
- **T3** — Instrument key events: `card.generated`, `card.shared`, `card.revoked`, `template.approved`, `template.rejected`, `user.role_changed`, `user.deleted`, `installer.completed`.
- **T4** — Replace M3's temporary `logs/moderation.log` with the new audit logger.

### Phase B: Admin dashboard
- **T5** — `Admin/DashboardController::index`: counts (users, cards, templates, storage in MB), 30-day activity sparkline (raw counts grouped by day), recent audit-log tail.
- **T6** — `Admin/UsersController` CRUD (list with search, edit role, soft-delete).
- **T7** — `Admin/CardsController::all` browser including guest cards; filter by role/date/template.
- **T8** — `Admin/AuditController::index` with event-type filter + identifier search.

### Phase C: Cleanup tools
- **T9** — `Admin/CleanupController::guestCards` — purge guest cards older than N days (default 30; configurable via app_settings). Deletes DB rows and the underlying PNG/PDF files.
- **T10** — `Admin/CleanupController::orphanedUploads` — find `uploads` rows with no `cards` reference and `created_at` older than N days; delete files + rows.
- **T11** — Both cleanup actions log to `audit_logs` and provide dry-run preview before commit.

### Phase D: Email verification
- **T12** — Add `email_verification_tokens` table OR reuse `password_resets` schema with an action discriminator column. Decide during expansion.
- **T13** — On register, set `users.email_verified_at = NULL`, send verification email via M1's mailer template.
- **T14** — `/email/verify/{token}` route consumes token, sets `email_verified_at`. Login attempts on unverified accounts show a "verify your email" banner with a "resend" button (rate-limited 1/hour).

### Phase E: Profile
- **T15** — `/profile` GET/POST for editing name, callsign, qth, grid_square, bio.
- **T16** — Avatar upload (separate flow; reuses ImageOptimizer with smaller bounding box; stored under `webroot/files/avatars/{userId}.jpg`).

### Phase F: App settings UI
- **T17** — `Admin/SettingsController` with form for: site name, max upload size, share base URL, SMTP host/user/pass/from. Persists to `app_settings` table.
- **T18** — Settings loader pulls into runtime config on app boot; cached.

### Phase G: CI + docs polish
- **T19** — `.github/workflows/ci.yml` running `composer install`, `composer audit`, `vendor/bin/phpunit`, `vendor/bin/phpcs --standard=PSR12 src/` inside the project's PHP container image.
- **T20** — README screenshots (capture on local Docker), badges (CI status), demo GIF for guest flow.
- **T21** — `docs/DEPLOYMENT.md` final pass: real screenshots, troubleshooting section, "common cPanel quirks" appendix.
- **T22** — Tag `v1.0.0`, rebuild release zip, optionally publish a GitHub Release with the zip attached.

---

## Spec coverage check

Spec §11 → M4 items mapped:
- audit_logs table + migration + instrumentation → T1–T4.
- Admin dashboard → T5.
- Admin: users CRUD, role changes, soft-delete → T6.
- Admin: all-cards browser (incl. guest) → T7.
- Admin: audit log viewer → T8.
- Admin: cleanup tools (purge old guest cards, prune orphaned uploads) → T9–T11.
- Email verification → T12–T14.
- Profile page (callsign, qth, grid, bio, avatar) → T15, T16.
- App settings panel → T17, T18.
- README, screenshots, GitHub Actions CI → T19, T20.
- v1.0 release tag and bundle → T22.

Done after this milestone: spec is fully implemented, repo is GitHub-public-ready, deployable zip can be downloaded as a GitHub Release artefact.
