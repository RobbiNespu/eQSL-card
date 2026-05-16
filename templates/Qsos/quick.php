<?php
/*
 * M5 T7 — Quick-add form. Portable-first one-thumb QSO entry.
 *
 * Stripped to the essentials: callsign, frequency, mode, RST sent/recv,
 * notes. Date/time auto-fills server-side; band derives from frequency.
 * After save the page re-renders empty with the success flash and the
 * callsign field focused so the operator can log the next contact
 * without leaving the keyboard.
 *
 * T8 adds the "Last 5 QSOs" pinned panel.
 * T9 swaps the full-page POST for an XHR + clear+refocus loop.
 * T10 adds notes quick-fill chips.
 * T11 makes the submit button sticky-above-keyboard.
 */
?>

<div class="quick-add">

  <?= $this->element('ui/page_header', [
      'title' => $title,
      'lede'  => 'Log a contact fast. Built for one-thumb use during portable ops — date and band auto-fill, the form clears after save so you can keep going.',
  ]) ?>

  <p class="form-text mb-3">
    Need extra fields (net check-in, internet transport, grid square, operator
    name/QTH)? Use the <a href="/qsos/new">full form</a> instead.
  </p>

  <?= $this->Form->create($qso, [
      'url' => '/qsos/quick',
      'class' => 'quick-add__form',
      'novalidate' => false,
  ]) ?>

    <div class="field">
      <label class="form-label" for="quick-callsign">
        Their callsign <span class="req">*</span>
      </label>
      <input type="text" id="quick-callsign" name="call_worked" class="form-control form-control-lg"
             autofocus autocomplete="off" autocapitalize="characters" spellcheck="false"
             placeholder="e.g. 9M2RDX" required>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-7">
        <div class="field">
          <label class="form-label" for="quick-freq">Frequency (MHz)</label>
          <input type="text" id="quick-freq" name="frequency_mhz" class="form-control"
                 placeholder="e.g. 14.20000" inputmode="decimal" autocomplete="off">
          <p class="form-text small mb-0">Band fills in for you on save.</p>
        </div>
      </div>
      <div class="col-5">
        <div class="field">
          <label class="form-label" for="quick-mode">Mode</label>
          <?= $this->Form->control('mode', [
              'label'   => false,
              'id'      => 'quick-mode',
              'type'    => 'select',
              'class'   => 'form-select',
              'options' => \App\Service\HamRadio::modeOptions(),
              'empty'   => '—',
              'templates' => ['inputContainer' => '{{content}}'],
          ]) ?>
        </div>
      </div>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-6">
        <div class="field">
          <label class="form-label" for="quick-rst-sent">RST sent</label>
          <input type="text" id="quick-rst-sent" name="rst_sent" class="form-control"
                 placeholder="59" inputmode="numeric" autocomplete="off">
        </div>
      </div>
      <div class="col-6">
        <div class="field">
          <label class="form-label" for="quick-rst-recv">RST received</label>
          <input type="text" id="quick-rst-received" name="rst_received" class="form-control"
                 placeholder="59" inputmode="numeric" autocomplete="off">
        </div>
      </div>
    </div>

    <div class="field mt-2">
      <label class="form-label" for="quick-notes">Notes <span class="form-label small">(optional)</span></label>
      <input type="text" id="quick-notes" name="notes" class="form-control"
             placeholder="e.g. POTA 9M-0021, Bukit Larut SOTA" autocomplete="off">
    </div>

    <div class="quick-add__actions form-actions-mobile mt-4">
      <button type="submit" class="btn btn-primary btn-lg">Log contact</button>
      <a class="btn btn-secondary" href="/qsos">Cancel</a>
    </div>

  <?= $this->Form->end() ?>

  <?php /* T8 placeholder — pinned "Last 5 QSOs" panel will render here. */ ?>
  <?php if ($recent->count() > 0): ?>
    <section class="quick-add__recent mt-4" aria-label="Recently logged">
      <h2 class="h6 text-muted">Last logged</h2>
      <ul class="quick-add__recent-list">
        <?php foreach ($recent as $r): ?>
          <li class="quick-add__recent-item">
            <span class="quick-add__recent-call"><?= h($r->call_worked) ?></span>
            <span class="quick-add__recent-meta">
              <?= h($r->band ?: '—') ?> ·
              <?= h($r->mode ?: '—') ?> ·
              <?= h($r->qso_datetime_utc?->format('H:i')) ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

</div>
