<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Submit a template to the gallery — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How to submit a personal eQSL card template for community use and what happens during admin review.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Any personal template can be submitted to the public gallery. An admin reviews it before it becomes visible to all users.',
]) ?>

<h2>Who can submit</h2>
<p>Any signed-in user. The template must be a <strong>personal</strong> template you created or cloned — you can't submit system templates (they're admin-only) or templates made by other users.</p>

<h2>How to submit</h2>
<p>Open the template in the designer (<a href="/templates">/templates</a> → <strong>Edit</strong>). In the save dialog, tick <strong>Make this template public</strong> and save. The template is now in the moderation queue — it stays in your personal library and is still fully usable while it waits.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/templates/submit-public/make-public-checkbox.webp',
    'alt' => 'Save dialog with "Make this template public" checkbox checked',
    'caption' => 'Tick the checkbox in the save dialog to submit for review.',
]) ?>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'Submitting sends an email notification to all admins. The review process depends on the install\'s admin activity — there\'s no SLA. If this is a self-hosted install with a small admin team, reach the admin directly.',
]) ?>

<h2>What happens during review</h2>
<p>The admin sees your template in the moderation queue at <a href="/admin/templates">/admin/templates</a>. They can:</p>
<ul>
  <li><strong>Approve</strong> — the template becomes publicly visible in the gallery's <strong>Public</strong> tab. Other users can render from it or clone it.</li>
  <li><strong>Reject</strong> — the template's <em>is_public</em> flag is cleared. It stays in your personal library. The admin may leave a reason in the notes. You can revise and re-submit.</li>
</ul>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/templates/submit-public/admin-queue.webp',
    'alt' => 'Admin templates page showing a pending submission with Approve and Reject buttons',
    'caption' => 'Admins review submissions in the template moderation queue.',
]) ?>

<h2>After approval</h2>
<p>Your callsign and name appear in the template's gallery card as the author. Users who clone your template start from your exact layout; any changes they make don't affect the original. You can still edit your approved template — edits don't automatically re-trigger review unless you change the public status again.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'The best public templates are simple and legible. Use high-contrast text, large callsign fields, and a minimal background placeholder. Operators will supply their own background — your template should look clean on both light and dark images.',
]) ?>

<h2>Withdrawing a submission</h2>
<p>Open the template in the designer and uncheck <strong>Make this template public</strong> (or save without it checked). The template's public flag clears and it's removed from the gallery immediately — no re-review needed. Cards already rendered from it are not affected.</p>
