<h1><?= $mode === 'edit' ? 'Edit QSO' : 'Add QSO' ?></h1>

<?= $this->Form->create($qso) ?>
<div class="row g-3">
  <div class="col-md-6">
    <?= $this->Form->control('call_worked', [
        'label' => 'Their callsign',
        'class' => 'form-control',
        'required' => true,
    ]) ?>
  </div>
  <div class="col-md-6">
    <?= $this->Form->control('qso_datetime_utc', [
        'type' => 'datetime-local',
        'label' => 'Date/Time UTC',
        'class' => 'form-control',
        'required' => true,
    ]) ?>
  </div>
  <div class="col-md-4">
    <?= $this->Form->control('frequency_mhz', [
        'label' => 'Frequency (MHz)',
        'class' => 'form-control',
    ]) ?>
  </div>
  <div class="col-md-4">
    <?= $this->Form->control('band', [
        'label' => 'Band',
        'type' => 'select',
        'class' => 'form-select',
        'options' => \App\Service\HamRadio::bandOptions($qso->band ?? null),
        'empty' => '— pick a band —',
    ]) ?>
  </div>
  <div class="col-md-4">
    <?= $this->Form->control('mode', [
        'label' => 'Mode',
        'type' => 'select',
        'class' => 'form-select',
        'options' => \App\Service\HamRadio::modeOptions($qso->mode ?? null),
        'empty' => '— pick a mode —',
    ]) ?>
  </div>
  <div class="col-md-3">
    <?= $this->Form->control('rst_sent', [
        'label' => 'RST sent',
        'class' => 'form-control',
    ]) ?>
  </div>
  <div class="col-md-3">
    <?= $this->Form->control('rst_received', [
        'label' => 'RST received',
        'class' => 'form-control',
    ]) ?>
  </div>
  <div class="col-md-6">
    <?= $this->Form->control('operator_name', [
        'label' => 'Their name',
        'class' => 'form-control',
    ]) ?>
  </div>
  <div class="col-md-6">
    <?= $this->Form->control('operator_qth', [
        'label' => 'QTH',
        'class' => 'form-control',
    ]) ?>
  </div>
  <div class="col-md-6">
    <?= $this->Form->control('grid_square', [
        'label' => 'Grid square',
        'class' => 'form-control',
    ]) ?>
  </div>
  <div class="col-md-12">
    <?= $this->Form->control('notes', [
        'type' => 'textarea',
        'rows' => 3,
        'label' => 'Notes',
        'class' => 'form-control',
    ]) ?>
  </div>
</div>
<button class="btn btn-primary mt-3"><?= $mode === 'edit' ? 'Save changes' : 'Add QSO' ?></button>
<a class="btn btn-link" href="/qsos">Cancel</a>
<?= $this->Form->end() ?>
