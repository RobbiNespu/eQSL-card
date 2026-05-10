# Deployment guide — shared hosting

For typical cPanel / DirectAdmin shared hosts. Requires **PHP 8.1+ (strict)**
and **MariaDB 10.6+ (or MySQL 5.7+)**. SSH is optional — FTP and a browser
are enough.

---

## 1. Build the release zip locally

```bash
docker compose run --rm php composer install --no-dev --optimize-autoloader
./scripts/build-release.sh 0.1.0
```

Output: `dist/eqsl-card-0.1.0.zip`. The script excludes `tests/`, `.git/`,
`docker-compose.yml`, dev composer deps, and runtime caches; it ships
`vendor/.htaccess` and the `.gitkeep` markers `tmp/` and `webroot/files/`
need.

Restore dev deps after: `docker compose run --rm php composer install`.

---

## 2. Upload to `public_html/`

1. Connect over FTP/SFTP (FileZilla, Cyberduck, cPanel File Manager).
2. Extract the zip locally, then upload the **contents** of `stage-0.1.0/`
   into `public_html/`.
3. Resulting tree (see spec §9.3,
   `docs/superpowers/specs/2026-05-09-eqsl-card-design.md`):

```
public_html/
├── index.php
├── .htaccess
├── webroot/        front controller + assets
├── config/         .htaccess: Require all denied
├── src/            .htaccess: Require all denied
├── vendor/         .htaccess: Require all denied
└── tmp/, logs/     .htaccess: Require all denied
```

---

## 3. Permissions (chmod)

Set via the file manager's permissions dialog (tick "recurse" where noted):

| Path | Mode | Recurse |
|---|---|---|
| `tmp/` | `0775` | yes |
| `logs/` | `0775` | yes |
| `webroot/files/` | `0775` | yes |
| `config/` | `0755` | no |
| `config/app_local.php` (post-install) | `0640` | no |

If your host runs PHP under your FTP user, `0775` is enough. Otherwise try
`0777` once, verify install, then tighten back to `0775`.

> **Local dev (Docker) note:** the bundled `php-fpm` image runs as `www-data`
> (uid 33), which is "others" relative to your host uid. `0775` will NOT
> grant write access. Run this once after cloning:
>
> ```bash
> docker compose run --rm --no-deps -u root php \
>   chmod -R 0777 config webroot/files tmp logs
> ```
>
> Production isn't affected — only the dev bind-mount.

---

## 4. Run the install wizard

Open `https://yourdomain.tld/`. `InstallationCheckMiddleware` redirects `/`
to `/install` until `config/installed.lock` exists.

1. **System checks** — *[screenshot placeholder]*
2. **Database credentials** — *[screenshot placeholder]*
3. **Run migrations** — *[screenshot placeholder]*
4. **Create admin account** — *[screenshot placeholder]*
5. **Seed defaults** — *[screenshot placeholder]*
6. **Finish** (writes `config/app_local.php` + `config/installed.lock`) —
   *[screenshot placeholder]*

After the lock is written, `/install/*` returns 404 and `/` serves the guest
form. Real screenshots ship in M4.

---

## 5. Recommended layout — subfolder + symlink

If your host lets you change the document root, keep app code **outside**
the public directory:

```
/home/user/
├── eqsl/                         app root
│   ├── src/, config/, vendor/, tmp/, logs/
│   └── webroot/                  only this is web-served
└── public_html/  →  symlink to /home/user/eqsl/webroot
```

Or point the document root directly at `/home/user/eqsl/webroot`. Keeps
`config/`, `src/`, `vendor/`, `tmp/`, `logs/` unreachable by URL even if
`.htaccess` is ignored.

---

## 6. Updating an installed host

1. Build a new zip locally: `./scripts/build-release.sh X.Y.Z`.
2. FTP-upload, **overwriting everything except**:
   - `config/app_local.php`
   - `config/installed.lock`
   - `webroot/files/uploads/`
   - `webroot/files/cards/`
   - `webroot/files/templates/`
3. Visit `/admin/upgrade` — runs pending migrations and clears caches.

> `/admin/upgrade` is **not** in v0.1; it lands in **v0.2+** (M2 T18). On
> v0.1, upgrade by running migrations over SSH
> (`bin/cake migrations migrate`) or by deleting `installed.lock` and
> re-running the wizard against the same DB.

---

## 7. Backup & restore

**Backup** (cron weekly or on demand):

```bash
mysqldump -u USER -p DBNAME > eqsl-$(date +%F).sql
tar -czf eqsl-files-$(date +%F).tar.gz webroot/files/
```

