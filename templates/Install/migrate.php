<h1>Step 3 — Apply schema</h1>
<p class="text-muted">Click the button to apply all pending database migrations.</p>
<?= $this->Form->create(null, ['url' => '/install/migrate']) ?>
<button type="submit" class="btn btn-primary">Run migrations</button>
<?= $this->Form->end() ?>
