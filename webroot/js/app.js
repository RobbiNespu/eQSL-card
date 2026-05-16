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
 * M5 T7-T9 — Quick-add form Alpine.js component.
 *
 * Backing state for /qsos/quick. Holds the form values reactively so
 * `cloneFromRecent()` can mutate band/mode/frequency/notes when the
 * operator taps a recent QSO row, and so `submit()` can POST via fetch
 * (T9) instead of a full page reload.
 *
 * `recent` is pre-serialised by the template (PHP → JSON) and passed
 * in as a constructor arg.
 *
 * After a successful save:
 *   - The new QSO is prepended to recent[] (panel updates without reload).
 *   - call_worked + RST are cleared (per-contact fields).
 *   - frequency/mode/notes are PRESERVED — net rotations don't re-type.
 *   - Callsign input is refocused → next action is "type the new callsign".
 *   - A transient flash banner fades after 3 s.
 *
 * No-JS fallback: the <form> still posts synchronously and the
 * controller renders the empty form with a flash. Tests cover this
 * path in QsosControllerQuickTest.
 */
// T10 — Defaults for the notes quick-fill chips. User additions are
// stored in localStorage under QUICK_ADD_CHIPS_KEY and prepended to
// this list. Removing a default isn't supported (operators usually
// want the standard set always available); only user-added chips
// show the × remove affordance.
const QUICK_ADD_DEFAULT_CHIPS = ['Net', 'POTA', 'SOTA', 'Contest', 'Ragchew'];
const QUICK_ADD_CHIPS_KEY = 'eqsl-quick-add-chips';

