<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
$extra = $params['class'] ?? '';
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="alert <?= h($extra) ?>" role="alert"><?= $message ?></div>
