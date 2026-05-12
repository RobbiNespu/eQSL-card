<h1><?= $mode === 'edit' ? 'Edit QSO' : 'Add QSO' ?></h1>
<p>
  <?= $mode === 'edit'
        ? 'Update this log entry. Saved values still feed any cards rendered from this QSO.'
        : 'Log a contact or a net check-in. The form adapts to which kind of QSO you select below.' ?>
</p>

<?php
// Alpine state for the contact/net toggle. Initial value comes from the
// entity (so editing a net QSO loads in net mode) and falls back to
// 'contact' for new rows. ncsPrefill defaults to the user's own callsign —
// most operators are running the nets they log; they can edit if they're
// scribing someone else's net.
$initialType = $qso->qso_type ?? 'contact';
$ncsCurrent  = $qso->ncs_callsign ?? '';
$ncsPrefill  = $ncsCurrent !== '' ? $ncsCurrent : ($operatorCallsign ?? '');
$initialTransport = $qso->transport ?? 'rf';

/*
 * Build the x-data initial state as one JSON object → one HTML-escape
 * step. Pasting raw json_encode() output inline inside an HTML attribute
 * would close the attribute the moment a string flows through, because
 * the encoded value starts/ends with literal double quotes.
 *
 * NB: this block uses a /-star comment (not //) on purpose. PHP // line
 * comments terminate at end-of-line AND at the first ?> token — so a
 * stray "?>" inside a // comment silently closes the <?php block and
 * dumps every subsequent statement as literal HTML.
 */
