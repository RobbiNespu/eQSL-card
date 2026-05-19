<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Mobile & portable ops — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="The mobile & portable-ops section of the eQSL Card help portal — bottom-tab navigation, quick-add for one-thumb logging, activations, PWA install, offline operation, dupe-check, voice input.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'eQSL Card ships a mobile-first portable-ops surface — one-thumb logging, offline-tolerant sync, activation grouping for POTA / SOTA / field days. This section of the help portal collects every article relevant to operating from the field.',
]) ?>

<h2>Articles in this section</h2>

<dl>
  <dt><a href="/help/mobile/navigation">Bottom-tab navigation</a></dt>
  <dd>On screens narrower than 992 px the top navbar collapses to a thumb-reachable five-tab bar with a More sheet for everything else.</dd>

  <dt><a href="/help/mobile/quick-add">Quick-add for portable ops</a></dt>
  <dd>The <code>/qsos/quick</code> route — five fields, last-5 panel, sticky Save button, save-and-stay loop. The single most-used screen during an activation.</dd>

  <dt><a href="/help/mobile/activations">Activations (POTA, SOTA, field day)</a></dt>
  <dd>Named portable sessions that auto-tag the QSOs logged inside them, with GPS auto-fill for the grid square and a per-activation ADIF export endpoint.</dd>

  <dt><a href="/help/mobile/install-pwa">Install as an app (PWA)</a></dt>
  <dd>"Add to Home Screen" on iOS Safari and Chrome / Edge / Brave on Android. Launches in a standalone window, full-screen, faster cold-start than a browser tab.</dd>

  <dt><a href="/help/mobile/offline">Logging offline</a></dt>
  <dd>The IndexedDB-backed offline queue, the sync engine, the status pill, and the conflict-resolution rule when the same QSO ends up in the queue twice.</dd>

  <dt><a href="/help/mobile/dupe-checking">Dupe-check traffic-light badge</a></dt>
  <dd>The four-state callsign-dupe indicator under the quick-add input, the underlying API, and the opt-in preference that turns the red state into a hard save block.</dd>

  <dt><a href="/help/mobile/voice-input">Voice input on the callsign field</a></dt>
  <dd>An opt-in microphone button that decodes NATO phonetic ("nine mike two romeo delta x-ray" → <code>9M2RDX</code>) into the callsign input via the Web Speech API.</dd>
</dl>

<h2>Suggested reading order</h2>
<p>If you're setting up for your first activation, the fastest path through the section is:</p>
<ol>
  <li><a href="/help/mobile/install-pwa">Install as an app</a> — so the icon is on your home screen.</li>
  <li><a href="/help/mobile/quick-add">Quick-add</a> — the form you'll spend the day in.</li>
  <li><a href="/help/mobile/activations">Activations</a> — start one when you arrive at the site.</li>
  <li><a href="/help/mobile/offline">Offline logging</a> — if the site has no cell signal, this is what catches the QSOs.</li>
  <li><a href="/help/mobile/dupe-checking">Dupe-check</a> — once a few contacts are logged, the badge starts being useful.</li>
</ol>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'Mobile articles are written for one-thumb reading. Screenshots are sized for portrait phones; every code example fits a 320 px viewport without horizontal scroll. If you find one that doesn\'t, file a bug — the section is supposed to be readable on the same hardware it documents.',
]) ?>
