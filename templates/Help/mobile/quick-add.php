<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Quick-add for portable ops — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="The /qsos/quick form is built for one-thumb logging during portable activations. Five fields, auto-derived band, stays on the page after save.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'A dedicated entry path for portable ops — POTA, SOTA, IOTA, field days, net check-ins from the field. Designed so you can log a stream of contacts without taking your eyes off the radio for more than a couple of seconds at a time.',
]) ?>

<h2>Where it lives</h2>
<p>The bottom-tab nav on mobile has a centre "Quick add" tab (with a primary-coloured plus icon). It routes to <code>/qsos/quick</code>. On desktop, you can reach the same form by typing the URL directly — but on a laptop the <a href="/qsos/new">full add form</a> is usually a better fit.</p>

<h2>What's in the form</h2>
<p>Five fields, in this order:</p>
<ol>
  <li><strong>Their callsign</strong> — the only required field. Large input, all-caps, the keyboard auto-pops with the cursor here when the page loads.</li>
  <li><strong>Frequency (MHz)</strong> — opens the decimal keypad. Band auto-fills from this on save (see below).</li>
  <li><strong>Mode</strong> — paired right next to the frequency. Select picker, defaults to whatever you used last.</li>
  <li><strong>RST sent / received</strong> — two short inputs, paired side-by-side. Numeric keypad.</li>
  <li><strong>Notes</strong> — single-line. Add an activation reference here (e.g. <code>POTA 9M-0021</code> or <code>SOTA 9M2/PR-001</code>) so you can find the QSO later by searching. The chip row below the input is a tap-to-insert shortcut — see <a href="#notes-chips">Notes shortcuts</a> below.</li>
</ol>

<h2 id="notes-chips">Notes shortcuts (chips)</h2>
<p>Below the notes field is a row of pill-shaped buttons — the "chips". Tap one and the notes field gets pre-filled with the chip's text plus a trailing space, with the cursor right after it so you can immediately type the activation reference:</p>

<ul>
  <li><strong>Net</strong> → <code>Net&nbsp;</code> — then type the net name (e.g. <code>Net MARTS Daily</code>).</li>
  <li><strong>POTA</strong> → <code>POTA&nbsp;</code> — then type the park reference (e.g. <code>POTA K-1234</code>).</li>
  <li><strong>SOTA</strong> → <code>SOTA&nbsp;</code> — then the summit reference (e.g. <code>SOTA 9M2/PR-001</code>).</li>
  <li><strong>Contest</strong> → <code>Contest&nbsp;</code> — then the contest name + exchange info.</li>
  <li><strong>Ragchew</strong> → <code>Ragchew&nbsp;</code> — for casual catches, often followed by a brief topic note.</li>
</ul>

<p>The chip <em>replaces</em> whatever's in the notes field — so don't tap a chip in the middle of typing if you want to keep what you've written. Tap at the start of a new contact.</p>

<h3>Saving your own chips</h3>
<p>Type your custom note into the field (e.g. <code>MARES 9M Net 2m</code>), then tap <strong>+ Save as chip</strong> at the end of the chip row. It joins the default chips as the first option. Your custom chips show an × button to remove them; the defaults (Net / POTA / SOTA / Contest / Ragchew) can't be removed.</p>

<p>Saved chips live in your browser's local storage — they survive page reload and browser restart, but don't sync across devices. If you log from a phone and a laptop, you'll need to save your chips on each. A backend-stored preference might come in a later release if there's demand.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'During a single activation, save the activation reference itself as a chip (e.g. "POTA K-1234 Bukit Larut"). Every check-in becomes a one-tap notes fill — much faster than typing the full reference each time. Remove the chip when the activation ends.',
]) ?>

<h2>What's NOT in the form</h2>
<p>Deliberately stripped:</p>
<ul>
  <li><strong>Date / time</strong> — the server stamps it as "now in UTC" at save. Quick add assumes you're logging contacts as they happen. If you need to backfill an older QSO, use the <a href="/qsos/new">full form</a> instead.</li>
  <li><strong>Band</strong> — auto-derived from frequency via the Malaysian MCMC allocation table. If frequency is blank, band stays blank.</li>
  <li><strong>Transport</strong> — defaults to RF (over the air). Internet transports (Echolink, Mumble, etc.) live on the full form.</li>
  <li><strong>QSO type</strong> — defaults to Contact. Net check-ins (with NCS callsign, net title, organisation) live on the full form.</li>
  <li><strong>Operator name / QTH / grid square</strong> — the callsign auto-complete on the full form covers these via the local directory + RadioID lookup. Quick add skips them to keep the surface small.</li>
</ul>

<p>If you need any of the stripped fields, the form has a link at the top to switch to the full one.</p>

<h2>The save loop</h2>
<p>Tap <strong>Log contact</strong>. The QSO is POSTed via XHR — no page reload — and a green banner confirms (<em>"Logged 9M2RDX."</em>) for a few seconds before fading. The callsign and RST fields clear; <strong>frequency, mode, and notes are deliberately preserved</strong> (you almost always log the next contact on the same freq during portable ops). The cursor jumps back to the callsign input.</p>

<p>If your callsign field is empty when you tap save, you get a red banner telling you so — the request never leaves the browser. If the server rejects the save (e.g. invalid frequency), a red banner shows "Save failed — check fields" and the form state is preserved so you can fix and resubmit.</p>

<p>The recents panel at the top updates in place — your new QSO appears as the first row, the bottom row falls off if there were already five. No page reload anywhere in the loop.</p>

<h2>The recents panel</h2>
<p>Pinned at the top of the page (above the form) is a "Last logged" panel showing your most recent 5 QSOs — callsign, band, mode, time. During a busy net or activation it gives you immediate context for what just happened without having to flip to the logbook.</p>

<p><strong>Tap any row</strong> to reuse its frequency, mode, and notes for the next contact. The callsign and RST stay blank (those are always per-contact). The cursor jumps back into the callsign input, so the next action is "type the new callsign". This is the killer feature for net check-ins on a single frequency — log the first check-in, then for everyone else it's tap-recent → type-callsign → save, three actions per contact.</p>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'The recents panel is reactive — after each save it refreshes with the latest row at the top. Cloning from a row in the middle still works, but the most-recent is almost always the one you want, so the top row is closest to your thumb.',
]) ?>

<h2>What ships later in M5</h2>
<ul>
  <li><strong>T8</strong> — Tappable "Last 5 QSOs" rows that clone band/mode/notes into the form (useful when a net is rotating check-ins on one freq).</li>
  <li><strong>T9</strong> — XHR submit with no page reload; immediate refocus on the callsign input for zero-tap next-contact logging.</li>
  <li><strong>T10</strong> — Notes quick-fill chips (Net / POTA / SOTA / Contest / Ragchew, user-configurable).</li>
  <li><strong>T11</strong> — Sticky full-width submit button anchored above the virtual keyboard.</li>
  <li><strong>T12-T17</strong> — Activations entity: start an activation, get GPS-derived grid square, every QSO auto-tags with the active activation, ADIF export per-activation for POTA/SOTA upload.</li>
  <li><strong>T18-T24</strong> — PWA install + offline queue: log without cell signal, syncs when reconnected.</li>
  <li><strong>T25-T29</strong> — Real-time dupe-check on callsign type.</li>
</ul>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'On Android Chrome, "Add to Home Screen" the page once and Quick-add launches in standalone (no browser chrome) — a noticeably faster path during fast-rotating nets. The proper PWA manifest with offline support lands in a later M5 phase.',
]) ?>
