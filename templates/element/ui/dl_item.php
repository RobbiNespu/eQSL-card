<?php
/**
 * One row of a <dl class="row dl-stack"> — term + value with em-dash
 * fallback when the value is empty.
 *
 * @var \App\View\AppView $this
 * @var string $term
 * @var string|null $value
 * @var bool $escape_value default true
 */
$escape = $escape_value ?? true;
$value  = $value ?? '';
$hasValue = $value !== '' && $value !== null;
?>
<dt class="col-sm-3"><?= h($term) ?></dt>
<dd class="col-sm-9"><?php if ($hasValue): ?><?= $escape ? h($value) : $value ?><?php else: ?><span class="text-muted">—</span><?php endif; ?></dd>
