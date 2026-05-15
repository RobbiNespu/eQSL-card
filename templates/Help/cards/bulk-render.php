<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Bulk-render many cards — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How to render eQSL cards for multiple QSOs at once using eQSL Card's bulk-render modal.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Select any number of QSOs from your logbook, pick a template and background, and the app renders all the cards in the background — five at a time, so it works on any shared host.',
]) ?>

<h2>When to use bulk render</h2>
<p>Use bulk render when you need cards for a batch of QSOs at once: after a contest weekend, after a net session where you logged 30 check-ins, or after importing a large ADIF log. Single-QSO rendering from the row's <strong>Render</strong> button is still available — bulk render is just a faster path for many cards with the same template and background.</p>

<h2>Selecting QSOs</h2>
<p>Go to your <a href="/qsos">logbook</a>. Check the rows you want cards for using the row checkboxes, or tick the header checkbox to select every QSO on the current page. The selection count updates in the sticky bar at the bottom of the table.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/cards/bulk-render/selection.webp',
    'alt' => 'Logbook table with six rows checked and a sticky bar showing "6 QSOs selected"',
    'caption' => 'Tick the rows you want, then open the Bulk render modal.',
]) ?>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'QSOs that already have a rendered card are automatically skipped — bulk render never duplicates an existing card. The progress summary tells you how many were skipped at the end.',
]) ?>

<h2>The bulk render modal</h2>
<p>Click <strong>Bulk render</strong> in the sticky bar. The modal asks for two things:</p>
<ul>
  <li><strong>Template</strong> — pick from your personal templates, public-approved templates, or system templates. The same template is applied to every card in the batch.</li>
  <li><strong>Background</strong> — choose the site default, one of your existing uploaded backgrounds, or upload a new image. The same background is used for every card in the batch.</li>
</ul>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/cards/bulk-render/modal.webp',
    'alt' => 'The bulk render modal with template and background selectors',
    'caption' => 'One template, one background — applied across all selected QSOs.',
]) ?>

<h2>How rendering works</h2>
<p>The app renders five cards per server request to stay within shared-hosting PHP time limits. You'll see a progress bar counting up as each chunk completes. On a typical shared host, 50 cards takes roughly 30–60 seconds. The page stays open during rendering — don't navigate away.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/cards/bulk-render/progress.webp',
    'alt' => 'Progress bar at 40%, showing "12 of 30 cards rendered"',
    'caption' => 'The progress bar updates every five cards.',
]) ?>

<h2>After rendering</h2>
<p>When the progress bar hits 100%, a summary appears: how many cards were rendered, how many were skipped (already had cards). Click <strong>Go to my library</strong> to see all your cards, or close the modal to stay in the logbook.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'If you need different templates or backgrounds for different QSOs — say a contact card for DX contacts and a net template for check-ins — run bulk render twice: filter the logbook first (use the mode or type filter), select the relevant rows, render, then repeat for the other group.',
]) ?>

<h2>Next up</h2>
<p><a href="/help/cards/share">Share a card publicly</a> — once cards are rendered, send recipients a link they can open without an account.</p>