$initialState = h(json_encode([
    'qsoType'       => $initialType,
    'ncsCallsign'   => $ncsCurrent,
    'netTitle'      => $qso->net_title ?? '',
    'netOrg'        => $qso->net_organisation ?? '',
    'transport'     => $initialTransport,
    'transportMeta' => $qso->transport_meta ?? '',
    'callsign'      => $qso->call_worked ?? '',
    'operatorName'  => $qso->operator_name ?? '',
    'operatorQth'   => $qso->operator_qth ?? '',
    'gridSquare'    => $qso->grid_square ?? '',
    'ncsPrefill'    => $ncsPrefill,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>

<?php
/*
 * The Alpine factory lives in the <script> block below so we don't have
 * to escape JS string delimiters, apostrophes-in-comments, or PHP
 * close-tag tokens against the HTML-attribute parser. The x-data
 * attribute stays short — it just calls the factory with the
 * server-side initial state and returns the data object Alpine
 * processes.
 *
 * $this->start('script') queues this block into the layout's deferred
 * script slot so it runs after Alpine has loaded but before Alpine
 * processes the DOM (see layout/default.php for the load order).
 */
$this->start('script');
?>
<script>
function qsoFormState(initial) {
  return Object.assign({}, initial, {
    lookupSource: '',
    lookupTimer: null,
    lookupAbort: null,
    isNet()      { return this.qsoType === 'net'; },
    isInternet() { return this.transport !== 'rf'; },
    onSwitchNet() {
      /* Prefill NCS with the user's callsign on first toggle, so the
         operator doesn't have to retype the same thing every time. */
      if (!this.ncsCallsign) this.ncsCallsign = this.ncsPrefill;
    },
    onCallsignInput() {
      /* Debounced upstream lookup. We only fire if the user has stopped
         typing for 700ms — keystroke-rate fetches would hammer providers
         and waste cache space on partial inputs. */
      this.lookupSource = '';
      if (this.lookupTimer) clearTimeout(this.lookupTimer);
      if (this.lookupAbort) this.lookupAbort.abort();
      const call = (this.callsign || '').toUpperCase().trim();
      if (call.length < 3) return;
      this.lookupTimer = setTimeout(() => this.fetchLookup(call), 700);
    },
    async fetchLookup(call) {
      this.lookupAbort = new AbortController();
      try {
        const r = await fetch('/api/callsign/' + encodeURIComponent(call), {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          signal: this.lookupAbort.signal,
        });
        if (r.status === 204 || r.status === 404) return; /* no hit or feature off */
        if (!r.ok) return;
        const data = await r.json();
        if (!data || !data.result) return;
        const res = data.result;
        /* Never clobber user-typed values — only fill empty fields. */
        if (!this.operatorName && res.name)       this.operatorName = res.name;
        if (!this.operatorQth  && res.qth)        this.operatorQth  = res.qth;
        if (!this.gridSquare   && res.grid_square) this.gridSquare  = res.grid_square;
        this.lookupSource = res.source || '';
      } catch (e) {
        /* Abort or transport error — silent. The user can still type by hand. */
      }
    },
    clearLookup() {
      /* Manual reset if the auto-filled values are wrong for this contact. */
      this.operatorName = '';
      this.operatorQth  = '';
      this.gridSquare   = '';
      this.lookupSource = '';
    },
  });
}
</script>
<?php $this->end(); ?>

<div x-data="qsoFormState(<?= $initialState ?>)">
<?= $this->Form->create($qso) ?>

  <!-- QSO type toggle ----------------------------------------------------- -->
  <div class="field" style="margin-bottom: var(--s-5);">
    <label class="form-label">QSO type</label>
    <div class="btn-group" role="group" aria-label="QSO type">
      <input type="radio" class="btn-check" id="qsoType-contact" name="qso_type" value="contact"
             x-model="qsoType">
      <label class="btn btn-outline-primary" for="qsoType-contact">Contact QSO</label>

      <input type="radio" class="btn-check" id="qsoType-net" name="qso_type" value="net"
             x-model="qsoType" @change="onSwitchNet()">
      <label class="btn btn-outline-primary" for="qsoType-net">Net check-in</label>
    </div>
    <p class="form-text">
      <span x-show="!isNet()">A 1:1 contact between two stations.</span>
      <span x-show="isNet()" x-cloak>The NCS issues the card to a net participant. Fill in the net details below.</span>
    </p>
  </div>

  <!-- Core QSO details --------------------------------------------------- -->
  <div class="row g-3">

    <div class="col-md-6">
      <div class="field">
        <label class="form-label" for="call-worked">
          <span x-text="isNet() ? 'Participant callsign' : 'Their callsign'">Their callsign</span>
          <span class="req">*</span>
        </label>
        <input type="text" id="call-worked" name="call_worked" class="form-control"
               x-model="callsign"
               @input.debounce.300ms="onCallsignInput()"
               @blur="onCallsignInput()"
               autocomplete="off" autocapitalize="characters" spellcheck="false"
               placeholder="e.g. W1AW"
               required>
        <p class="form-text text-success" x-show="lookupSource" x-cloak>
          Auto-filled from <strong x-text="lookupSource"></strong>.
          <button type="button" class="btn-link" style="padding: 0; min-height: 0;" @click="clearLookup()">Clear</button>
        </p>
      </div>
    </div>

    <div class="col-md-6">
      <div class="field">
        <label class="form-label" for="qso-datetime-utc">Date / Time UTC <span class="req">*</span></label>
        <?= $this->Form->control('qso_datetime_utc', [
            'type'  => 'datetime-local',
            'label' => false,
            'id'    => 'qso-datetime-utc',
            'class' => 'form-control',
            'required' => true,
            'templates' => ['inputContainer' => '{{content}}'],
        ]) ?>
        <p class="form-text">UTC — not your local time. Use a UTC clock to be sure.</p>
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
              <label class="form-label" for="ncs-callsign">NCS callsign <span class="req">*</span></label>
              <input type="text" id="ncs-callsign" name="ncs_callsign" class="form-control"
                     x-model="ncsCallsign" :required="isNet()"
                     autocomplete="off" autocapitalize="characters" spellcheck="false">
            </div>
          </div>
          <div class="col-md-4">
            <div class="field">
              <label class="form-label" for="net-title">Net title <span class="req">*</span></label>
              <input type="text" id="net-title" name="net_title" class="form-control"
                     x-model="netTitle" :required="isNet()"
                     placeholder="e.g. PARTY 9M2 Daily Net">
            </div>
          </div>
          <div class="col-md-4">
            <div class="field">
              <label class="form-label" for="net-org">Organisation</label>
              <input type="text" id="net-org" name="net_organisation" class="form-control"
                     x-model="netOrg" placeholder="e.g. MARTS">
            </div>
          </div>
        </div>
      </div>
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

    <!-- Transport ------------------------------------------------------- -->
    <div class="col-md-4">
      <div class="field">
        <label class="form-label" for="transport">Transport</label>
        <select id="transport" name="transport" class="form-select" x-model="transport">
          <?php foreach (\App\Service\Transport::options($initialTransport) as $code => $label): ?>
            <option value="<?= h($code) ?>"<?= $code === $initialTransport ? ' selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="form-text">
          <span x-show="!isInternet()">Standard over-the-air contact.</span>
          <span x-show="isInternet()" x-cloak>Internet-mediated. Frequency &amp; band are optional below.</span>
        </p>
      </div>
    </div>

    <div class="col-md-8" x-show="isInternet()" x-cloak>
      <div class="field">
        <label class="form-label" for="transport-meta">Channel / node / server <span class="form-label small">(optional)</span></label>
        <input type="text" id="transport-meta" name="transport_meta" class="form-control"
               x-model="transportMeta"
               placeholder="e.g. Echolink node 12345, Mumble: hamradio.example.com">
      </div>
    </div>
    <!-- Always-submit fallback for transport_meta when transport=='rf' so
         toggling internet → rf doesn't leak the previously-typed value. -->
    <template x-if="!isInternet()">
      <input type="hidden" name="transport_meta" value="">
    </template>

    <!-- Frequency / band / mode --------------------------------------- -->
    <div class="col-md-4">
      <div class="field">
        <label class="form-label" for="frequency-mhz">Frequency (MHz)</label>
        <?= $this->Form->control('frequency_mhz', [
            'label' => false,
            'id'    => 'frequency-mhz',
            'class' => 'form-control',
            'placeholder' => 'e.g. 14.07415',
            'templates' => ['inputContainer' => '{{content}}'],
        ]) ?>
        <p class="form-text">Megahertz. Up to 4 decimal places.</p>
      </div>
    </div>
    <div class="col-md-4">
      <?= $this->Form->control('band', [
          'label'   => 'Band',
          'type'    => 'select',
          'class'   => 'form-select',
          'options' => \App\Service\HamRadio::bandOptions($qso->band ?? null),
          'empty'   => '— pick a band —',
      ]) ?>
    </div>
    <div class="col-md-4">
      <?= $this->Form->control('mode', [
          'label'   => 'Mode',
          'type'    => 'select',
          'class'   => 'form-select',
          'options' => \App\Service\HamRadio::modeOptions($qso->mode ?? null),
          'empty'   => '— pick a mode —',
      ]) ?>
    </div>

    <!-- Signal report ------------------------------------------------- -->
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

    <!-- Operator details (Alpine-bound so callsign autofill can populate). -->
    <div class="col-md-6">
      <div class="field">
        <label class="form-label" for="operator-name">Their name</label>
        <input type="text" id="operator-name" name="operator_name" class="form-control"
               x-model="operatorName" autocomplete="off">
      </div>
    </div>
    <div class="col-md-6">
      <div class="field">
        <label class="form-label" for="operator-qth">QTH</label>
        <input type="text" id="operator-qth" name="operator_qth" class="form-control"
               x-model="operatorQth" placeholder="City, country">
      </div>
    </div>
    <div class="col-md-6">
      <div class="field">
        <label class="form-label" for="grid-square">Grid square</label>
        <input type="text" id="grid-square" name="grid_square" class="form-control"
               x-model="gridSquare" placeholder="e.g. OJ02wx" autocomplete="off">
        <p class="form-text">Maidenhead locator — 4 or 6 characters.</p>
      </div>
    </div>
    <div class="col-12">
      <?= $this->Form->control('notes', [
          'type'  => 'textarea',
          'rows'  => 3,
          'label' => 'Notes',
          'class' => 'form-control',
      ]) ?>
    </div>
  </div>

  <div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary"><?= $mode === 'edit' ? 'Save changes' : 'Add QSO' ?></button>
    <a class="btn btn-secondary" href="/qsos">Cancel</a>
  </div>
<?= $this->Form->end() ?>
</div>