function quickAddForm(recent) {
    return {
        recent: Array.isArray(recent) ? recent : [],
        submitting: false,
        flashKind: '',     // 'success' | 'error' | ''
        flashMessage: '',
        flashTimer: null,
        chips: [],
        form: {
            callsign: '',
            frequency: '',
            mode: '',
            rstSent: '',
            rstRecv: '',
            notes: '',
        },
        init() {
            // Load user-added chips from localStorage (if any), then
            // concatenate the defaults. Defaults always render last so a
            // user who saved their MARTS net first sees that on the left.
            let userChips = [];
            try {
                const raw = localStorage.getItem(QUICK_ADD_CHIPS_KEY);
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) {
                        userChips = parsed
                            .filter(s => typeof s === 'string' && s.trim() !== '')
                            .map(s => ({ text: s.trim(), userAdded: true }));
                    }
                }
            } catch (e) { /* malformed storage — ignore */ }
            this.chips = [
                ...userChips,
                ...QUICK_ADD_DEFAULT_CHIPS.map(t => ({ text: t, userAdded: false })),
            ];
        },
        insertChip(chip) {
            if (!chip || !chip.text) return;
            // Replace notes content with the chip + trailing space, so the
            // operator can immediately type the activation reference
            // (e.g. "POTA " → "POTA K-1234"). Replace rather than append:
            // most chips are mutually exclusive activation types.
            this.form.notes = chip.text + ' ';
            this.$nextTick(() => {
                if (this.$refs.notes) {
                    this.$refs.notes.focus();
                    // Move cursor to end of input for easy continuation.
                    const len = this.$refs.notes.value.length;
                    this.$refs.notes.setSelectionRange(len, len);
                }
            });
        },
        addChipFromInput() {
            const text = this.form.notes.trim();
            if (!text) return;
            // Case-insensitive duplicate check so "POTA" and "pota" don't
            // both end up in the list. Code review caught the strict-eq
            // version that allowed both to be added.
            const lc = text.toLowerCase();
            if (this.chips.some(c => c.text.toLowerCase() === lc)) {
                this.showFlash('error', `"${text}" is already a chip.`);
                return;
            }
            this.chips.unshift({ text, userAdded: true });
            this.saveUserChips();
            this.showFlash('success', `Saved "${text}" as a chip.`);
        },
        removeChip(idx) {
            if (idx < 0 || idx >= this.chips.length) return;
            if (!this.chips[idx].userAdded) return;  // can't remove defaults
            this.chips.splice(idx, 1);
            this.saveUserChips();
        },
        saveUserChips() {
            const userChips = this.chips
                .filter(c => c.userAdded)
                .map(c => c.text);
            try {
                localStorage.setItem(QUICK_ADD_CHIPS_KEY, JSON.stringify(userChips));
            } catch (e) { /* quota / private mode — best-effort */ }
        },
        cloneFromRecent(r) {
            if (!r || typeof r !== 'object') return;
            this.form.frequency = r.frequency || '';
            this.form.mode      = r.mode      || '';
            this.form.notes     = r.notes     || '';
            this.form.callsign = '';
            this.form.rstSent  = '';
            this.form.rstRecv  = '';
            this.$nextTick(() => {
                if (this.$refs.callsign) this.$refs.callsign.focus();
            });
        },
        async submit($event) {
            $event.preventDefault();
            if (this.submitting) return;
            if (!this.form.callsign.trim()) {
                this.showFlash('error', 'Callsign is required.');
                return;
            }
            this.submitting = true;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                || document.cookie.match(/csrfToken=([^;]+)/)?.[1] || '';
            const body = new URLSearchParams({
                call_worked:   this.form.callsign,
                frequency_mhz: this.form.frequency,
                mode:          this.form.mode,
                rst_sent:      this.form.rstSent,
                rst_received:  this.form.rstRecv,
                notes:         this.form.notes,
            });

            try {
                const resp = await fetch('/qsos/quick', {
                    method: 'POST',
                    headers: {
                        'Accept':       'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': decodeURIComponent(csrf),
                    },
                    body,
                });
                const data = await resp.json().catch(() => null);
                if (!resp.ok || !data || !data.ok) {
                    this.showFlash('error',
                        data?.errors
                            ? 'Save failed — check fields.'
                            : `Save failed (${resp.status}).`);
                    return;
                }
                this.recent = [data.qso, ...this.recent].slice(0, 5);
                this.form.callsign = '';
                this.form.rstSent  = '';
                this.form.rstRecv  = '';
                this.showFlash('success', `Logged ${data.qso.callsign}.`);
                this.$nextTick(() => {
                    if (this.$refs.callsign) this.$refs.callsign.focus();
                });
            } catch (e) {
                this.showFlash('error', 'Network error — please retry.');
            } finally {
                this.submitting = false;
            }
        },
        showFlash(kind, message) {
            this.flashKind = kind;
            this.flashMessage = message;
            if (this.flashTimer) clearTimeout(this.flashTimer);
            this.flashTimer = setTimeout(() => {
                this.flashKind = '';
                this.flashMessage = '';
            }, 3000);
        },
    };
}
window.quickAddForm = quickAddForm;

/**
 * M5 T11 — Keep sticky form actions above the on-screen keyboard.
 *
 * The Android Chrome side is handled by the meta-viewport
 * `interactive-widget=resizes-content` hint, which shrinks the layout
 * viewport when the keyboard opens — `position: sticky; bottom: 0`
 * then naturally rests just above the keyboard.
 *
 * iOS Safari ignores that hint and only shrinks the VISUAL viewport
 * (not layout), so fixed/sticky elements end up *behind* the keyboard.
 * This listener uses the Visual Viewport API to compute how much of
 * the layout viewport the keyboard is covering, then writes that as a
 * --keyboard-inset CSS variable. Sticky elements offset their `bottom`
 * by that amount to stay above the keyboard.
 *
 * No-op on browsers without window.visualViewport (older mobile
 * Safari, IE). Worst case: sticky button stays at bottom: 0 and
 * possibly overlaps with keyboard. The form is still usable.
 */
