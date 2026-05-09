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
<h1>Share revoked</h1>
<p class="text-muted"><?= h($reason ?? 'This share is no longer available.') ?></p>
<p><a href="/" class="btn btn-link">Generate your own eQSL</a></p>
