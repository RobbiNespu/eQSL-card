<?php
/*
 * M5 T7-T8 — Quick-add form. Portable-first one-thumb QSO entry.
 *
 * Stripped to the essentials: callsign, frequency, mode, RST sent/recv,
 * notes. Date/time auto-fills server-side; band derives from frequency.
 * After save the page re-renders empty with the success flash and the
 * callsign field focused so the operator can log the next contact
 * without leaving the keyboard.
 *
 * T8 — Last-5 panel pinned ABOVE the form; tap a row to clone its
 *      frequency, mode, and notes into the form (useful when a net is
 *      rotating check-ins on one freq). Callsign is left blank — that's
 *      always the per-contact variable.
 * T9 swaps the full-page POST for an XHR + clear+refocus loop.
 * T10 adds notes quick-fill chips.
 * T11 makes the submit button sticky-above-keyboard.
 *
 * Recent rows are pre-serialised to JSON in $recentJson below and handed
 * to Alpine as a single x-data prop so the click handler can index by id
 * without re-querying the DOM.
 */

$recentJson = json_encode(array_map(static function ($r): array {
    return [
        'id'        => (int)$r->id,
        'callsign'  => (string)$r->call_worked,
        'frequency' => (string)($r->frequency_mhz ?? ''),
        'band'      => (string)($r->band ?? ''),
        'mode'      => (string)($r->mode ?? ''),
        'notes'     => (string)($r->notes ?? ''),
        'time'      => $r->qso_datetime_utc?->format('H:i') ?? '',
    ];
}, $recent->toList()), JSON_THROW_ON_ERROR);
?>

