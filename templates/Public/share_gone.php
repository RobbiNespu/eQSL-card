<?php
/**
 * Revoked share landing (M2-T14).
 *
 * Rendered with a 410 Gone HTTP status when the operator has revoked the
 * share. The 410 status conveys "this URL is permanently gone" semantics so
 * search engines deindex the page and crawlers stop fetching it.
 *
 * @var \App\View\AppView $this
 * @var string $reason
 */
?>
<div style="max-width: 480px; margin: 0 auto; text-align: center; padding-top: var(--s-7);">
  <h1>Share revoked</h1>
  <p><?= h($reason ?? 'This share is no longer available.') ?></p>
  <div class="d-flex justify-content-center mt-4">
    <a class="btn btn-primary" href="/">Generate your own eQSL</a>
  </div>
</div>
