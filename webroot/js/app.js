/*
 * Tiny replacement for the two pieces of Bootstrap JS we used to load:
 *   - data-toggle="dropdown"  → toggle .show on the next .dropdown-menu sibling
 *   - data-toggle="collapse"  → toggle .show on the element matching data-target
 *
 * We previously read these from data-bs-* attributes (Bootstrap 5 native).
 * Now templates author them as data-toggle / data-target so the markup is
 * library-neutral. The existing dropdown/collapse markup stays unchanged.
 *
 * Why not Alpine? Alpine is loaded last (after this script) for an
 * unrelated reason, and we want the navbar to start working before
 * Alpine has hydrated the rest of the page.
 */
(function () {
  function closeAllDropdowns(except) {
    document.querySelectorAll('.dropdown-menu.show').forEach(m => {
      if (m !== except) m.classList.remove('show');
    });
  }

  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-toggle]');
    if (trigger) {
      const kind = trigger.getAttribute('data-toggle');

      if (kind === 'dropdown') {
        e.preventDefault();
        const wrap = trigger.closest('.dropdown, .nav-item');
        const menu = wrap ? wrap.querySelector('.dropdown-menu') : null;
        if (!menu) return;
        const wasOpen = menu.classList.contains('show');
        closeAllDropdowns(menu);
        menu.classList.toggle('show', !wasOpen);
        trigger.setAttribute('aria-expanded', !wasOpen ? 'true' : 'false');
        return;
      }

      if (kind === 'collapse') {
        e.preventDefault();
        const sel = trigger.getAttribute('data-target');
        const target = sel ? document.querySelector(sel) : null;
        if (!target) return;
        const open = !target.classList.contains('show');
        target.classList.toggle('show', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        return;
      }
    }

    /* Clicking outside any open dropdown closes them all. */
    if (!e.target.closest('.dropdown-menu')) {
      closeAllDropdowns(null);
    }
  });

  /* Escape key closes any open dropdown — keyboard accessibility. */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAllDropdowns(null);
  });
})();

/*
 * Three-state theme toggle: light → dark → system → light.
 *   - localStorage key 'eqsl-theme' stores the user's chosen state.
 *   - The pre-paint <script> in the layout's <head> reads this and
 *     applies the right data-theme on <html> before any CSS loads.
 *   - This handler runs *after* the navbar is in the DOM. It updates
 *     localStorage + re-runs the same resolver to flip data-theme.
 *   - Sun icon shows in dark mode; moon shows in light mode. The
 *     "system" state defaults to whichever the OS prefers and shows
 *     the matching icon.
 */
(function () {
  function resolve(pref) {
    if (pref === 'dark') return 'eqsl-dark';
    if (pref === 'light') return 'eqsl';
    return window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'eqsl-dark' : 'eqsl';
  }

  function applyIcons() {
    var theme = document.documentElement.getAttribute('data-theme');
    var sun  = document.querySelector('.theme-icon--sun');
    var moon = document.querySelector('.theme-icon--moon');
    if (!sun || !moon) return;
    if (theme === 'eqsl-dark') { sun.style.display = ''; moon.style.display = 'none'; }
    else                       { sun.style.display = 'none'; moon.style.display = ''; }
  }

  function setTitle(pref) {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    btn.setAttribute('title', 'Theme: ' + pref + ' (click to cycle)');
  }

  function cycle() {
    var current = localStorage.getItem('eqsl-theme') || 'system';
    var next = current === 'light' ? 'dark'
             : current === 'dark'  ? 'system'
             :                       'light';
    localStorage.setItem('eqsl-theme', next);
    var resolved = resolve(next);
    document.documentElement.setAttribute('data-theme', resolved);
    document.documentElement.setAttribute('data-theme-pref', next);
    applyIcons();
    setTitle(next);
  }

  document.addEventListener('DOMContentLoaded', function () {
    applyIcons();
    setTitle(localStorage.getItem('eqsl-theme') || 'system');
    var btn = document.getElementById('themeToggle');
    if (btn) btn.addEventListener('click', cycle);
  });

  /* If the OS preference changes while a "system" user has the page open,
     update without requiring a reload. */
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
    if ((localStorage.getItem('eqsl-theme') || 'system') === 'system') {
      document.documentElement.setAttribute('data-theme', resolve('system'));
      applyIcons();
    }
  });
})();

