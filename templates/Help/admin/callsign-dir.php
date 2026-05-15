<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Callsign directory CSV upload — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How to upload a local callsign directory CSV so eQSL Card can auto-complete operator details without external API calls.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Upload a CSV of callsign records to give eQSL Card a local, offline-first source of operator name, QTH, and grid data for the auto-complete feature.',
]) ?>

<h2>Why a local directory</h2>
<p>External lookup providers (MCMC, RadioID, QRZ) require an outbound network call for each new callsign. A local directory eliminates that round-trip for the callsigns you care about most — your club members, regular net participants, frequent contacts. It also works on installs with no internet access or strict firewall rules.</p>
<p>The local directory is always checked <em>first</em> in the provider chain, regardless of the drag order configured in settings. A hit here prevents any external call from firing.</p>

<h2>CSV format</h2>
<p>The importer recognises these column headers (case-insensitive, order doesn't matter):</p>
<table class="table table-sm">
  <thead>
    <tr><th>Column</th><th>Aliases</th><th>Required?</th></tr>
  </thead>
  <tbody>
    <tr><td><code>callsign</code></td><td><code>call</code>, <code>call_sign</code></td><td>Yes</td></tr>
    <tr><td><code>name</code></td><td><code>full_name</code>, <code>operator_name</code></td><td>No</td></tr>
    <tr><td><code>qth</code></td><td><code>city</code>, <code>location</code>, <code>address</code></td><td>No</td></tr>
    <tr><td><code>grid</code></td><td><code>grid_square</code>, <code>locator</code>, <code>maidenhead</code></td><td>No</td></tr>
  </tbody>
</table>
<p>Any extra columns are ignored. UTF-8 encoding is expected. The importer skips rows where the callsign column is empty.</p>

<h2>Uploading</h2>
<p>Go to <a href="/admin/callsign-lookups/provider/local">/admin/callsign-lookups/provider/local</a>. The upload form has two fields:</p>
<ul>
  <li><strong>CSV file</strong> — pick the file. No size limit other than the PHP <code>upload_max_filesize</code>.</li>
  <li><strong>Source label</strong> (optional) — a human-readable tag stored with each imported row, e.g. <em>MCMC 2026-Q1</em> or <em>Club roster May 2026</em>. Useful for knowing where a record came from when you view the directory table.</li>
</ul>
<p>Click <strong>Upload</strong>. The importer processes the file row by row, inserting new records and updating existing ones (matched on callsign). A summary is shown: how many were imported, updated, and skipped.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/admin/callsign-dir/upload-form.webp',
    'alt' => 'The callsign directory upload form with file input and source label field',
    'caption' => 'Upload a CSV; the source label tags the batch for future reference.',
]) ?>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'Re-uploading a CSV with updated data is safe — existing rows are updated rather than duplicated. You can run the upload as often as the source data changes (e.g. quarterly after an MCMC licence renewal cycle).',
]) ?>

<h2>Browsing the directory</h2>
<p>The table below the upload form shows all imported records, paginated and searchable by callsign. Use the search box to check if a specific callsign is present.</p>

<h2>Clearing the directory</h2>
<p>Click <strong>Clear all</strong> to wipe the entire table. This removes all imported records but does <em>not</em> clear the <code>callsign_lookups</code> cache — cached results from previous lookups continue to be returned until the cache expires (90 days) or is manually cleared on the <a href="/admin/cleanup">cleanup page</a>.</p>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'Clearing the directory only removes the source records. If you want lookups to immediately stop returning stale local data, clear the directory AND clear the callsign cache on the cleanup page.',
]) ?>
