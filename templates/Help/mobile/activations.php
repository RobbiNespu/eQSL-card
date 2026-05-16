<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Activations (POTA, SOTA, field day) — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="Activations group consecutive QSOs into named portable sessions. Start one at the start of your POTA / SOTA / field day, end it when you're done, and every QSO you logged in between gets tagged with it.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'An activation is a named portable session — POTA, SOTA, IOTA, field day, kampung activation. Start one when you arrive at the site, end it when you pack up, and every QSO you log via /qsos/quick in between is tagged with it. Later you can export just that session\'s QSOs in ADIF for the awards portal.',
]) ?>

<h2>When to use an activation</h2>
<ul>
  <li><strong>POTA</strong> — start an activation for the park you're operating from (e.g. <code>POTA-K-1234</code>). When you're done, end it. The park reference is the code; the park name is the friendly label.</li>
  <li><strong>SOTA</strong> — same pattern with the summit reference (e.g. <code>SOTA-9M2/PR-001</code>).</li>
  <li><strong>Field day / contest</strong> — start one named after the contest. Every contact logged during it gets the group tag.</li>
  <li><strong>Kampung activation</strong> — for sites without a formal reference, the code is whatever you want (e.g. <code>BL-2026-05-16</code>) and the name is descriptive.</li>
</ul>

<p>You can also log <em>without</em> an active activation — the quick-add form shows a small note at the top in that case ("Logging without an activation. Start one if you're running a session"). Casual QSOs from home don't need activations.</p>

<h2>Starting an activation</h2>
<p>Go to <a href="/activations">/activations</a>. The page shows three sections:</p>
<ol>
  <li><strong>Active right now</strong> — your open activation (if any), with an "End now" button.</li>
  <li><strong>Start a new activation</strong> — inline form. Fill in code, name, optionally grid square and notes, tap "Start activation".</li>
  <li><strong>Recent activations</strong> — a list of past sessions.</li>
</ol>

<p>The <strong>started_at</strong> timestamp is set by the server to the moment you submit the form. You can't pick a past start time from this UI — if you need to log a backdated activation, do it through the database directly (or edit the activation after starting).</p>

<h2>The active-activation banner on Quick add</h2>
<p>When you have an open activation, the <a href="/qsos/quick">Quick add page</a> shows a green banner at the top:</p>

<pre><code>Logging for  Bukit Larut SOTA  OJ02wx</code></pre>

<p>Tap the banner to jump to /activations and manage it. <strong>Every QSO you save from the Quick add form while this banner is visible auto-tags with the activation</strong> — the server reads the active row at save time and sets the QSO's <code>activation_id</code> for you, no per-contact UI needed.</p>

<p>The auto-tag is server-side and ownership-scoped: you can't tag a QSO with another operator's activation, even if you guess their activation ID and POST it. The Qso entity locks <code>activation_id</code> from mass assignment; only the server sets it, and only from your own active activation list.</p>

<p>If there's no active activation, the banner area instead shows a small "Start one" prompt linking to /activations.</p>

<h2>Ending an activation</h2>
<p>From the /activations page, tap <strong>End now</strong> on the active activation card (or the End button in the recent-activations row). The server stamps <code>ended_at</code> to the current UTC time. New QSOs logged after this point won't auto-tag with this activation — start a new one if you're moving to a different site.</p>

<p>You can have multiple activations <em>technically</em> open at once (the system doesn't enforce single-active), but only the most-recently-started one shows in the banner and gets used for auto-tagging. If you want to switch from one open activation to another, end the first.</p>

<h2>Exporting to POTA / SOTA / LoTW (ADIF)</h2>
<p>Once an activation is done (or even while it's still running), you can download an ADIF file with all its QSOs:</p>
<ul>
  <li>On the active card: tap <strong>Export ADIF</strong>.</li>
  <li>On any recent row: tap the <strong>ADIF</strong> button in the actions column.</li>
  <li>Or visit <code>/activations/{id}/export.adi</code> directly.</li>
</ul>

<p>The download is a plain-text ADIF 3.1.4 document. Filename is derived from the activation code (e.g. <code>POTA-K-1234.adi</code>). Upload it to:</p>
<ul>
  <li><strong>POTA</strong> — <a href="https://pota.app/#/user/logs" rel="noopener">pota.app → My Account → Logs</a>.</li>
  <li><strong>SOTA</strong> — <a href="https://www.sotadata.org.uk/" rel="noopener">sotadata.org.uk → Submit log</a>.</li>
  <li><strong>LoTW</strong> — TQSL desktop app, then upload the signed file to ARRL.</li>
  <li><strong>QRZ.com / eQSL.cc</strong> — both portals accept ADIF in their upload form.</li>
</ul>

<h3>What's in the file</h3>
<p>Each QSO record has the standard fields (CALL, QSO_DATE, TIME_ON, BAND, MODE, FREQ, RST_SENT, RST_RCVD) plus the activator metadata the awards portals key on:</p>
<ul>
  <li><code>STATION_CALLSIGN</code> and <code>OPERATOR</code> — your callsign (from your profile).</li>
  <li><code>MY_GRIDSQUARE</code> — the activation's grid square, overriding any per-QSO grid.</li>
  <li><code>MY_POTA_REF</code> — set when the activation code starts with <code>POTA-</code> or <code>POTA </code>. The portal reads this to credit you for the park.</li>
  <li><code>MY_SOTA_REF</code> — same pattern for <code>SOTA-</code> codes.</li>
  <li><code>MY_IOTA</code> — same for <code>IOTA-</code>.</li>
</ul>

<p>If your activation code is free-text (e.g. <code>BL-2026-05-16</code> for a kampung site that has no formal reference), none of those MY_* fields appear — the file is still valid ADIF and uploadable, just without award-portal-specific tags.</p>

<p>Worked-station details ship if you logged them: <code>GRIDSQUARE</code>, <code>NAME</code>, <code>QTH</code>, <code>NOTES</code>. Empty fields are omitted entirely (no <code>&lt;NAME:0&gt;</code> noise).</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'You can export a still-active activation — useful when running a long net or all-day field day, you can upload partial results periodically without ending the session. Awards portals dedupe by callsign + datetime + band so re-uploading later won\'t double-count.',
]) ?>

<h2>Editing and deleting</h2>
<p>The <strong>Edit</strong> link on each activation row lets you rename, fix the grid square, or update notes. The <code>started_at</code> and <code>ended_at</code> fields are not exposed — those are operational signals owned by the start/end actions.</p>

<p>The <strong>Delete</strong> action is hard-delete (the activation row goes away), but QSOs logged under it stay in your logbook — their <code>activation_id</code> reverts to NULL. The system never destroys real contact data because the grouping pointer changed.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'For repeat sites (a park you activate often), reuse the same code + name combo. The recent-activations list makes it easy to find the previous one\'s grid square so you don\'t have to look it up.',
]) ?>

<h2>What ships next</h2>
<p>Three more features round out the activations workflow (still in progress):</p>
<ul>
  <li><strong>T15 — GPS auto-fill</strong>: optional browser geolocation on activation start, derives Maidenhead grid square from your lat/lon.</li>
</ul>
