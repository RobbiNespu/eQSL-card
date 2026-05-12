<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Your first eQSL card — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="5-minute quick start: from sign-up to generated, downloadable eQSL card.">
<?php $this->end(); ?>
<?php $this->set('useMermaid', true); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'A 5-minute walkthrough: from a brand-new account to a downloadable eQSL card for your first logged contact.',
]) ?>

<h2>The flow at a glance</h2>

<pre class="mermaid">
flowchart LR
  A[Sign up] --> B[Log a QSO]
  B --> C[Render the card]
  C --> D[Download or share]
</pre>

<p>Four steps, three forms, one card. The rest of this guide walks each step in detail.</p>

<h2>Step 1: Log a QSO</h2>
<p>From your dashboard, click <strong>+ New QSO</strong> or visit <a href="/qsos/new">/qsos/new</a>. The form distinguishes between a regular <strong>Contact QSO</strong> (a 1:1 exchange) and a <strong>Net check-in</strong> (where you, as Net Control Station, are logging a participant). Pick the right type at the top.</p>

<p>For a first card, fill in at minimum: their callsign, the date/time in UTC, and any signal report info you exchanged. Frequency, band, and mode are optional but recommended — they appear on the generated card.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/getting-started/first-card/qso-form.webp',
    'alt' => 'The QSO form filled in with a contact callsign, UTC datetime, frequency, and mode',
    'caption' => 'Step 1 — log the contact.',
]) ?>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'Always log times in UTC, not your local time. A UTC clock or a watch with a dual time zone makes this painless. If you log in local time the date may be off by a day for transcontinental QSOs.',
]) ?>

<p>For a deeper dive, see <a href="/help/logging/add-qso">Log a contact</a>.</p>

<h2>Step 2: Render the card</h2>
<p>From your <a href="/qsos">logbook</a>, click <strong>Render</strong> next to any QSO. You'll be asked to pick a template (or accept the default), choose a background image (site default, a previously uploaded image, or a fresh upload), then click <strong>Generate</strong>.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/getting-started/first-card/render-form.webp',
    'alt' => 'The render form showing template radio cards and background picker',
    'caption' => 'Step 2 — pick a template and background, then generate.',
]) ?>

<p>The server composites your QSO data onto the template at the resolution defined by that template (typically 1500×1000 px). Generation typically takes under a second.</p>

<h2>Step 3: Download or share</h2>
<p>The generated card lands in your <a href="/cards">card library</a>. Click any card to view it, then choose:</p>
<ul>
  <li><strong>Download image</strong> — saves the .webp directly.</li>
  <li><strong>Download PDF</strong> — same card wrapped in a PDF for printing.</li>
  <li><strong>Share publicly</strong> — generates a /qsl/{slug} link anyone can view.</li>
</ul>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/getting-started/first-card/generated-card.webp',
    'alt' => 'The generated card with download and share buttons',
    'caption' => 'Step 3 — download, share, or both.',
]) ?>

<p>For the full sharing story including password protection and revoking links, see <a href="/help/cards/share">Share a card publicly</a>.</p>

<h2>What's next</h2>
<p>Now that you've shipped one card, the rest of the docs covers the depth: <a href="/help/logging/import">bulk-importing</a> an existing log, <a href="/help/templates/overview">designing your own template</a>, <a href="/help/cards/bulk-render">bulk-rendering</a> hundreds of cards at once.</p>
