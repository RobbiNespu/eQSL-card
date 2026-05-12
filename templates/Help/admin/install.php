<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'First-time install + setup — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="First-time installation + first-admin setup walkthrough for the eQSL Card site.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'First-time setup of an eQSL Card site — the in-app install wizard takes you from a fresh FTP upload to a working signed-in admin account in about five minutes.',
]) ?>

<h2>Before you begin</h2>
<p>The shared host or VPS needs:</p>
<ul>
  <li><strong>PHP 8.1 or higher</strong>, with these extensions enabled: <code>mbstring</code>, <code>intl</code>, <code>gd</code>, <code>pdo_mysql</code>, <code>openssl</code>, <code>fileinfo</code>.</li>
  <li><strong>MariaDB 10.6+ or MySQL 8+</strong> with an empty database + a user that has CREATE/ALTER/DROP rights.</li>
  <li>Write access to <code>webroot/files/</code>, <code>tmp/</code>, and <code>logs/</code> from the PHP user.</li>
  <li>Outbound HTTPS from the browser so the bundled Inter + Geist Mono fonts and Alpine.js load from <code>cdn.jsdelivr.net</code>.</li>
</ul>
<p>If you built the deployable zip via <code>./scripts/build-release.sh</code> the <code>vendor/</code> directory is already inside. Otherwise SSH to the host and run <code>composer install --no-dev --optimize-autoloader</code> before continuing.</p>

<h2>Stage 1 — System check</h2>
<p>Visit <code>https://your-domain/install</code>. The wizard's first screen runs an environment audit and shows each requirement with a green ✓ or red ✗.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/admin/install/stage-1-syscheck.webp',
    'alt' => 'Install wizard system check showing all green ticks',
    'caption' => 'Stage 1 — every row should be green before you can continue.',
]) ?>

<p>Common failures and what they mean:</p>
<ul>
  <li><strong>"PHP ≥ 8.1: ✗"</strong> — the host runs an older PHP. Most shared hosts have a control-panel toggle to bump the PHP version per directory.</li>
  <li><strong>"intl extension: ✗"</strong> — uncomment the <code>extension=intl</code> line in <code>php.ini</code>, or ask the host to enable it.</li>
  <li><strong>"webroot/files writable: ✗"</strong> — the directory exists but the PHP user can't write to it. <code>chmod 775</code> + correct ownership usually fixes it.</li>
</ul>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/admin/install/syscheck-failure.webp',
    'alt' => 'System check screen with two red crosses on missing extensions',
    'caption' => 'A failed check stops the wizard until you fix it.',
]) ?>

<h2>Stage 2 — Database</h2>
<p>The wizard collects the database DSN: host, port, name, user, password. Submit, and the wizard tests the connection, runs the bundled migrations, and writes a minimal <code>config/app_local.php</code> with the credentials and a freshly-generated app salt.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/admin/install/stage-2-db.webp',
    'alt' => 'Database configuration form with host, name, user, password fields',
    'caption' => 'Stage 2 — the wizard writes app_local.php for you.',
]) ?>

<?= $this->element('ui/callout', [
    'variant' => 'warning',
    'body' => 'The database user only needs CREATE/ALTER/DROP rights during install. Once migrations are done, you can revoke those rights and leave SELECT/INSERT/UPDATE/DELETE for normal operation.',
]) ?>

<h2>Stage 3 — First admin account</h2>
<p>The wizard prompts for the initial admin user: name, callsign, email, password. This step happens exactly once. Subsequent admins are created via <a href="/help/admin/users">user management</a> — promoting an existing user from "user" to "admin".</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/admin/install/stage-3-admin.webp',
    'alt' => 'First-admin form with name, callsign, email, password fields',
    'caption' => 'Stage 3 — your first admin.',
]) ?>

<?= $this->element('ui/callout', [
    'variant' => 'warning',
    'body' => 'Pick a strong password and capture it in your password manager BEFORE clicking Submit. If you forget it before email is configured, you\'ll need direct database access to reset it — and that means using the SQL pattern documented in the Locked-out section of /admin/settings.',
]) ?>

<h2>Stage 4 — Done</h2>
<p>The wizard writes <code>config/installed.lock</code> + <code>tmp/installed.lock</code> as guards, then lands you on a "Setup complete" page with a sign-in link. From there:</p>
<ol>
  <li>Sign in as your new admin.</li>
  <li>Open <a href="/admin/settings">/admin/settings</a> and configure SMTP if you want password-reset emails to work.</li>
  <li>Upload a default eQSL background under the same settings page.</li>
  <li>Optionally enable the callsign auto-complete and pick provider order.</li>
</ol>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/admin/install/stage-4-done.webp',
    'alt' => 'Setup complete screen with a green checkmark and sign-in link',
    'caption' => 'Stage 4 — sign in and start configuring.',
]) ?>

<h2>What lives on disk after install</h2>
<ul>
  <li><code>config/app_local.php</code> — your DB credentials + salt. Keep it out of git (the .gitignore already excludes it).</li>
  <li><code>config/installed.lock</code> + <code>tmp/installed.lock</code> — guards that prevent the install wizard from running a second time. Delete both ONLY if you want to nuke the install and start over.</li>
  <li><code>webroot/files/uploads/</code> — user-uploaded background images. Back this up.</li>
  <li><code>webroot/files/cards/</code> — generated card PNGs/PDFs. Back this up too.</li>
</ul>

<h2>Next up</h2>
<p><a href="/help/admin/settings">Site settings</a> walks the SMTP / retention / security knobs. <a href="/help/admin/cleanup">Storage cleanup</a> covers the periodic maintenance tasks.</p>
