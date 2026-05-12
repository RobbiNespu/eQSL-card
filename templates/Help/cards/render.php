<?php $this->extend('/Help/view'); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Pick a template, attach a background, and the server composites your QSO data into a downloadable eQSL card in under a second.',
]) ?>

<h2>Where rendering starts</h2>
<p>From your <a href="/qsos">logbook</a>, find the QSO you want a card for and click <strong>Render</strong> in the row's action column. (If a card has already been generated for that QSO, you'll see <strong>View card</strong> instead — delete the existing card from your library first if you want to re-render.)</p>

<h2>Pick a template</h2>
<p>Templates control the visual layout: where the callsigns sit, which fonts are used, what background imagery, what credit line. You'll see three families:</p>
<ul>
  <li><strong>System</strong> — admin-curated, always available, never edited or deleted.</li>
  <li><strong>Public</strong> — community-contributed and admin-approved. Clone one if you'd like to tweak it for your station.</li>
  <li><strong>Personal</strong> — your own templates. Designed in the <a href="/help/templates/designer">visual designer</a>.</li>
</ul>
<p>Click any template card to select it. The first system template is pre-selected, so a fresh render with no preference still produces a sensible card.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/cards/render/template-picker.webp',
    'alt' => 'Template radio cards showing system, public, and personal templates',
    'caption' => 'Step 1 — pick a template.',
]) ?>

<h2>Pick a background</h2>
<p>The background is the underlying image (your shack, an antenna landscape, a sunset over the harbour). Three options:</p>
<ul>
  <li><strong>Site default</strong> — useful for quick renders; uses the admin-configured fallback image.</li>
  <li><strong>A previous upload</strong> — pick one of the images already in your library. Reuse encourages a consistent look.</li>
  <li><strong>Upload a new image</strong> — drop a JPG, PNG, or WebP. Server auto-resizes to fit the template's bounding box (typically 2000×1500 px). Attribution fields (author + license) appear if you upload here, and they're stored alongside the image for the credit line.</li>
</ul>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/cards/render/background-picker.webp',
    'alt' => 'Background picker showing a default option, three previous uploads, and an upload-new toggle',
    'caption' => 'Step 2 — pick a background.',
]) ?>

<h2>Generate</h2>
<p>Click <strong>Generate</strong>. The server composites your QSO data onto the template (PHP-GD for image, FPDF for the optional PDF wrapper), writes the result to <code>webroot/files/cards/</code>, and lands you on the card's view page where you can download or share it.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/cards/render/generated.webp',
    'alt' => 'The freshly-rendered card with Download image, Download PDF, and Share buttons',
    'caption' => 'Step 3 — the card is yours.',
]) ?>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'Generated cards are immutable. If you edit the QSO data after rendering, you need to delete the old card and re-render to get the update. This is intentional — cards are historical artefacts.',
]) ?>

<p>For 50+ cards at once, see <a href="/help/cards/bulk-render">Bulk-render many cards</a>.</p>
