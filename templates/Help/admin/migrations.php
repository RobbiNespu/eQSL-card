<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Running migrations — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How to apply database migrations when upgrading eQSL Card on shared hosting with no SSH access.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'After uploading a new release via FTP, visit /admin/upgrade to apply any pending database migrations and clear caches — no SSH or command line needed.',
]) ?>

<h2>Why migrations matter</h2>
<p>Each release may add new database tables, columns, or indexes to match the new code. Running a new version without applying its migrations produces <em>Column not found</em> or <em>Table doesn't exist</em> errors. The upgrade page makes applying migrations a one-click operation that works on shared hosting where shell access is unavailable.</p>

<h2>The upgrade page</h2>
<p>Go to <a href="/admin/upgrade">/admin/upgrade</a>. The page lists every known migration and its current status:</p>
<ul>
  <li><strong>up</strong> — already applied. This migration is in the database.</li>
  <li><strong>down</strong> — pending. This migration has not run yet.</li>
</ul>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/admin/migrations/status-table.webp',
    'alt' => 'The migration status table showing five "up" rows and two "down" rows',
    'caption' => 'Pending (down) rows need to run before the new code can work correctly.',
]) ?>

<h2>Running pending migrations</h2>
<p>If any rows show <strong>down</strong>, click <strong>Apply pending migrations</strong>. The server runs Phinx migrations in order, then clears all CakePHP caches. The page reloads and every row should now show <strong>up</strong>.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'Run migrations immediately after uploading new files — before any user makes a request that hits the updated code. The window between file upload and migration is the only moment you\'re at risk of an error. If the site is public, consider putting it into maintenance mode (a static HTML page served by .htaccess) for the 30 seconds the migration takes.',
]) ?>

<h2>What happens under the hood</h2>
<p>The button POSTs to <code>/admin/upgrade</code>, which calls <code>Migrations::migrate()</code> (CakePHP's Phinx wrapper) on the default database connection. Phinx reads migrations from <code>config/Migrations/</code>, skips already-applied ones, and runs the rest in sequence inside a transaction where the database supports it. After a successful run, <code>Cache::clearAll()</code> drops any cached model schema or ORM introspection that might reference the old schema.</p>

<h2>If the migration fails</h2>
<p>The page displays the error message from Phinx. Common causes:</p>
<ul>
  <li><strong>Permission denied</strong> — the DB user lacks ALTER or CREATE rights. Grant those rights during the migration and revoke them afterwards.</li>
  <li><strong>Duplicate column</strong> — a migration was partially applied manually. Use a MySQL client to check the <code>phinxlog</code> table and remove the conflicting row, then re-run.</li>
  <li><strong>Syntax error</strong> — a corrupted migration file. Re-download the release zip and re-upload <code>config/Migrations/</code>.</li>
</ul>

<?= $this->element('ui/callout', [
    'variant' => 'warning',
    'body' => 'Never delete rows from the <code>phinxlog</code> table unless you\'re specifically recovering from a failed partial migration. Deleting a row for an already-applied migration causes Phinx to try to run it again, which will fail with a duplicate-column error.',
]) ?>

<h2>Rollback</h2>
<p>The upgrade page only runs <em>up</em> migrations. Rolling back requires shell access (<code>bin/cake migrations rollback</code>) or manual SQL. For shared hosting with no SSH, the safest rollback strategy is to restore a database backup taken before the upgrade — which is why you should <strong>always back up the database before upgrading</strong>.</p>

<h2>Next up</h2>
<p>After migrations, check <a href="/admin/settings">/admin/settings</a> to see if any new settings fields appeared that need configuring in the new release.</p>
