<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Audit log — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How to read and filter the eQSL Card admin audit log.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'The audit log at /admin/audit is a tamper-evident record of every significant admin action — settings changes, user role promotions, template approvals, and cleanup runs.',
]) ?>

<h2>What gets logged</h2>
<p>Every action that changes system state writes a row to the <code>audit_logs</code> table. The current event types are:</p>
<ul>
  <li><code>settings.updated</code> — admin saved changes on the settings page; the <em>metadata</em> column lists which keys changed.</li>
  <li><code>settings.default_background_changed</code> / <code>settings.default_background_reset</code> — default card background was replaced or reset to the bundled image.</li>
  <li><code>user.role_changed</code> — a user's role was promoted or demoted; metadata includes the old and new role.</li>
  <li><code>user.deleted</code> — an account was soft-deleted.</li>
  <li><code>template.public_requested</code> — a user submitted a template for public gallery review.</li>
  <li><code>template.approved</code> / <code>template.rejected</code> — an admin approved or rejected a gallery submission.</li>
  <li><code>callsign_directory.imported</code> — a CSV was uploaded; metadata includes row counts.</li>
  <li><code>callsign_directory.cleared</code> — the full directory was wiped.</li>
  <li><code>cleanup.*</code> — guest card purge, orphan upload prune, card expiry, cache clear, log clear, session clear.</li>
</ul>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/admin/audit/log-table.webp',
    'alt' => 'The audit log table showing event name, actor callsign, metadata, and timestamp columns',
    'caption' => 'The audit log table, newest entries first.',
]) ?>

<h2>Filtering the log</h2>
<p>Use the filter bar above the table to narrow the view:</p>
<ul>
  <li><strong>Event type</strong> — a dropdown of every distinct event name currently in the log. The list grows organically as new events are recorded — only event types that have actually fired appear here.</li>
  <li><strong>Actor</strong> — filter by user ID to see only actions taken by a specific admin.</li>
</ul>
<p>The log is paginated at 50 rows per page, newest first. Filters combine (event type AND actor).</p>

<h2>Reading a log row</h2>
<p>Each row shows:</p>
<ul>
  <li><strong>Event</strong> — dot-separated event name (e.g. <code>cleanup.cache_cleared</code>).</li>
  <li><strong>Actor</strong> — the admin who triggered the action, linked to their user row.</li>
  <li><strong>Target</strong> — where applicable, the type and ID of the object affected (e.g. <code>Users #42</code>).</li>
  <li><strong>Metadata</strong> — a JSON column with event-specific detail. For <code>settings.updated</code> this is the list of changed keys; for <code>cleanup.guest_cards_purged</code> it's <code>{"days":30,"count":17}</code>.</li>
  <li><strong>When</strong> — UTC timestamp.</li>
</ul>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'The audit log is append-only from the UI — there\'s no delete button. Rows can only be removed via direct database access. This is intentional so that the log remains a trustworthy record of admin activity.',
]) ?>

<h2>Dashboard summary</h2>
<p>The admin dashboard (<a href="/admin">/admin</a>) shows the 20 most recent audit events in a condensed list. The full paginated drill-down is here at <code>/admin/audit</code>.</p>