**Restore:**

```bash
mysql -u USER -p DBNAME < eqsl-YYYY-MM-DD.sql
tar -xzf eqsl-files-YYYY-MM-DD.tar.gz -C /path/to/public_html/
```

Back up `config/app_local.php` separately — it holds the DB password and
security salt.

---

## 8. Shared-host caveats

- **PHP version selector** (cPanel MultiPHP / DirectAdmin Select PHP):
  pick **8.1+**. eQSL-card hard-fails on 8.0.
- **MariaDB**: 10.6+ required (MySQL 5.7+ also OK). Older 10.3 rejects the
  JSON column type in `templates.layout_json`.
- **File-manager flags**: enable "recurse into subdirectories" when
  chmodding `tmp/`, `logs/`, `webroot/files/`.
- **Max upload size**: set `upload_max_filesize` and `post_max_size` to at
  least `8M` (PHP Selector "Options" or `.user.ini`). Camera captures and
  ADIF imports otherwise hit silent 413s.
- **Required PHP extensions**: `gd` (or `imagick`), `intl`, `mbstring`,
  `pdo_mysql`, `zip`, `fileinfo`.

---

## 9. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| White page at `/` | PHP fatal, silently logged | `tail logs/error.log`; enable `display_errors` temporarily in `.user.ini`. |
| HTTP 500 | Wrong PHP, missing extension, `.htaccess` not honoured | Confirm PHP 8.1+, enable extensions, ensure `AllowOverride All`. |
| `/install` returns 404 | `config/installed.lock` already exists | Delete the lock to re-run the wizard (data is preserved). |
| Migrations fail | DB user lacks `CREATE`/`ALTER`, or MariaDB <10.6 | Grant full DB privileges; upgrade MariaDB. |
| Cards 403 on download | `webroot/files/` not writable | Re-chmod `0775` recursively. |
| Mail not sending | Host blocks outbound SMTP | Use the host's SMTP relay; configure in `config/app_local.php`. |

For anything else: `tail -f logs/error.log`.

---

## 10. Troubleshooting (detailed)

### "/" redirects in a loop or returns blank

- Check that `config/app_local.php` exists and contains valid DB credentials.
- Confirm `tmp/`, `logs/`, and `webroot/files/` are writable (cPanel File Manager → Permissions → 0775 for directories).
- Tail `logs/error.log` for the first failure.

### `/install` returns 404 after deploy

- The installer locks itself once complete. To re-run, FTP-delete `config/installed.lock` (only do this on a fresh empty database — re-running on an existing DB will create a duplicate admin user).

### "Class 'PDO' not found"

- Your shared-host PHP doesn't have `pdo_mysql` enabled. Most cPanel hosts let you enable it under **PHP Selector → Extensions**.

### Generated cards have no text overlaid

- The `webroot/files/fonts/` directory was not uploaded. Re-upload the release zip and verify `Inter-Regular.ttf`, `Cinzel-Regular.ttf`, etc. exist on disk.
- Verify the `gd` extension is compiled with FreeType: `php -r "var_dump(gd_info()['FreeType Support']);"` should print `bool(true)`.

### "MysqlAdapter::TEXT_LONG" error during install

- Your host runs MySQL 5.6 or older — upgrade to MySQL 5.7+ or MariaDB 10.x. The `templates.layout_json` LONGTEXT column requires it.

### Mail isn't sending

- Visit `/admin/settings` (admin only) and verify SMTP host/user/password/from. Many shared hosts block outbound port 25; use port 587 with TLS.

---

## 11. Common cPanel quirks

- **Document root:** cPanel typically maps your domain to `public_html/`. Extract the release zip directly into `public_html/` (not a subfolder).
- **Hidden file visibility:** cPanel File Manager hides dotfiles by default. Enable "Show Hidden Files" in Settings before uploading or you'll miss `.htaccess` and `.editorconfig`.
- **PHP version:** cPanel's "MultiPHP Manager" must point your domain at PHP 8.1 (not the system default).
- **Mod_rewrite:** Some discount hosts disable it by default. The installer's first step verifies routing — if it returns 404, contact support.
- **Symlinks:** Some hosts disable `symlink()`. The recommended subfolder layout (app outside `public_html/`) requires it; if disabled, fall back to placing the entire app inside `public_html/` with the bundled `.htaccess` deny rules.
- **Cron:** Not required by this app (no background workers), but if you want admin cleanup tools to run on a schedule, you can wire `bin/cake` calls via cPanel cron once SSH is available — for now run via `/admin/cleanup`.
