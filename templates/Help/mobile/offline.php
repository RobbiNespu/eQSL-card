<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Logging offline — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How eQSL Card logs QSOs when there's no cell signal — the IndexedDB offline queue, the sync engine that drains it when connectivity returns, the status pill, and the conflict-resolution rules.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'When the browser has no network, quick-add quietly switches to an offline queue stored in your device. The queue survives reload, app restart, and reboot. When connectivity returns, the queued QSOs flush to the server in order — no manual sync, no lost contacts.',
]) ?>

<h2>What works offline</h2>
<ul>
  <li><strong><a href="/help/mobile/quick-add">Quick-add</a></strong> (<code>/qsos/quick</code>) — the main portable logging path. Every save when offline drops into the queue.</li>
  <li><strong>The recents panel</strong> — your last five QSOs render from the cached page, including queued rows pinned at the top with a <strong>⏳ queued</strong> pill.</li>
  <li><strong>Browse cached pages</strong> — anything you visited online recently (dashboard, logbook, help articles) loads from the service worker cache.</li>
</ul>

<h2>What does NOT work offline</h2>
<ul>
  <li><strong>The full add form</strong> (<code>/qsos/new</code>). Offline support is intentionally scoped to quick-add — that's the form built for the field. Use quick-add for any QSO you might log without a signal.</li>
  <li><strong>Login / logout, admin pages, password reset, the installer.</strong> These are <code>network-only</code> — they fail with a clear "you're offline" message rather than serving a stale auth page.</li>
  <li><strong>Bulk-render, template edits, file uploads.</strong> Anything that requires the server to do real work needs a live connection.</li>
  <li><strong>Search across the full logbook.</strong> The cached logbook page only contains the rows that were on screen when you last loaded it.</li>
</ul>

<h2>The offline queue</h2>
<p>Storage: a private IndexedDB database called <code>eqsl-card-offline</code> in the browser, table <code>qsos</code>. Each queued row holds the same fields as the server-side QSO record — callsign, frequency, mode, RST sent/received, notes, activation reference if any — plus three offline-specific columns:</p>

<ul>
  <li><strong><code>client_uuid</code></strong> — a UUID generated when the row is queued. This is the dedup key during sync; the server returns the existing row instead of creating a duplicate if it sees the same UUID twice.</li>
  <li><strong><code>queued_at</code></strong> — monotonic timestamp used to drain the queue oldest-first. Two QSOs queued in the same millisecond still get distinct ordering so the chronology is preserved.</li>
  <li><strong><code>last_error</code></strong> — optional. If a sync attempt failed (validation rejected the row, server returned 5xx), the human-readable error sticks here so you can review and decide whether to retry or drop.</li>
</ul>

<p>The queue is scoped per browser. Two phones each queueing under the same account each have their own local store; rows don't sync between devices until they reach the server.</p>

<h3>What triggers queueing</h3>
<ul>
  <li><code>navigator.onLine === false</code> at save time. Most reliable signal — the OS reports it.</li>
  <li>The <code>fetch()</code> POST throws a network error (DNS failure, connection timeout, server unreachable). Catches the case where <code>navigator.onLine</code> is wrong about connectivity, which happens often on captive-portal wifi or one-bar cellular.</li>
  <li>The server returns 5xx. Treated as a transient infrastructure failure — the row stays queued and will retry.</li>
</ul>

<h3>What does NOT trigger queueing</h3>
<ul>
  <li>A server-side validation rejection (HTTP 422). The row never enters the queue — the form shows the field-level error and lets you fix it. If a queued row hits 422 during sync, it stays queued with <code>last_error</code> set and waits for manual review (you can fix the data via a quick edit, or delete the row from the status-pill sheet).</li>
</ul>

<h2>The sync engine</h2>
<p>Sync runs automatically. You don't need to open a menu, tap a button, or even know it's happening. Triggers:</p>

<ol>
  <li><strong>The browser <code>online</code> event fires</strong> — most common trigger. Phone left an underground car park, hotspot reconnected, etc.</li>
  <li><strong>A page load completes while there are queued rows.</strong> Catches the case where you launch the app already-online — no need to wait for an explicit connectivity transition.</li>
  <li><strong>A 60-second background poll.</strong> Only runs while there are queued rows AND the browser reports online. Catches drains that the first two triggers missed (event lost, sync interrupted, etc.). Does <em>not</em> wake the device or use battery when the app isn't open.</li>
</ol>

<p>Once triggered, the engine:</p>
<ol>
  <li>Reads queued rows oldest-first by <code>queued_at</code>.</li>
  <li>POSTs each to <code>/qsos/quick</code> with its <code>client_uuid</code>.</li>
  <li>On HTTP 2xx — removes the row from IndexedDB. Done.</li>
  <li>On HTTP 422 — marks the row with the validation error in <code>last_error</code>, leaves it for manual review, and continues with the next row.</li>
  <li>On HTTP 5xx or network error — marks the row with the error and <strong>aborts the rest of the drain</strong>. This preserves chronological order for the next attempt; a transient outage doesn't get the queue rearranged.</li>
</ol>

