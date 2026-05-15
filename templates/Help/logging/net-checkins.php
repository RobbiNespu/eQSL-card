<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Logging net check-ins — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How to use eQSL Card's net check-in mode to log participants and generate confirmation cards as Net Control Station.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'When you\'re running Net Control, eQSL Card lets you log each participant as a net check-in and generate a confirmation card they can download or share.',
]) ?>

<h2>What a net check-in QSO is</h2>
<p>A net check-in is a special QSO type that models the NCS → participant relationship. Instead of a 1:1 contact between two stations, the card reads:</p>
<blockquote><em>(NCS callsign) confirms (participant callsign) checked into (net title) on (date / time UTC).</em></blockquote>
<p>Three extra fields appear when you select this mode: NCS callsign, net title, and organisation. All three print on the card if the active template includes those placeholders.</p>

<h2>Switching to net check-in mode</h2>
<p>Open the QSO form (<a href="/qsos/add">/qsos/add</a>). At the top, toggle from <strong>Contact QSO</strong> to <strong>Net check-in</strong>. The form immediately shows the three net-specific fields. Switching back hides them without losing anything you've already typed.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/logging/net-checkins/form-net-mode.webp',
    'alt' => 'QSO form with Net check-in selected, showing NCS callsign, net title, and organisation fields',
    'caption' => 'Net check-in mode adds three extra fields to the form.',
]) ?>

<h2>Filling in the net fields</h2>
<ul>
  <li><strong>Their callsign</strong> — the <em>participant's</em> callsign, not the NCS. This is whose card it becomes.</li>
  <li><strong>NCS callsign</strong> — your callsign as Net Control. Defaults to your logged-in callsign but can be overridden (e.g. if you run NCS under a club callsign).</li>
  <li><strong>Net title</strong> — the name of the net, e.g. <em>9W Morning Chat</em>. This prints on the card.</li>
  <li><strong>Organisation</strong> — optional. Club or ARES/RACES group, e.g. <em>MARTS</em>.</li>
  <li><strong>Date / Time UTC</strong> — the check-in time, always UTC.</li>
</ul>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'If your net runs at the same time every week, enter one check-in manually and then use the logbook\'s duplicate-QSO shortcut to clone it for the next session — only the date and participant callsign need changing.',
]) ?>

<h2>Logging multiple check-ins</h2>
<p>Submit the form and it resets, keeping the net-specific fields pre-filled (NCS callsign, net title, organisation) so you only need to change <em>their callsign</em> and <em>date/time</em> for each participant. Work your way down the roll-call list without re-entering common fields.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/logging/net-checkins/logbook-net-rows.webp',
    'alt' => 'Logbook table showing several net check-in rows with the net-title badge visible',
    'caption' => 'Net check-in rows appear in the logbook with a Net badge.',
]) ?>

<h2>Rendering the confirmation card</h2>
<p>Once logged, net check-in QSOs appear in the logbook like any other entry. Click <strong>Render</strong> on any row to generate the confirmation card. Pick a template that includes the net placeholders — the <em>Net template</em> system template is designed specifically for this. See <a href="/help/cards/render">Generate an eQSL card</a> for the full render flow.</p>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'Participants don\'t need an account to receive their card. Share the public link with them — they can view and download the card without logging in. See <a href="/help/cards/share">Share a card publicly</a>.',
]) ?>

<h2>Next up</h2>
<p><a href="/help/logging/autocomplete">Callsign auto-complete</a> explains how to speed up check-in entry by pulling participant name and QTH automatically.</p>
