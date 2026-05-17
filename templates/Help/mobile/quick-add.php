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

<h2>Sticky save button</h2>
<p>On mobile (&lt; 992 px) the <strong>Log contact</strong> button is sticky-positioned at the bottom of the form. As you scroll through the fields, the button stays visible — you never have to scroll back down to reach it.</p>

<p>When the on-screen keyboard opens, the button stays <em>above</em> the keyboard. Two mechanisms make this work:</p>
<ul>
  <li><strong>Android Chrome / Edge</strong> get a viewport hint (<code>interactive-widget=resizes-content</code>) that tells the browser to shrink the layout viewport when the keyboard opens. Sticky positioning then naturally sits above it.</li>
  <li><strong>iOS Safari</strong> ignores that hint, but a small Visual Viewport API listener writes the keyboard's height as a CSS variable; the sticky button uses that variable as a bottom offset.</li>
</ul>

<p>Worst-case (very old browsers without the Visual Viewport API): the button stays at the bottom of the scrollport and may briefly overlap the keyboard. The form is still functional — tap the field above the button to dismiss the keyboard if needed.</p>

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

<h2>Dupe-check badge</h2>
<p>As you type a callsign (≥ 2 characters), a small coloured pill appears under the input telling you whether you've worked this station before. The badge is the fastest way to spot a duplicate before you log it — especially valuable during nets and contests where the same operator may check in twice by accident.</p>

<table>
  <thead><tr><th>Colour</th><th>Meaning</th><th>What to do</th></tr></thead>
  <tbody>
    <tr><td><span class="callout-note">Grey "First contact"</span></td><td>Never worked this callsign on your log.</td><td>Log normally.</td></tr>
    <tr><td><span class="callout-tip">Blue "Worked Nx · last yesterday"</span></td><td>Worked before, but a different day OR a different band.</td><td>Log normally — different day/band counts as a new QSO for awards.</td></tr>
    <tr><td><span class="callout-warning">Amber "Worked today on this band"</span></td><td>You've already worked this callsign today on the band you're typing into the freq input. Most awards programs would treat this as a dupe.</td><td>Double-check the frequency / your previous log — likely an accidental rebound.</td></tr>
    <tr><td><strong>Red "Duplicate — already worked on this band this activation"</strong></td><td>Confirmed dupe within the current activation: the same operator is already in your logbook tagged with the active activation on this band. Logging again would create a true duplicate that POTA/SOTA would reject.</td><td>Don't log. Move on or change band.</td></tr>
  </tbody>
</table>

<p>The badge auto-detects band from the frequency input. If you haven't typed a freq yet, the per-band signals (amber, red) can't fire — only "first contact" (grey) or "worked before" (blue) will appear.</p>

<p>The check runs asynchronously with a 200ms debounce — you won't see network requests fire on every keystroke. If you keep typing, the in-flight check is cancelled and a fresh one starts after you pause. After a successful save, the badge resets so the next callsign starts from scratch.</p>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'The backing endpoint is GET /api/qsos/dupe-check?callsign=X&band=Y — owner-scoped at the SQL layer, so another operator\'s QSOs never surface in your check. If you build any third-party tooling on top of the API, the response shape is {callsign, total_qsos, last_worked_at, same_band_today, same_band_this_activation}.',
]) ?>

<h2>Block-dupe safety preference (opt-in)</h2>
<p>An opt-in preference on your <a href="/profile">profile page</a> turns the red dupe badge into a hard block on Save. Look for <em>"Block save when a duplicate is detected in the active activation"</em> under "Quick-add safety".</p>

<p>With this on, whenever the dupe-check badge goes red ("Duplicate — already worked on this band this activation"), the <strong>Log contact</strong> button greys out and an inline alert explains why. The check is enforced both at the button (disabled) and at the request layer (the JS handler refuses to POST even if you re-enable the button via DevTools).</p>

<p>Default: <strong>OFF</strong>. Turn ON if:</p>
<ul>
  <li>You're running a POTA / SOTA activation and want zero risk of an accidental re-log that the awards portal would reject.</li>
  <li>You're managing a net and want to enforce one-check-in-per-callsign discipline.</li>
</ul>

<p>Leave OFF if:</p>
<ul>
  <li>You're running a contest where same-band re-contacts are intentional (e.g. multiplier rules).</li>
  <li>You're operating a DXpedition where pile-up callers may re-call by accident and you want every attempt logged.</li>
</ul>

<p>The preference is per-user, server-stored. Applies to every device you log in from — no need to toggle on each phone/tablet/laptop.</p>

<h2>What ships later in M5</h2>
<ul>
  <li><strong>T28</strong> — Haptic feedback on save (<code>navigator.vibrate(30)</code>) for non-visual confirmation during portable ops.</li>
  <li><strong>T29</strong> — Voice input on the callsign field via the Web Speech API (NATO phonetic → letters). Feature-flagged.</li>
</ul>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'On Android Chrome, "Add to Home Screen" the page once and Quick-add launches in standalone (no browser chrome) — a noticeably faster path during fast-rotating nets. The proper PWA manifest with offline support lands in a later M5 phase.',
]) ?>
