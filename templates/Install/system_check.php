<h1>Step 1 — System checks</h1>
<table class="table">
<?php foreach ($report as $name => $row): ?>
    <tr>
        <td><?= h($name) ?></td>
        <td><?= $row['ok'] ? '✅' : '❌' ?></td>
        <td><?= h($row['detail']) ?></td>
    </tr>
<?php endforeach; ?>
</table>
<?php if ($allPass): ?>
    <a href="/install/database" class="btn btn-primary">Next: Database</a>
<?php else: ?>
    <p class="text-danger">Resolve the failing checks and reload this page.</p>
<?php endif; ?>
