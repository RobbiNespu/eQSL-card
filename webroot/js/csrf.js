/**
 * Single source of truth for reading the CakePHP CSRF token in the
 * browser. Reads <meta name="csrf-token"> first, falls back to the
 * csrfToken cookie. Publishes window.eqslCsrf().
 */
(function () {
  function readCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content') || '';
    const m = document.cookie.match(/csrfToken=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
  }
  window.eqslCsrf = readCsrfToken;
})();
