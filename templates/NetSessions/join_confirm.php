<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\NetSession $session
 * @var string $token
 * @var string $title
 */
$this->assign('title', $title);
?>
<div class="container py-4">
  <h1>Join as a co-logger</h1>
  <p>
    You've been invited to log check-ins for
    <strong><?= h($session->net_title) ?></strong>
    <?php if ($session->net_organisation): ?>
      (<?= h($session->net_organisation) ?>)
    <?php endif; ?>.
    Confirm below to accept.
  </p>
  <?= $this->Form->create(null, ['url' => '/net-sessions/join/' . h($token), 'class' => 'mt-3']) ?>
    <button class="btn btn-primary">Join as logger</button>
    <a class="btn btn-link" href="/dashboard">Cancel</a>
  <?= $this->Form->end() ?>
</div>
