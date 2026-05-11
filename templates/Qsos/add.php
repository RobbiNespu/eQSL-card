<h1><?= $mode === 'edit' ? 'Edit QSO' : 'Add QSO' ?></h1>

<?php
// Alpine state for the contact/net toggle. Initial value comes from the
// entity (so editing a net QSO loads in net mode) and falls back to
// 'contact' for new rows. ncsPrefill defaults to the user's own callsign —
// most operators are running the nets they log; they can edit if they're
// scribing someone else's net.
$initialType = $qso->qso_type ?? 'contact';
$ncsCurrent = $qso->ncs_callsign ?? '';
$ncsPrefill = $ncsCurrent !== '' ? $ncsCurrent : ($operatorCallsign ?? '');
?>
<div x-data="{
    qsoType: <?= json_encode($initialType) ?>,
    ncsCallsign: <?= json_encode($ncsCurrent) ?>,
    netTitle: <?= json_encode($qso->net_title ?? '') ?>,
    netOrg: <?= json_encode($qso->net_organisation ?? '') ?>,
    isNet() { return this.qsoType === 'net'; },
    onSwitchNet() {
      // Prefill NCS with the user's callsign on first toggle so the
      // operator doesn't type the same thing every time.
      if (!this.ncsCallsign) this.ncsCallsign = <?= json_encode($ncsPrefill) ?>;
    },
}">
<?= $this->Form->create($qso) ?>

<div class="mb-3">
  <label class="form-label fw-bold">QSO type</label>
  <div class="btn-group" role="group">
    <input type="radio" class="btn-check" id="qsoType-contact" name="qso_type" value="contact"
           x-model="qsoType">
    <label class="btn btn-outline-primary" for="qsoType-contact">Contact QSO</label>

    <input type="radio" class="btn-check" id="qsoType-net" name="qso_type" value="net"
           x-model="qsoType" @change="onSwitchNet()">
    <label class="btn btn-outline-primary" for="qsoType-net">Net check-in</label>
  </div>
  <p class="form-text small">
    <span x-show="!isNet()">1:1 contact between two stations.</span>
    <span x-show="isNet()" x-cloak>Card issued by the NCS to a net participant. Fill in the net details below.</span>
  </p>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label" x-text="isNet() ? 'Participant callsign' : 'Their callsign'">Their callsign</label>
    <input type="text" name="call_worked" class="form-control"
           value="<?= h($qso->call_worked ?? '') ?>" required>
  </div>
  <div class="col-md-6">
    <?= $this->Form->control('qso_datetime_utc', [
        'type' => 'datetime-local',
        'label' => 'Date/Time UTC',
        'class' => 'form-control',
        'required' => true,
    ]) ?>
  </div>

  <!-- Net details: visible only in net mode. Inputs stay in the DOM so the
       form always submits the names; values default to "" for contact rows. -->
  <div class="col-12" x-show="isNet()" x-cloak>
    <fieldset class="border rounded p-3 bg-light-subtle">
      <legend class="float-none w-auto small fw-bold px-2">Net details</legend>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">NCS callsign <span class="text-danger">*</span></label>
          <input type="text" name="ncs_callsign" class="form-control"
                 x-model="ncsCallsign" :required="isNet()">
        </div>
        <div class="col-md-4">
          <label class="form-label">Net title <span class="text-danger">*</span></label>
          <input type="text" name="net_title" class="form-control"
                 x-model="netTitle" :required="isNet()"
                 placeholder="e.g. PARTY 9M2 Daily Net">
        </div>
        <div class="col-md-4">
          <label class="form-label">Organisation</label>
          <input type="text" name="net_organisation" class="form-control"
                 x-model="netOrg" placeholder="e.g. MARTS">
        </div>
      </div>
    </fieldset>
  </div>

  <!-- Hidden inputs so contact-mode submits empty net fields explicitly.
       Without these, switching to net then back to contact would still
       POST the previously-typed net values. -->
  <template x-if="!isNet()">
    <div>
      <input type="hidden" name="ncs_callsign" value="">
      <input type="hidden" name="net_title" value="">
      <input type="hidden" name="net_organisation" value="">
    </div>
  </template>

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
</div>
