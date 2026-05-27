<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Analytics & exports — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="Post-net analytics — signal distribution, participant map, retention metrics — plus ADIF and PDF export for LoTW upload and record-keeping.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'After the net ends, review signal health, plot where your participants were, track who keeps coming back, and export the log for LoTW or club records.',
]) ?>

<h2>The analytics page</h2>
<p>Open the analytics page from the session detail view (<code>/net-sessions/{id}</code>) by clicking <strong>Analytics</strong>. The URL is <code>/net-sessions/{id}/analytics</code>. This page is owner-only (co-loggers are redirected to 404).</p>

<p>Analytics are available as soon as any check-ins exist — you can open the page while the net is still live if you want a mid-net snapshot, though the full picture only makes sense after the net ends.</p>

<h2>Signal distribution</h2>
<p>The signal distribution chart shows how many check-ins arrived at each signal strength level from S1 to S9. The strength is derived from the <em>received</em> RST report — specifically the signal strength digit (the middle digit of a phone RST, e.g. "5<strong>9</strong>" = S9, "5<strong>7</strong>" = S7). CW RST works the same way.</p>

<p>The chart uses S1–S9 on the horizontal axis and check-in count on the vertical axis. A healthy net on a good frequency typically shows a cluster around S7–S9. If you see a lot of S3–S5 check-ins, the band or antenna may need attention.</p>

<p>Check-ins where RST was left blank (or set to "59" without reflection) show as S9. If you want meaningful signal data, ask each station their actual received RST and log it faithfully.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'During the net, the cockpit\'s stat tiles show total check-ins and unique callsigns in real time. The signal chart is only on the analytics page — check it after the net for the full picture.',
]) ?>

<h2>Participant map</h2>
<p>The participant map plots each check-in as a marker on a world map, using the Maidenhead grid square logged in the cockpit. Hover a marker to see the callsign and signal strength.</p>

<p>Only check-ins that have a grid square logged will appear as markers. Check-ins without a grid square are still counted in the stats but do not appear on the map. If most of your participants are missing from the map, remind your net preamble to request grid squares.</p>

<p>If the page loads without internet access (or if the Leaflet tile server is unreachable), the map container will be blank. A plain-text list of callsigns with grids is always present below the map as a fallback.</p>

<h2>Participation &amp; retention</h2>
<p>The retention block looks at the last 8 ended sessions that share the same net title and owner. It shows:</p>

<ul>
  <li><strong>Session-to-session retention</strong> — the fraction of last session's unique callsigns who also appeared in the current session. For example, 75% means three-quarters of last week's participants checked in again this week.</li>
  <li><strong>Regulars</strong> — callsigns that appeared in at least 50% of the recent sessions in the window. These are your reliable check-ins.</li>
  <li><strong>Longest streak</strong> — the maximum number of <em>consecutive</em> sessions that any single callsign attended within the retention window. The callsign (or callsigns, if there is a tie) holding that record is listed beside the number. For example, "5 — W1ABC" means W1ABC checked in to every one of the last five sessions in a row. Use this to recognise your most committed participants.</li>
  <li><strong>Session history table</strong> — a compact table showing session IDs and unique callsign counts for the recent window, so you can spot trends at a glance.</li>
</ul>

<p>Retention figures require at least two ended sessions with the identical net title. A new net, or one that has only run once, will show a dash instead of a percentage. The longest-streak figure likewise shows a dash if the window contains fewer than two sessions.</p>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'The retention window is the last 8 sessions (matching title + owner). Sessions with a different title — even a minor spelling difference — are treated as a different net. Keep your net title consistent across weeks.',
]) ?>

<h2>ADIF export</h2>
<p>Download an ADIF 3.1.4 file from <code>/net-sessions/{id}/export.adi</code> (the button is labeled <strong>Export ADIF</strong> on the session detail page). The file contains one ADIF record per check-in, formatted for upload to LoTW, QRZ, or any ADIF-compatible portal.</p>

<p>Each record includes: callsign, band, mode, frequency, UTC date and time, RST sent/received, the net title (in the NOTES field), and the NCS callsign (the session owner's callsign). The exporter reuses the same ADIF exporter used for regular QSO exports, ensuring consistency with LoTW's field requirements.</p>

<p>Both the owner and co-loggers can download the export. The file is named <code>net-{id}.adi</code>.</p>

<h2>PDF net report</h2>
<p>Download a formatted A4 PDF report from <code>/net-sessions/{id}/export.pdf</code> (the button is labeled <strong>Export PDF</strong> on the session detail page). The report includes:</p>

<ul>
  <li>A header with the net title, organisation, band, mode, frequency, and session dates.</li>
  <li>Summary statistics: total check-ins and unique callsigns.</li>
  <li>The full check-in roster in chronological order: callsign, name, grid, RST, role, and UTC time.</li>
</ul>

<p>The PDF is suitable for club archives, trustee records, or printing. Both the owner and co-loggers can download it. The file is named <code>net-{id}-report.pdf</code>.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body'    => 'Export the ADIF immediately after ending the net — while the session is fresh. LoTW uploads go much faster when the log is submitted within 24 hours of the QSOs.',
]) ?>
