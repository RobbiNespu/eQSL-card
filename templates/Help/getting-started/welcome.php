<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Welcome to eQSL Card — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="What eQSL Card is, who it's for, and what you can do with it.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'A self-hosted workbench for amateur radio operators who want to generate, share, and archive electronic QSL cards.',
]) ?>

<h2>What is an eQSL?</h2>
<p>An <strong>eQSL card</strong> is the digital counterpart of the classic paper QSL — a confirmation that you and another station spoke on the air. Instead of mailing a printed card, you generate an image (and optionally a PDF), and send it via email, chat, or a shareable link.</p>

<h2>Who it's for</h2>
<p>If you log QSOs, run nets, or operate special-event stations, eQSL Card gives you a free way to confirm contacts digitally. Net Control Stations can issue check-in cards to participants in seconds. DXpeditioners can ship cards to a hundred contacts before they pack up.</p>

<h2>What you can do</h2>
<ul>
  <li><strong>Log QSOs</strong> — manual entry or bulk import from ADIF / CSV.</li>
  <li><strong>Design templates</strong> — visual editor with text, fonts, images, and Q-prefix placeholders.</li>
  <li><strong>Generate cards</strong> — one card, or hundreds in bulk, from your log.</li>
  <li><strong>Share publicly</strong> — optional password protection, revocable share links.</li>
  <li><strong>Run admin tools</strong> — user management, storage cleanup, callsign directory imports.</li>
</ul>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/getting-started/welcome/sample-card.webp',
    'alt' => 'Sample eQSL card showing a QSO confirmation between two stations',
    'caption' => 'A generated eQSL card — your callsign and theirs, the QSO details, on the background of your choice.',
]) ?>

<h2>Next up</h2>
<p><a href="/help/getting-started/create-account">Create an account →</a> to start logging contacts.</p>