function cameraForm() {
    return {
        mode: 'default',
        captured: '',
        // QSO type for the guest /generate form. Mirrors the logged-in
        // qsos/add toggle so the public form can produce net check-in cards
        // too (placeholders like {ncs_callsign}, {net_title} resolve).
        qsoType: 'contact',
        isNet() { return this.qsoType === 'net'; },
        async startCamera() {
            this.mode = 'camera';
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                this.$refs.video.srcObject = stream;
            } catch (e) {
                alert('Camera unavailable: ' + e.message);
                this.mode = 'upload';
            }
        },
        capture() {
            const v = this.$refs.video, c = this.$refs.canvas;
            c.width = v.videoWidth; c.height = v.videoHeight;
            c.getContext('2d').drawImage(v, 0, 0);
            this.captured = c.toDataURL('image/jpeg', 0.9);
            v.srcObject?.getTracks().forEach(t => t.stop());
        },
    };
}
window.cameraForm = cameraForm;

function bulkRenderForm() {
    return {
        selected: [],
        modalOpen: false,
        started: false,
        finished: false,
        templateId: '',
        done: 0,
        total: 0,
        skipped: 0,
        message: '',
        jobToken: null,
        toggleOne(id, on) {
            id = parseInt(id, 10);
            if (on) {
                if (!this.selected.includes(id)) this.selected.push(id);
            } else {
                this.selected = this.selected.filter(x => x !== id);
            }
        },
        toggleAll(on) {
            const cbs = document.querySelectorAll('tbody input[type="checkbox"]');
            cbs.forEach(cb => {
                cb.checked = on;
                this.toggleOne(parseInt(cb.value, 10), on);
            });
        },
        openBulkModal() { this.modalOpen = true; this.started = false; this.finished = false; },
        closeModal() { this.modalOpen = false; },
        async startBulk() {
            this.started = true;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                || document.cookie.match(/csrfToken=([^;]+)/)?.[1] || '';
            const body = new URLSearchParams();
            body.append('template_id', this.templateId);
            this.selected.forEach(id => body.append('qso_ids[]', id));
            const r = await fetch('/qsos/bulk-render', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                body,
                credentials: 'same-origin',
            });
            const data = await r.json();
            this.jobToken = data.job_token;
            this.done = data.done;
            this.total = data.total;
            this.finished = data.finished;
            this.skipped = data.skipped || 0;
            this.message = data.message || '';
            if (!this.finished) {
                await this.pollNext(csrf);
            }
        },
        async pollNext(csrf) {
            while (!this.finished) {
                const r = await fetch(`/qsos/bulk-render/${this.jobToken}/next`, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await r.json();
                this.done = data.done;
                this.total = data.total;
                this.finished = data.finished;
                this.skipped = data.skipped || this.skipped;
            }
        },
    };
}
window.bulkRenderForm = bulkRenderForm;

/**
 * M5 T7-T8 — Quick-add form Alpine.js component.
 *
 * Backing state for /qsos/quick. Holds the form values reactively so
 * `cloneFromRecent()` can mutate band/mode/frequency/notes when the
 * operator taps a recent QSO row to reuse its parameters (typical net
 * scenario: same freq + mode for a string of check-ins).
 *
 * Callsign and RST are NOT cloned — those are always per-contact
 * variables. After clone we focus the callsign input so the next
 * action is "type the new callsign".
 *
 * `recent` is pre-serialised by the template (PHP → JSON) and passed
 * in as a constructor arg.
 *
 * T9 will extend this with submit() that POSTs via fetch + clears the
 * form on success, replacing the synchronous re-render currently
 * handled by QsosController::quick().
 */
function quickAddForm(recent) {
    return {
        recent: Array.isArray(recent) ? recent : [],
        form: {
            callsign: '',
            frequency: '',
            mode: '',
            rstSent: '',
            rstRecv: '',
            notes: '',
        },
        cloneFromRecent(r) {
            if (!r || typeof r !== 'object') return;
            this.form.frequency = r.frequency || '';
            this.form.mode      = r.mode      || '';
            this.form.notes     = r.notes     || '';
            // Clear callsign + RST so the operator types the new contact's
            // values fresh — those should never carry forward.
            this.form.callsign = '';
            this.form.rstSent  = '';
            this.form.rstRecv  = '';
            // Hand focus back to the callsign input so the next tap is
            // "type the new callsign".
            this.$nextTick(() => {
                if (this.$refs.callsign) this.$refs.callsign.focus();
            });
        },
    };
}
window.quickAddForm = quickAddForm;
