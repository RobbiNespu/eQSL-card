/*
 * Tiny vanilla focus-trap. Activate by giving an element data-focus-trap
 * and showing/hiding it with a CSS class (e.g. via Alpine x-show).
 *
 * Usage in Alpine:
 *   <div x-show="modalOpen" x-init="$el._trap = focusTrap.attach($el)"
 *        x-effect="modalOpen ? $el._trap.activate() : $el._trap.deactivate()">
 *
 * The trap remembers the element that was focused when activated and
 * restores focus to it on deactivate. ESC inside the trap fires a
 * custom 'focustrap:escape' event the caller can listen for to close
 * the modal.
 */
(function () {
  var FOCUSABLE = [
    'a[href]', 'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])', 'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
  ].join(',');

  /**
   * Return all visible, focusable descendants of `root` in DOM order.
   * Filters out elements with offsetParent === null (i.e. display:none subtrees).
   * @param {HTMLElement} root
   * @returns {HTMLElement[]}
   */
  function getFocusable(root) {
    return Array.prototype.slice.call(root.querySelectorAll(FOCUSABLE))
      .filter(function (el) { return el.offsetParent !== null; });
  }

  /**
   * Create a focus-trap controller for `root`. Returns an object with
   * `activate()` and `deactivate()` methods.
   *
   * `activate()` — saves the currently focused element, moves focus to the
   * first focusable child, and installs a keydown handler that cycles Tab
   * within the trap and fires a `focustrap:escape` event on Escape.
   *
   * `deactivate()` — removes the keydown handler and restores focus to the
   * element that was focused before `activate()` was called.
   *
   * @param {HTMLElement} root - modal or dialog element to trap focus within
   * @returns {{ activate: function, deactivate: function }}
   */
  function attach(root) {
    var savedFocus = null;
    var keydownHandler = null;

    return {
      activate: function () {
        savedFocus = document.activeElement;
        var focusables = getFocusable(root);
        if (focusables.length > 0) focusables[0].focus();

        keydownHandler = function (e) {
          if (e.key === 'Escape') {
            root.dispatchEvent(new CustomEvent('focustrap:escape'));
            return;
          }
          if (e.key !== 'Tab') return;

          var items = getFocusable(root);
          if (items.length === 0) return;
          var first = items[0];
          var last  = items[items.length - 1];

          if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
          } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
          }
        };
        document.addEventListener('keydown', keydownHandler);
      },
      deactivate: function () {
        if (keydownHandler) {
          document.removeEventListener('keydown', keydownHandler);
          keydownHandler = null;
        }
        if (savedFocus && typeof savedFocus.focus === 'function') {
          savedFocus.focus();
        }
      },
    };
  }

  window.focusTrap = { attach: attach };
})();