function initKeyboardAware() {
    const vv = window.visualViewport;
    if (!vv) return;
    const update = () => {
        // Difference between layout viewport bottom and visual viewport
        // bottom = the keyboard's visible height (in CSS px).
        const inset = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
        document.documentElement.style.setProperty('--keyboard-inset', inset + 'px');
    };
    vv.addEventListener('resize', update);
    vv.addEventListener('scroll', update);
    update();
}
document.addEventListener('DOMContentLoaded', initKeyboardAware);

/**
 * M5 T15 — Alpine component factory for the activations form's "Use my location"
 * button. Hands the operator a one-tap path from browser GPS to a
 * pre-filled Maidenhead grid square input. Manual override always
 * available — the operator may know the official reference grid
 * better than what GPS rounds to (a SOTA summit is its summit grid,
 * not where you're sitting at the moment of activation).
 *
 * State machine:
 *   idle    → user hasn't tapped, or has cleared
 *   asking  → permission prompt is open / position request in flight
 *   ok      → grid filled
 *   denied  → user said no to permission
 *   error   → other failure (timeout, no GPS hardware, etc.)
 *
 * No localStorage cache for the permission — the browser handles that
 * via its own permission UI. We're not trying to remember "you said
 * yes last time"; just expose the affordance.
 */
function activationGpsHelper() {
    return {
        gpsState: 'idle',
        gpsMessage: '',
        async fillGridFromGps() {
            if (!navigator.geolocation) {
                this.gpsState = 'error';
                this.gpsMessage = 'Your browser doesn\'t support geolocation.';
                return;
            }
            this.gpsState = 'asking';
            this.gpsMessage = 'Asking for your location…';

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const grid = window.latLonToGridSquare(
                        pos.coords.latitude,
                        pos.coords.longitude,
                        6
                    );
                    if (grid === null) {
                        this.gpsState = 'error';
                        this.gpsMessage = 'Could not convert your coordinates.';
                        return;
                    }
                    const input = this.$refs.gridInput;
                    if (input) {
                        input.value = grid;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    this.gpsState = 'ok';
                    this.gpsMessage = 'Grid filled: ' + grid + '. Override if you know a more accurate reference.';
                },
                (err) => {
                    if (err.code === err.PERMISSION_DENIED) {
                        this.gpsState = 'denied';
                        this.gpsMessage = 'Permission denied. Type the grid manually.';
                    } else if (err.code === err.TIMEOUT) {
                        this.gpsState = 'error';
                        this.gpsMessage = 'Location request timed out. Try outside or near a window.';
                    } else {
                        this.gpsState = 'error';
                        this.gpsMessage = 'Could not get location: ' + (err.message || 'unknown error');
                    }
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        },
    };
}
window.activationGpsHelper = activationGpsHelper;

/**
 * M5 T19 — Register the service worker so caching + (soon) offline
 * support engage. Only runs on HTTPS (or localhost for dev); registers
 * at the root scope so requests across the whole origin can be
 * intercepted.
 *
 * Skipped entirely on browsers without serviceWorker support — older
 * Safari, iOS < 11, IE. The app degrades gracefully: no offline, but
 * everything else works.
 *
 * The worker self-updates on every page load (browsers check sw.js
 * for changes); skipWaiting + clients.claim in sw.js mean a new
 * worker activates immediately rather than waiting for all tabs to
 * close. Bump CACHE_VERSION in sw.js on each release.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        // Base path injected by the layout: '' for root deploy, '/qsl' for
        // subfolder. The SW URL + scope both need the prefix so the SW
        // doesn't try to claim the entire origin (which would conflict
        // with other apps on the same host on subfolder deploys).
        const base = (typeof window.EQSL_BASE === 'string') ? window.EQSL_BASE : '';
        navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' })
            .catch((err) => {
                // No console.error — failed registration is usually a
                // dev-environment HTTP issue and isn't actionable for the
                // operator. Silent fallback is fine.
            });
    });
}
