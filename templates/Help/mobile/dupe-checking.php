<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Dupe-check traffic-light badge — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="The four-state callsign-dupe traffic-light badge under the quick-add input, the underlying /api/qsos/dupe-check endpoint, the rules that decide each colour, and the opt-in preference that turns the red state into a hard save block.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'As you type a callsign on the quick-add form, a small coloured pill appears under the input telling you whether you\'ve worked this station before — and if so, how recently and on which band. Designed to catch accidental re-logs during nets and activations before they reach the server.',
]) ?>

<h2>Where the badge appears</h2>
<p>On the <a href="/help/mobile/quick-add">quick-add form</a> (<code>/qsos/quick</code>), right below the callsign input. It activates once you've typed at least 2 characters. As you continue typing the badge state updates with a 200 ms debounce — you won't see network requests fire on every keystroke.</p>

<p>If you haven't typed a frequency yet, only the first two badge states (grey / blue) can fire — the per-band signals (amber, red) need a band derived from the frequency input to compute.</p>

<h2>The four states</h2>

<table>
  <thead>
    <tr>
      <th>Colour</th>
      <th>Label</th>
      <th>Rule</th>
      <th>What it usually means</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><span class="callout-note">Grey</span></td>
      <td><strong>First contact</strong></td>
      <td>Zero rows in your logbook with this callsign.</td>
      <td>A fresh contact. Log it normally.</td>
    </tr>
    <tr>
      <td><span class="callout-tip">Blue</span></td>
      <td><strong>Worked N× · last YYYY-MM-DD</strong></td>
      <td>One or more prior QSOs with this callsign, none of them on the same band today.</td>
      <td>You've worked this operator before, but on a different day or different band. Awards programs treat different-day or different-band as a fresh QSO. Log normally.</td>
    </tr>
    <tr>
      <td><span class="callout-warning">Amber</span></td>
      <td><strong>Worked today on this band</strong></td>
      <td>A prior QSO with this callsign today, on the same band you're about to log on. No active activation involved.</td>
      <td>Probably a duplicate. Double-check the frequency and your earlier log — most awards programs would reject the second contact. Log only if you're sure (e.g. a different mode on the same band may still count, depending on the rule set).</td>
    </tr>
    <tr>
      <td><strong>Red · Duplicate — already worked on this band this activation</strong></td>
      <td>(see label)</td>
      <td>A prior QSO with this callsign tagged with the <em>active</em> activation, on the same band.</td>
      <td>A confirmed dupe within the current portable session. POTA / SOTA / IOTA / contest portals would reject the second contact. Don't log; change band or move on.</td>
    </tr>
  </tbody>
</table>

<p>The badge always tells you the <em>total</em> number of times you've worked the callsign (e.g. "Worked 4× · last 2026-04-12") so you have the context — even when the colour is grey you've got the recency info.</p>

<h2>The decision rule</h2>
<p>The server picks the badge state with a strict priority:</p>
<ol>
  <li>If there's <code>same_band_this_activation</code> → red.</li>
  <li>Else if there's <code>same_band_today</code> → amber.</li>
  <li>Else if there's <em>any</em> prior contact → blue.</li>
  <li>Else → grey.</li>
</ol>

<p>This is deliberately conservative: the badge never says "OK" when there's a stricter rule that fires. If you see green-then-yellow-then-red over the course of a session, the badge is telling the truth — it didn't relax across states, your situation got more dupe-prone.</p>

<h2>The API endpoint</h2>
<p>If you're building third-party tooling on top of your logbook, the badge is powered by a documented JSON endpoint:</p>

<pre><code>GET /api/qsos/dupe-check?callsign=9M2RDX&band=40m</code></pre>

<p>Response shape:</p>

<pre><code>{
  "callsign": "9M2RDX",
  "total_qsos": 4,
  "last_worked_at": "2026-04-12T09:34:00Z",
  "same_band_today": false,
  "same_band_this_activation": false
}</code></pre>

