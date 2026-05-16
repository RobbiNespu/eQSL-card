<div x-data="cameraForm()">
<h1>Generate an eQSL</h1>
<p>Fill in the QSO, attach a background, and download. No account needed — sign up later to keep your card library.</p>

<?= $this->Form->create(null, ['url' => '/generate', 'type' => 'file']) ?>

<!-- QSO type toggle. Mirrors the logged-in /qsos/new form so guests can
     produce net check-in cards too. -->
<div class="field" style="margin-bottom: var(--s-5);">
  <label class="form-label">QSO type</label>
  <div class="btn-group" role="group" aria-label="QSO type">
    <input type="radio" class="btn-check" id="qsoType-contact" name="qso_type" value="contact"
           x-model="qsoType">
    <label class="btn btn-outline-primary" for="qsoType-contact">Contact QSO</label>

    <input type="radio" class="btn-check" id="qsoType-net" name="qso_type" value="net"
           x-model="qsoType">
    <label class="btn btn-outline-primary" for="qsoType-net">Net check-in</label>
  </div>
  <p class="form-text">
    <span x-show="!isNet()">A 1:1 contact between two stations.</span>
    <span x-show="isNet()" x-cloak>The NCS issues the card to a net participant. Fill in the net details below.</span>
  </p>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="callsign">
        <span x-text="isNet() ? 'Participant callsign' : 'Their callsign'">Their callsign</span>
        <span class="req">*</span>
      </label>
      <?= $this->Form->control('callsign', [
          'class' => 'form-control', 'label' => false, 'id' => 'callsign',
          'required' => true, 'autocapitalize' => 'characters', 'autocomplete' => 'off',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="operator_callsign">My callsign <span class="req">*</span></label>
      <?= $this->Form->control('operator_callsign', [
          'class' => 'form-control', 'label' => false, 'id' => 'operator_callsign',
          'required' => true, 'autocapitalize' => 'characters', 'autocomplete' => 'off',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="qso_datetime_utc">Date / Time UTC <span class="req">*</span></label>
      <?= $this->Form->control('qso_datetime_utc', [
          'type' => 'datetime-local', 'class' => 'form-control', 'label' => false,
          'id' => 'qso_datetime_utc', 'required' => true,
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="frequency_mhz">Frequency (MHz)</label>
      <?= $this->Form->control('frequency_mhz', [
          'class' => 'form-control', 'label' => false, 'id' => 'frequency_mhz',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-3">
    <div class="field">
      <label class="form-label" for="band">Band</label>
      <?= $this->Form->control('band', [
          'type' => 'select', 'class' => 'form-select', 'label' => false, 'id' => 'band',
          'options' => \App\Service\HamRadio::bandOptions(),
          'empty' => '— pick a band —',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-3">
    <div class="field">
      <label class="form-label" for="mode">Mode</label>
      <?= $this->Form->control('mode', [
          'type' => 'select', 'class' => 'form-select', 'label' => false, 'id' => 'mode',
          'options' => \App\Service\HamRadio::modeOptions(),
          'empty' => '— pick a mode —',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-3">
    <div class="field">
      <label class="form-label" for="rst_sent">RST sent</label>
      <?= $this->Form->control('rst_sent', [
          'class' => 'form-control', 'label' => false, 'id' => 'rst_sent',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-3">
    <div class="field">
      <label class="form-label" for="rst_received">RST received</label>
      <?= $this->Form->control('rst_received', [
          'class' => 'form-control', 'label' => false, 'id' => 'rst_received',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="field">
      <label class="form-label" for="operator_name">Their name</label>
      <?= $this->Form->control('operator_name', [
          'class' => 'form-control', 'label' => false, 'id' => 'operator_name',
          'placeholder' => 'Optional',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>
  <div class="col-12">
    <div class="field">
      <label class="form-label" for="notes">Notes</label>
      <?= $this->Form->control('notes', [
          'type' => 'textarea', 'rows' => 2,
          'class' => 'form-control', 'label' => false, 'id' => 'notes',
          'templates' => ['inputContainer' => '{{content}}'],
      ]) ?>
    </div>
  </div>

  <!-- Net details: visible only in net mode. Inputs stay in the DOM so the
       form always submits the names; values default to "" for contact rows. -->
  <div class="col-12" x-show="isNet()" x-cloak>
    <div class="form-fieldset">
      <span class="form-fieldset__legend">Net details</span>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="field">
            <label class="form-label" for="ncs_callsign">NCS callsign <span class="req">*</span></label>
            <input type="text" id="ncs_callsign" name="ncs_callsign" class="form-control"
                   :required="isNet()"
                   autocomplete="off" autocapitalize="characters" spellcheck="false">
          </div>
        </div>
        <div class="col-md-4">
          <div class="field">
            <label class="form-label" for="net_title">Net title</label>
            <input type="text" id="net_title" name="net_title" class="form-control"
                   placeholder="e.g. Sunday Morning Net">
          </div>
        </div>
        <div class="col-md-4">
          <div class="field">
            <label class="form-label" for="net_organisation">Organisation</label>
            <input type="text" id="net_organisation" name="net_organisation" class="form-control"
                   placeholder="e.g. MARTS">
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<hr>
<h2>Template</h2>
<?php if (!empty($templates) && $templates->count() > 0): ?>
  <div class="row g-3 mb-3">
    <?php $first = true; ?>
    <?php foreach ($templates as $t): ?>
      <div class="col-md-3">
        <label class="radio-card">
          <input type="radio" name="template_id" value="<?= $t->id ?>" <?= $first ? 'checked' : '' ?>>
          <?php if ($t->thumbnail_path): ?>
            <img src="/<?= h($t->thumbnail_path) ?>" alt="<?= h($t->name) ?>"
                 class="img-fluid rounded mb-2" loading="lazy">
          <?php endif; ?>
          <span class="d-block small fw-semibold"><?= h($t->name) ?></span>
        </label>
      </div>
      <?php $first = false; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="alert alert-info">No templates available — please contact the operator.</div>
<?php endif; ?>

<p class="form-text mt-3 mb-3">
  The background is part of the template you pick above. If none of the available
  templates has the look you want, <a href="/register">create an account</a> to design
  your own template with a custom background.
</p>

<button class="btn btn-primary mt-2">Generate eQSL</button>
<?= $this->Form->end() ?>
</div>
