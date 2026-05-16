<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'How templates work — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How system, public, and personal templates work in eQSL Card.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'A template controls the visual layout of an eQSL card — where text sits, which fonts, what background imagery. eQSL Card keeps three families: system, public, and your own.',
]) ?>

<h2>Three families of templates</h2>

<h3>System templates</h3>
<p>Admin-curated and always available to every user. They cover the common cases — a generic contact card, a net check-in card, a special-event format. You can't edit or delete a system template; you can only use it as-is or clone it into a personal template you then modify.</p>

<h3>Public templates</h3>
<p>Community-contributed. Any signed-in user can mark a personal template as "public" via the designer; an admin then reviews and approves it before it appears in the public gallery. Once approved, anyone can clone the template into their own library or render directly from it.</p>

<h3>Personal templates</h3>
<p>Yours. Designed in the <a href="/help/templates/designer">visual designer</a> or cloned from system / public templates. Private to your account unless you submit them to the gallery.</p>

<h2>Browsing the gallery</h2>
<p>Visit <a href="/templates">/templates</a> for the tabbed view: <strong>My templates</strong>, <strong>Public</strong>, and <strong>System</strong>. Each tab shows cards with the template name, a thumbnail (if rendered), and a short description.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/templates/overview/templates-page.webp',
    'alt' => 'The templates listing showing three tabs and a grid of template cards',
    'caption' => 'The templates page with three tabs.',
]) ?>

<h2>Using a template</h2>
<p>Templates are picked at render time, not at QSO-creation time. The same QSO can be rendered against multiple templates — useful if you want different cards for the same contact (one for your records, one to send the operator).</p>

<h2>Cloning a public template</h2>
<p>If a public template is "almost what you want", click <strong>Clone</strong> on its card in the gallery. That creates a new <strong>Personal</strong> template with the same layout — you can then edit it in the designer to change fonts, colours, fields, or background placement without affecting the original. The original creator's authorship is preserved in the template metadata.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/templates/overview/template-card.webp',
    'alt' => 'A template card with Edit and Clone action buttons',
    'caption' => 'Cards show different action buttons depending on which tab they live in.',
]) ?>

<h2>Submitting a personal template to the gallery</h2>
<p>When you're happy with a personal template and think other operators would benefit, tick the "Make this template public" checkbox in the designer's save dialog. It enters the moderation queue and an admin reviews it. Approved templates appear in the public tab; rejected ones come back with a reason. See <a href="/help/templates/submit-public">Submit a template to the gallery</a> for the full submission lifecycle.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'Designing a template that uses the operator\'s callsign, the contact\'s callsign, and the QSO datetime / band / mode covers 90% of card use cases. Keep templates simple — recipients value legibility over visual flourish.',
]) ?>

<h2>What's next</h2>
<p><a href="/help/templates/designer">Design your own template →</a> walks the visual editor; <a href="/help/cards/render">Generate an eQSL card</a> shows how a template gets composited with a QSO at render time.</p>