<ul>
  <li><strong>Owner-scoped</strong> at the SQL layer — another operator's QSOs never surface in your check.</li>
  <li><strong>Authentication required</strong> — the same session cookie as the rest of the app.</li>
  <li><strong>200 ms debounced</strong> at the client. If you call it yourself, no debounce; the endpoint is cheap (single indexed query).</li>
  <li><strong>Band parameter</strong> is optional — without it, <code>same_band_today</code> and <code>same_band_this_activation</code> always return <code>false</code> (can't be computed without a band).</li>
  <li>If a quick-add save fires while a dupe-check request is in flight, the result is discarded. After a save the badge resets so the next callsign starts from scratch.</li>
</ul>

<h2>Block-dupe safety preference (opt-in)</h2>
<p>An opt-in preference on your <a href="/profile">profile page</a> turns the red dupe badge into a hard block on Save. Look for <em>"Block save when a duplicate is detected in the active activation"</em> under "Quick-add safety".</p>

<p>With this on, whenever the dupe-check badge goes red ("Duplicate — already worked on this band this activation"), the <strong>Log contact</strong> button greys out and an inline alert explains why. The block is enforced at two layers:</p>
<ul>
  <li><strong>Button layer</strong> — the Save button has <code>disabled</code> set and shows a grey style.</li>
  <li><strong>Submit handler</strong> — even if you re-enable the button via DevTools, the JS handler refuses to POST and shows the alert.</li>
</ul>

<p>Default: <strong>OFF</strong>. The amber and grey/blue states are <em>never</em> blocked — only the red state, and only when the preference is on.</p>

<h3>When to turn it ON</h3>
<ul>
  <li><strong>POTA / SOTA activations</strong> where the awards portal will reject duplicates and you want zero risk of an accidental re-log polluting your submitted ADIF.</li>
  <li><strong>Net control</strong> where you want to enforce one-check-in-per-callsign discipline.</li>
  <li><strong>Award-chasing</strong> sessions where you're trying to hit specific multipliers and a re-contact would waste the slot.</li>
</ul>

<h3>When to leave it OFF</h3>
<ul>
  <li><strong>Contests</strong> where the rule set explicitly allows same-band re-contacts (mode multipliers, time-window multipliers, etc.).</li>
  <li><strong>DXpeditions</strong> where pile-up callers re-call by accident and you want every attempt recorded.</li>
  <li><strong>Casual operating</strong> from home — the dupe-check value is dominated by awards/contest use cases.</li>
</ul>

<p>The preference is per-user, server-stored. Applies to every device you log in from — no need to toggle on each phone/tablet/laptop. The check fires identically online and offline; the offline-queue path also respects the block (a red state will refuse to queue, not just refuse to send).</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'During an activation, the most useful pattern is: amber catches the "I forgot we already worked them" mistake, red catches the "I logged this 10 seconds ago and tapped Save twice" mistake. The block-dupe preference is the seatbelt for the second one — turn it on for an awards session and forget it\'s there.',
]) ?>

<h2>Limits and corner cases</h2>
<ul>
  <li><strong>The dupe-check considers ALL of your QSOs, not just the active activation.</strong> Blue can fire for a contact you worked years ago. This is correct — you want the recency context even on a fresh activation.</li>
  <li><strong>"Today" is calendar UTC, not local time.</strong> A QSO at 23:50 local and one at 00:10 local on the same evening may register as different days if you're in a UTC-offset timezone. The amber state will <em>not</em> fire for the second one. This matches LoTW / POTA day-boundary semantics — UTC is the QSO timestamp.</li>
  <li><strong>Mode is NOT part of the dupe rule.</strong> SSB → CW on the same band same day still counts as a dupe for the badge. Most awards programs do treat mode-changes as new QSOs, so this is conservative — you'll see amber where the contest rules might say OK. Override your own judgement here if you know the rule allows it.</li>
  <li><strong>If you delete an old QSO, the dupe-check sees it gone.</strong> The endpoint reads the current state of the logbook, not a snapshot. Soft-deleted QSOs are excluded.</li>
</ul>
