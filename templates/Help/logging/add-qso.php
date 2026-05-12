<?php $this->extend('/Help/view'); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'The QSO form is the heart of the app. Use it once per contact, or once per net participant if you run a net.',
]) ?>

<h2>Two QSO types</h2>
<p>At the top of the form you pick between two types:</p>
<ul>
  <li><strong>Contact QSO</strong> — a 1:1 exchange between two stations.</li>
  <li><strong>Net check-in</strong> — used when you're the Net Control Station logging a participant's check-in. Adds three extra fields (NCS callsign, net title, organisation) and produces a card stating "<em>(NCS) confirms (participant) checked into (net title)</em>".</li>
</ul>
<p>The form layout changes when you toggle. Switching back hides the net fields but doesn't lose any data you'd already typed — the form remembers them until you save or navigate away.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/logging/add-qso/form-empty.webp',
    'alt' => 'Empty QSO form with Contact QSO selected at the top',
    'caption' => 'The QSO form in Contact mode (default).',
]) ?>

<h2>Required fields</h2>
<ul>
  <li><strong>Their callsign</strong> — case-insensitive; entered in upper-case by convention.</li>
  <li><strong>Date / Time UTC</strong> — always UTC, never your local time.</li>
</ul>
<p>Everything else is optional, but you'll want enough on the card for it to mean anything.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'Always log in UTC. A UTC clock makes this painless; on Linux/macOS, `date -u` prints the current UTC datetime. Getting the date wrong is the most common rookie mistake on transcontinental QSOs.',
]) ?>

<h2>Optional fields</h2>
<ul>
  <li><strong>Frequency (MHz)</strong> — up to 4 decimal places, e.g. <code>14.07415</code>.</li>
  <li><strong>Band</strong> — picked from the standard amateur band list. Auto-derived from frequency if you leave it blank? No — set it explicitly so it appears on the card.</li>
  <li><strong>Mode</strong> — CW, SSB, FM, FT8, etc.</li>
  <li><strong>RST sent / received</strong> — the signal report exchange.</li>
  <li><strong>Their name / QTH / grid square</strong> — appears on the card if the template includes those placeholders.</li>
  <li><strong>Notes</strong> — private; not rendered on the card.</li>
</ul>

<h2>Callsign auto-complete</h2>
<p>If the admin has enabled callsign auto-complete, typing in <strong>Their callsign</strong> looks up name / QTH / grid from any enabled provider (a local CSV directory, RadioID.net, MCMC for Malaysian callsigns, etc.) and prefills the corresponding fields. The result is cached for 90 days so the same callsign on the next QSO returns instantly without another lookup. See <a href="/help/logging/autocomplete">Callsign auto-complete</a> for the details.</p>

<h2>Transport — RF or internet</h2>
<p>The <strong>Transport</strong> dropdown defaults to <strong>RF (over the air)</strong>. If the contact was via Echolink, Zello, Mumble, TeamSpeak, or Discord, pick the right option — frequency and band become optional, and a free-text "Channel / node / server" field appears for things like "Echolink node 12345" or "Mumble: hamradio.example.com". The transport pill shows on the QSO row in the logbook so you can tell RF QSOs from internet-mediated ones at a glance.</p>

<h2>What happens after save</h2>
<p>The QSO lands in your <a href="/qsos">logbook</a>. From there you can render it as an eQSL card individually or in bulk — see <a href="/help/cards/render">Generate an eQSL card</a>.</p>
