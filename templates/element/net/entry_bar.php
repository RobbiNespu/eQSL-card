<?php
/**
 * Net cockpit — fast entry bar.
 *
 * Server-rendered form for logging a check-in. All inputs have data-* hooks
 * for Task 12 JS (net-cockpit.js) to intercept the submit, clear fields,
 * and append the new row to the roster without a page reload.
 *
 * Received variables:
 *   @var \App\Model\Entity\NetSession $session
 *   @var \App\View\AppView $this
 */
?>
<form data-net-entry
      method="post"
      action="/net-sessions/<?= (int)$session->id ?>/checkins"
      class="net-entry-bar"
      autocomplete="off"
      novalidate>
  <?= $this->Form->hidden('', ['name' => '_csrfToken', 'value' => $this->getRequest()->getAttribute('csrfToken')]) ?>
  <div class="net-entry-bar__fields">

    <div class="net-entry-bar__field">
      <label class="form-label" for="ne-callsign">Callsign <span class="req">*</span></label>
      <input type="text" id="ne-callsign" name="call_worked"
             class="form-control"
             style="text-transform:uppercase"
             placeholder="9W2ABC"
             autocapitalize="characters"
             spellcheck="false"
             autocomplete="off"
             required>
      <div data-entry-hint class="form-text small text-warning mb-0"></div>
    </div>

    <div class="net-entry-bar__field">
      <label class="form-label" for="ne-name">Name</label>
      <input type="text" id="ne-name" name="operator_name"
             class="form-control"
             placeholder="optional"
             autocomplete="off">
    </div>

    <div class="net-entry-bar__field net-entry-bar__field--grid">
      <label class="form-label" for="ne-grid">Grid</label>
      <input type="text" id="ne-grid" name="grid_square"
             class="form-control"
             placeholder="OJ02"
             maxlength="8"
             autocomplete="off">
    </div>

    <div class="net-entry-bar__field net-entry-bar__field--rst">
      <label class="form-label" for="ne-rst">RST</label>
      <input type="text" id="ne-rst" name="rst_received"
             class="form-control"
             value="59"
             inputmode="numeric"
             autocomplete="off">
    </div>

    <div class="net-entry-bar__field net-entry-bar__field--role">
      <label class="form-label" for="ne-role">Role</label>
      <select id="ne-role" name="net_role" class="form-select">
        <option value="NCS">NCS</option>
        <option value="Relay">Relay</option>
        <option value="Check-in" selected>Check-in</option>
        <option value="Traffic">Traffic</option>
      </select>
    </div>

    <div class="net-entry-bar__submit">
      <label class="form-label" aria-hidden="true">&nbsp;</label>
      <button type="submit" class="btn btn-success w-100">+ Log</button>
    </div>

  </div>
</form>
