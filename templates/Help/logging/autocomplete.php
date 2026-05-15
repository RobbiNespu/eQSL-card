<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Callsign auto-complete — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How callsign auto-complete works in eQSL Card — providers, caching, and how admins enable it.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Type a callsign into the QSO form and eQSL Card will try to prefill the operator\'s name, QTH, and grid square automatically.',
]) ?>

<h2>How it works</h2>
<p>When you type in the <strong>Their callsign</strong> field and pause, the form sends a request to <code>/api/callsign/{call}</code>. The server walks down an ordered list of providers until one returns a hit, then prefills <strong>Their name</strong>, <strong>QTH</strong>, and <strong>Grid square</strong>. The result is stored in the <code>callsign_lookups</code> table for 90 days, so the same callsign on a future QSO returns instantly without another network call.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/logging/autocomplete/prefill.webp',
    'alt' => 'QSO form with the name, QTH, and grid fields pre-filled after typing a callsign',
    'caption' => 'Auto-complete prefills the operator\'s details when a provider has a match.',
]) ?>

<h2>Available providers</h2>
<p>The admin can enable any combination of providers (in priority order):</p>
<dl class="row dl-stack">
  <dt class="col-sm-3">Local directory</dt>
  <dd class="col-sm-9">A callsign CSV the admin uploads under <a href="/admin/callsign-directory">/admin/callsign-directory</a>. Always checked first — no network call, instant lookup. Best for common contacts in your local club or net.</dd>

  <dt class="col-sm-3">MCMC</dt>
  <dd class="col-sm-9">Malaysian Communications and Multimedia Commission licencee database. Covers all current <span class="callsign">9W</span> and <span class="callsign">9M</span> callsigns. Network lookup.</dd>

  <dt class="col-sm-3">MARTS</dt>
  <dd class="col-sm-9">Malaysian Amateur Radio Transmitters' Society member list. Supplements MCMC for club-affiliated operators. Network lookup.</dd>

  <dt class="col-sm-3">RadioID</dt>
  <dd class="col-sm-9">Worldwide DMR/Fusion database. Useful for digital-mode contacts where RadioID registrations are common. Network lookup.</dd>

  <dt class="col-sm-3">RAPI</dt>
  <dd class="col-sm-9">Regional amateur operator index. Network lookup.</dd>

  <dt class="col-sm-3">QRZ</dt>
  <dd class="col-sm-9">QRZ.com. Requires a QRZ XML subscription key configured in settings. Network lookup.</dd>
</dl>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'Auto-complete only runs when the feature is enabled by the site admin. If you don\'t see prefilling, the admin may have it turned off or no providers are configured. Reach the operator from the homepage.',
]) ?>

<h2>Provider priority and the 90-day cache</h2>
<p>Providers are checked in the order set by the admin. The first hit wins — subsequent providers in the chain are not queried. The winner's data lands in <code>callsign_lookups</code> tagged with the source name. On the next lookup within 90 days, the cache row is returned immediately and <em>no</em> provider is called.</p>
<p>If the cached data is stale (e.g. the operator moved QTH), an admin can wipe the cache from <a href="/admin/cleanup">/admin/cleanup</a> → <em>Clear callsign cache</em>. The next lookup re-queries the providers.</p>

<h2>What happens when there's no match</h2>
<p>A 204 (No Content) response means no provider has data for that callsign. The form fields stay empty and you fill them in manually. The miss is not cached — the next QSO with the same callsign tries the providers again, in case a registration has appeared in the meantime.</p>

<h2>For admins — enabling and configuring</h2>
<p>Go to <a href="/admin/settings">/admin/settings</a> and find the <strong>Callsign auto-complete</strong> section:</p>
<ol>
  <li>Check <strong>Enable callsign auto-complete</strong>.</li>
  <li>Tick the providers you want to use and drag them into priority order.</li>
  <li>If you're enabling QRZ, paste your QRZ XML API key into the field that appears.</li>
  <li>Save.</li>
</ol>
<p>To upload a local CSV directory, see <a href="/help/admin/callsign-dir">Callsign directory CSV upload</a>.</p>