<div class="quick-add"
     x-data='quickAddForm(<?= h($recentJson) ?>)'>

  <?= $this->element('ui/page_header', [
      'title' => $title,
      'lede'  => 'Log a contact fast. Built for one-thumb use during portable ops — date and band auto-fill, the form clears after save so you can keep going.',
  ]) ?>

  <p class="form-text mb-3">
    Need extra fields (net check-in, internet transport, grid square, operator
    name/QTH)? Use the <a href="/qsos/new">full form</a> instead.
  </p>

  <?php /* T14 — Active activation banner. Visible when the operator has
         an open activation; T16 will use this to auto-tag QSOs saved
         from this form. Tappable to drill into the activation page. */ ?>
  <?php if (!empty($activeActivation)): ?>
    <a href="/activations" class="quick-add__active-banner" aria-label="Currently logging for <?= h($activeActivation->name) ?> — tap to manage activation">
      <span class="quick-add__active-banner-label">Logging for</span>
      <strong class="quick-add__active-banner-name"><?= h($activeActivation->name) ?></strong>
      <?php if ($activeActivation->grid_square): ?>
        <span class="quick-add__active-banner-grid"><?= h($activeActivation->grid_square) ?></span>
      <?php endif; ?>
    </a>
  <?php else: ?>
    <p class="form-text small mb-3">
      Logging without an activation. <a href="/activations">Start one</a> if you're
      running a POTA / SOTA / net session — every QSO will auto-tag with it.
    </p>
  <?php endif; ?>

  <?php /* T8 — Last-5 panel ABOVE the form. Tappable rows clone freq/
         mode/notes into the form below. */ ?>
  <?php if ($recent->count() > 0): ?>
    <section class="quick-add__recent mb-3" aria-label="Recently logged. Tap a row to copy frequency, mode, and notes into the form.">
      <div class="quick-add__recent-header">
        <h2 class="h6 text-muted mb-0">Last logged</h2>
        <span class="form-text small">Tap to reuse freq/mode/notes</span>
      </div>
      <ul class="quick-add__recent-list">
        <template x-for="r in recent" :key="r.id">
          <li>
            <button type="button" class="quick-add__recent-item"
                    @click="cloneFromRecent(r)"
                    :aria-label="`Reuse settings from QSO with ${r.callsign} at ${r.time}`">
              <span class="quick-add__recent-call" x-text="r.callsign"></span>
              <span class="quick-add__recent-meta">
                <span x-text="r.band || '—'"></span> ·
                <span x-text="r.mode || '—'"></span> ·
                <span x-text="r.time"></span>
              </span>
            </button>
          </li>
        </template>
      </ul>
    </section>
  <?php endif; ?>

  <?php /* T9 — Alpine intercepts submit and POSTs JSON via fetch. The
         plain <form action> still works for no-JS clients (server returns
         the empty-form HTML render with a flash). The custom attribute
         pass-through happens via CakePHP FormHelper options. */ ?>
  <?= $this->Form->create($qso, [
      'url' => '/qsos/quick',
      'class' => 'quick-add__form',
      'novalidate' => false,
      '@submit' => 'submit($event)',
  ]) ?>
    <?php /* Transient flash banner (T9). Replaces the page-reload flash
           on the success path; HTML fallback still uses the Flash component. */ ?>
    <div class="quick-add__flash" x-show="flashMessage" x-cloak
         :class="`quick-add__flash--${flashKind}`"
         role="status" aria-live="polite"
         x-text="flashMessage"></div>

    <div class="field">
      <label class="form-label" for="quick-callsign">
        Their callsign <span class="req">*</span>
      </label>
      <input type="text" id="quick-callsign" name="call_worked"
             class="form-control form-control-lg"
             x-ref="callsign" x-model="form.callsign"
             autofocus autocomplete="off" autocapitalize="characters" spellcheck="false"
             placeholder="e.g. 9M2RDX" required>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-7">
        <div class="field">
          <label class="form-label" for="quick-freq">Frequency (MHz)</label>
          <input type="text" id="quick-freq" name="frequency_mhz" class="form-control"
                 x-model="form.frequency"
                 placeholder="e.g. 14.20000" inputmode="decimal" autocomplete="off">
          <p class="form-text small mb-0">Band fills in for you on save.</p>
        </div>
      </div>
      <div class="col-5">
        <div class="field">
          <label class="form-label" for="quick-mode">Mode</label>
          <select id="quick-mode" name="mode" class="form-select" x-model="form.mode">
            <option value="">—</option>
            <?php foreach (\App\Service\HamRadio::modeOptions() as $code => $label): ?>
              <option value="<?= h($code) ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-6">
        <div class="field">
          <label class="form-label" for="quick-rst-sent">RST sent</label>
          <input type="text" id="quick-rst-sent" name="rst_sent" class="form-control"
                 x-model="form.rstSent"
                 placeholder="59" inputmode="numeric" autocomplete="off">
        </div>
      </div>
      <div class="col-6">
        <div class="field">
          <label class="form-label" for="quick-rst-recv">RST received</label>
          <input type="text" id="quick-rst-received" name="rst_received" class="form-control"
                 x-model="form.rstRecv"
                 placeholder="59" inputmode="numeric" autocomplete="off">
        </div>
      </div>
    </div>

    <div class="field mt-2">
      <label class="form-label" for="quick-notes">Notes <span class="form-label small">(optional)</span></label>
      <input type="text" id="quick-notes" name="notes" class="form-control"
             x-model="form.notes" x-ref="notes"
             placeholder="e.g. POTA 9M-0021, Bukit Larut SOTA" autocomplete="off">
      <?php /* T10 — Notes quick-fill chips. Tap inserts the chip's prefix
             into the notes field so the operator can finish with the
             activation reference (e.g. tap POTA → "POTA " → type "K-1234").
             Defaults below; users can add/remove their own via the chip
             editor's add button (stored in localStorage so it survives
             page reload without a backend change). */ ?>
      <div class="quick-add__chips" role="group" aria-label="Notes quick-fill shortcuts">
        <template x-for="(chip, idx) in chips" :key="`chip-${idx}-${chip.text}`">
          <?php /* Two separate buttons in a wrapper. The chip body inserts;
                 the × removes (only for user-added chips). A nested <button>
                 inside another <button> is invalid HTML and keyboard
                 users couldn't reach the remove — code review caught it. */ ?>
          <span class="quick-add__chip-wrap">
            <button type="button" class="quick-add__chip"
                    @click="insertChip(chip)"
                    :aria-label="`Insert ${chip.text} into notes`">
              <span x-text="chip.text"></span>
            </button>
            <button type="button" class="quick-add__chip-remove"
                    x-show="chip.userAdded"
                    @click="removeChip(idx)"
                    :aria-label="`Remove ${chip.text} chip`">&times;</button>
          </span>
        </template>
        <button type="button" class="quick-add__chip quick-add__chip--add"
                @click="addChipFromInput()"
                :disabled="!form.notes.trim()"
                title="Save the current notes content as a new chip">+ Save as chip</button>
      </div>
    </div>

    <div class="quick-add__actions form-actions-mobile mt-4">
      <button type="submit" class="btn btn-primary btn-lg">Log contact</button>
      <a class="btn btn-secondary" href="/qsos">Cancel</a>
    </div>

  <?= $this->Form->end() ?>

</div>