<h2>The status pill</h2>
<p>While there's any queued or syncing activity, a small coloured pill renders at the top of every page. It's a quick at-a-glance status indicator and the entry point to the per-row review sheet.</p>

<table>
  <thead><tr><th>State</th><th>Looks like</th><th>What it means</th></tr></thead>
  <tbody>
    <tr><td><strong>Online · 0 queued</strong></td><td>Hidden</td><td>Nothing queued, nothing to show. The pill is suppressed entirely.</td></tr>
    <tr><td><span class="callout-warning">Yellow · "N queued"</span></td><td>Static pill</td><td>Rows waiting; online but the drain hasn't started yet (or just finished and there's residue).</td></tr>
    <tr><td><span class="callout-danger">Red · "Offline · N queued"</span></td><td>Static pill</td><td>Browser reports no network. Drain triggers automatically when connectivity returns.</td></tr>
    <tr><td><span class="callout-tip">Green · "Syncing · M pending"</span></td><td>Animated dot</td><td>Drain in progress. Updates live as rows flush.</td></tr>
    <tr><td><span class="callout-danger">Red · "Sync error · N queued"</span></td><td>Static pill</td><td>Last drain attempt failed. Tap the pill, hit <strong>Retry now</strong> after fixing whatever broke (server down, network unstable).</td></tr>
  </tbody>
</table>

<p>Tap the pill to open a sheet listing every pending row — callsign, freq/mode/time, the <code>last_error</code> if any. Each row has a <strong>Delete</strong> button for the case where you want to drop the entry without fixing it (e.g. you re-entered the QSO manually after seeing it failed). A <strong>Retry now</strong> button at the top of the sheet kicks off a fresh drain attempt.</p>

<h2>How sync conflicts get resolved</h2>
<p>"Conflict" in offline-sync usually means: the same QSO gets sent to the server twice. Sync engines that don't handle this end up creating duplicate rows. The dedup rule here:</p>

<ul>
  <li><strong>Primary dedup key:</strong> <code>(user_id, client_uuid)</code>. The server has a unique index on this pair. If a queued row gets POSTed twice (sync retried mid-flight, network flapped during the response, you restored from a backup), the second POST returns the existing row instead of inserting a new one. The client deletes the queued row on either path, so you never end up with two identical entries.</li>
  <li><strong>Server-authoritative on conflict.</strong> If you somehow have a queued QSO that's already on the server (e.g. you logged the same contact on two devices), the server's existing row wins and the queued one drops. The contract is "last-write-wins from the server's perspective" — your local queue is the candidate, the server's row is the truth.</li>
</ul>

<p>Awards portals (POTA, SOTA, LoTW) also dedup their own way — by callsign + datetime + band. Multiple uploads of the same ADIF don't double-credit you. So even in worst-case "I uploaded yesterday's file again by mistake", nothing bad happens.</p>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'The conflict-tolerance test (T24 of M5) verifies this end-to-end — queue 50 QSOs offline, go online, confirm all 50 land server-side with zero duplicates and zero losses. The test lives in tests/JavaScript/offline-queue.spec.js and runs in CI on every PR.',
]) ?>

<h2>How to verify offline mode works on your device</h2>
<p>Sanity check before relying on it during an activation:</p>
<ol>
  <li>Install the app to home screen (<a href="/help/mobile/install-pwa">install instructions</a>) and launch from the icon.</li>
  <li>Visit <code>/qsos/quick</code> once while online so the page is cached.</li>
  <li>Turn on Airplane Mode.</li>
  <li>Log a test QSO. Confirm: the green save flash fires, the row appears in the recents panel with the ⏳ pill, the status pill shows "Offline · 1 queued".</li>
  <li>Turn Airplane Mode off. Within a few seconds the pill changes to "Syncing · 1 pending" and then disappears as the row flushes.</li>
  <li>Refresh the page — the queued ⏳ row is now a normal logged QSO; check the server-side logbook to confirm it arrived.</li>
</ol>

<p>If any step doesn't behave as described, <a href="/help/reference/troubleshooting">troubleshooting</a> has device-specific notes.</p>

<h2>Limits</h2>
<ul>
  <li><strong>Queue depth:</strong> no hard cap, but you'll feel sluggish UI past a few hundred queued rows. In practice, even a full-day POTA activation rarely queues more than 100 QSOs.</li>
  <li><strong>IndexedDB quota:</strong> the browser allocates per-origin storage; a typical phone gets at least ~50 MB. A QSO is ~500 bytes, so the practical ceiling is well over 100,000 rows — you'll run out of operating hours before storage.</li>
  <li><strong>Permanence:</strong> the queue lives in browser storage. Clearing site data or "Forget this site" in the browser settings wipes it. Don't do that with queued rows still pending.</li>
  <li><strong>Cross-device:</strong> a queued row on phone A is invisible to phone B. Both will sync to the server eventually, but until then they're isolated.</li>
</ul>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'For multi-day field events with patchy connectivity, treat the queue as your insurance policy: log everything via quick-add, glance at the status pill once per hour to make sure the queue is draining, and trust the dedup rule to handle any retries.',
]) ?>
