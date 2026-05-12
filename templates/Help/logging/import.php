<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Import an ADIF / CSV log — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="Bulk-import an ADIF or CSV export from your existing logging program.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Got an existing logbook from another program? Upload it once and skip months of re-typing.',
]) ?>

<h2>Supported formats</h2>
<ul>
  <li><strong>ADIF</strong> (.adi or .adif) — the amateur radio standard. Export from N3FJP, Log4OM, HRD, fldigi, WSJT-X, Cloudlog, or any tool that follows the ADIF spec.</li>
  <li><strong>CSV</strong> (.csv) — flexible fallback for tools that don't speak ADIF. Headers are case-insensitive; the parser maps common synonyms automatically.</li>
</ul>

<h2>What gets imported</h2>
<p>The parser recognises these fields and maps them to the QSO record:</p>
<ul>
  <li>Callsign (required) — ADIF <code>CALL</code> / CSV <code>callsign</code>, <code>call</code>, or <code>call_sign</code>.</li>
  <li>Date + Time UTC (required) — ADIF <code>QSO_DATE</code> + <code>TIME_ON</code> combined.</li>
  <li>Band, Mode, Frequency.</li>
  <li>RST sent (<code>RST_SENT</code>) and received (<code>RST_RCVD</code>).</li>
  <li>Notes — ADIF <code>COMMENT</code> or <code>NOTES</code>.</li>
</ul>
<p>Other ADIF fields (operator name, QTH, grid square, etc.) are also captured if present.</p>

<h2>Duplicate handling</h2>
<p>The importer dedupes on the pair <code>(callsign, datetime UTC)</code>. If a row matches an existing QSO already in your logbook, it's silently skipped — no need to manually clean exports.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/logging/import/upload-form.webp',
    'alt' => 'Import logbook form showing a single file input that accepts .adi, .adif, or .csv',
    'caption' => 'Step 1 — upload the file.',
]) ?>

<h2>The two-step flow</h2>
<p>Imports happen in two confirmation steps to prevent surprises:</p>
<ol>
  <li><strong>Upload + parse.</strong> Visit <a href="/qsos/import">/qsos/import</a>, pick your file, click <strong>Parse</strong>. The server reads the file locally and shows you a summary: how many QSOs are valid, how many are duplicates, how many failed to parse, with the first few records previewed.</li>
  <li><strong>Confirm.</strong> If the preview looks right, click <strong>Import these QSOs</strong> and the valid rows get persisted in one transaction. Nothing is saved until you confirm.</li>
</ol>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/logging/import/preview-screen.webp',
    'alt' => 'Import preview showing 142 new QSOs ready, 8 duplicates skipped, with the first few rows previewed',
    'caption' => 'Step 2 — review the summary and confirm.',
]) ?>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'Parser warnings (e.g. an unrecognised mode code) are listed in a collapsible "Parser warnings" section. They\'re informational — the row still imports if the required fields parsed cleanly.',
]) ?>

<h2>What's next</h2>
<p>After importing you can browse all your QSOs in the <a href="/qsos">logbook</a> and bulk-render eQSL cards for any selection — see <a href="/help/cards/bulk-render">Bulk-render many cards</a>.</p>
