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
