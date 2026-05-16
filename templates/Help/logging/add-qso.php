<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Log a contact — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="Step-by-step guide to logging a contact or net check-in in eQSL Card.">
<?php $this->end(); ?>

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

<h2>On mobile</h2>
<p>The form is tuned for one-handed use on phones (M5, 2026-05-16):</p>
<ul>
  <li>The whole layout collapses to a <strong>single column</strong> below 992 px so you scroll top-to-bottom without horizontal swiping.</li>
  <li><strong>RST sent</strong> and <strong>RST received</strong> stay paired side-by-side even on the narrowest screens — the values are short enough to fit, and you'll usually type them together.</li>
  <li>The <strong>Frequency (MHz)</strong> input opens the decimal keypad (not the full alphanumeric keyboard) via <code>inputmode="decimal"</code>. The RST inputs open the numeric keypad. Both save dozens of taps over a typical session.</li>
  <li>The primary <strong>Add QSO</strong> / <strong>Save changes</strong> button is full-width at the bottom of the form so it's reachable with your thumb without aiming. Cancel collapses to a text link below it.</li>
  <li>For activations and portable ops where you need to log a stream of contacts faster, a dedicated <strong>Quick add</strong> route ships in a later phase of M5 — see the <a href="/help/mobile/navigation">mobile navigation guide</a> for context. Today the Quick add tab in the bottom nav routes here.</li>
</ul>

<p>If you find anything still cramped or unreachable at 375 px, that's a bug worth reporting — the M5 audit aimed to catch every blocker, but real devices surface things desktop emulators miss.</p>
