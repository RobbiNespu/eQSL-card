<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Install as an app (PWA) — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="Install eQSL Card to your home screen so it launches like a native app — full-screen, no browser chrome, fast cold-start. Required for offline use in a later release.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Add eQSL Card to your home screen and it launches in its own window — no browser address bar, no tab strip. Faster cold-start, more screen real estate for the form, and the icon sits next to your other apps.',
]) ?>

<h2>Why install</h2>
<ul>
  <li><strong>One-tap launch</strong> — no fumbling with the browser tab list.</li>
  <li><strong>Full-screen</strong> — the bottom-tab nav sits at the bottom of the screen, not above the browser's URL bar.</li>
  <li><strong>Standalone window</strong> — feels like a native app; you can switch to and from it via the OS task switcher.</li>
  <li><strong>Static asset caching</strong> — the app shell loads instantly on every visit after the first, even on slow networks.</li>
  <li><strong>Offline-ready</strong> (coming in a later release) — log QSOs without a cell signal; they sync when you reconnect.</li>
</ul>

<h2>Install on Android (Chrome / Edge / Brave)</h2>
<ol>
  <li>Open the app in the browser.</li>
  <li>Tap the browser menu (⋮ top-right).</li>
  <li>Look for <strong>Install app</strong> (Chrome) or <strong>Add to Home screen</strong> (Edge / Brave). Some browsers also pop up a banner at the bottom of the page on first visit.</li>
  <li>Confirm the install prompt. The app icon appears on your home screen.</li>
  <li>Tap the icon to launch — opens in standalone mode, no browser chrome.</li>
</ol>

<h2>Install on iOS (Safari)</h2>
<ol>
  <li>Open the app in Safari (not Chrome — iOS only allows PWA install from Safari).</li>
  <li>Tap the <strong>Share</strong> button (square with up-arrow at the bottom).</li>
  <li>Scroll down and tap <strong>Add to Home Screen</strong>.</li>
  <li>Confirm the name (it'll default to "eQSL"). Tap <strong>Add</strong>.</li>
  <li>Tap the icon on your home screen — launches in standalone mode.</li>
</ol>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'Install only works over HTTPS in production, or on localhost during development. If you\'re on an HTTP-only deployment the install option won\'t appear — switch to HTTPS (any free Let\'s Encrypt cert works).',
]) ?>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'Subfolder deploys (e.g. example.com/qsl) are supported. The manifest URLs and service-worker scope auto-adjust to the deploy base path — every "Install" / "Add to Home Screen" pointer goes to /qsl/qsos/quick, the SW only intercepts requests under /qsl/, and other apps on the same host stay untouched.',
]) ?>

<h2>What gets cached</h2>
<p>A service worker caches three categories of resources differently:</p>

<ul>
  <li><strong>Cache-first</strong> — long-lived static assets (CSS, JS, fonts, icons, help screenshots, sample CSV files). Loads from cache instantly; only re-fetches when the URL changes. The app's CSS file URL has a <code>?v=&lt;mtime&gt;</code> suffix so a deployed update fetches the new version automatically.</li>
  <li><strong>Network-first</strong> — HTML pages and JSON APIs (dashboard, logbook, quick-add). Always tries the network; falls back to a cached copy if offline. The cached version stays warm for offline browsing.</li>
  <li><strong>Network-only</strong> — admin pages, login, password reset, installer. Never cached. These need to be live so a stale session doesn't show and so admin work is never served from a frozen snapshot.</li>
</ul>

<h2>Updating after a release</h2>
<p>When the maintainer deploys a new version, the service worker checks <code>sw.js</code> for changes on the next page load. If the cache version bumped, the old shell gets swept and the new one activates immediately. No "tap reload to update" prompt needed.</p>

<p>If something looks stuck on an old version (e.g. you see UI from a previous release after a known deploy), force-refresh: Android Chrome <em>Settings → Site settings → Storage → Clear</em>, or iOS Safari <em>Settings → Safari → Clear History and Website Data</em>. The PWA installs a fresh worker on the next launch.</p>

<h2>What's NOT cached (and why)</h2>
<ul>
  <li><strong>User-uploaded backgrounds</strong> (<code>/files/uploads/</code>) — they change per user; caching would leak across accounts in a shared-device scenario.</li>
  <li><strong>Generated cards</strong> (<code>/files/cards/</code>) — same reason, plus they're huge.</li>
  <li><strong>Auth + admin pages</strong> — must always be live.</li>
</ul>

<h2>What ships next</h2>
<p>The PWA basics (install + asset caching) land first. Offline-first logging follows in a later release:</p>
<ul>
  <li><strong>T20 / T21</strong> — IndexedDB-backed queue for quick-add POSTs. When you tap Log contact with no cell signal, the QSO stashes in your browser's local DB.</li>
  <li><strong>T22 / T23</strong> — Sync engine + status pill. When you reconnect, queued QSOs upload chronologically; a status pill at the top shows pending count and lets you retry/delete per row.</li>
</ul>
