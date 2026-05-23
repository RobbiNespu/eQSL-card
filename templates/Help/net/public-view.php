<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'The public live view — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="Share a read-only live roster link with listeners — the /net/{slug} public view shows check-ins and stats in real time without requiring a login.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Share a single link and anyone can watch the check-in roster update in real time — no account required.',
]) ?>

<h2>What it is</h2>
<p>Every net session has a permanent, shareable URL of the form <code>/net/{public_slug}</code>. Open it in a browser and you see a live, read-only view of the net: the roster of check-ins (callsign, name, grid, signal, role, time), the total check-in count, and the unique callsign count. The page polls for new entries automatically — listeners don't need to refresh.</p>

<p>The slug is a 16-character random string generated when the session is created. It doesn't change, so you can include it in a net announcement or QSL card before the net starts.</p>

<h2>How to find and share the link</h2>
<p>Two ways to get the link:</p>
<ul>
  <li>On the session detail page (<code>/net-sessions/{id}</code>), the Co-logger management section shows the invite link. The public link is accessible from the cockpit top bar — click the <strong>Public link</strong> button to open <code>/net/{slug}</code> in a new tab.</li>
  <li>Inside the live cockpit the top bar has a <strong>Public link</strong> button that opens the view directly.</li>
</ul>
<p>Copy the URL from your browser's address bar and share it however you like — by voice, email, or the net's club page.</p>

<h2>What viewers see</h2>
<p>The public view shows:</p>
<ul>
  <li>The net title and organisation.</li>
  <li>A live roster: callsign, operator name (if logged), Maidenhead grid square (if logged), signal strength (derived from the received RST), role, and the UTC time of check-in.</li>
  <li>A stat strip: total check-ins and unique callsigns.</li>
  <li>The net status (Live or Ended).</li>
</ul>

<h2>What viewers do NOT see</h2>
<p>The public feed deliberately excludes private information:</p>
<ul>
  <li><strong>Who logged the entry</strong> — the <code>logged_by</code> field is never included in the public feed. Whether the NCS or a co-logger added a row is not visible to outsiders.</li>
  <li><strong>Internal user IDs</strong> — no account identifiers are exposed.</li>
  <li><strong>Session notes</strong> — the owner's internal notes field is not shown.</li>
  <li><strong>The invite (logger) token</strong> — the co-logger join link is never included in the public page.</li>
</ul>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'The public feed is served by a separate controller (NetController) with a minimal allow-listed field set. Even if you tried to inject extra fields via the URL, they are not present in the response.',
]) ?>

<h2>The is_public toggle</h2>
<p>When you create or edit a session there is a <strong>Public</strong> checkbox. If this is unchecked, the <code>/net/{slug}</code> URL returns a 404 — no roster is visible, even to someone who has the correct slug. Toggle it on to enable the public view; toggle it off to hide it again at any time.</p>

<p>By default, new sessions are created with the public view enabled. Uncheck it if you are running a private or closed net.</p>

<h2>When the public view works</h2>
<p>The public view only works once the net is <strong>Live</strong> (or <strong>Ended</strong>). While the session is still in <strong>Scheduled</strong> status the <code>/net/{slug}</code> URL returns 404, even if is_public is enabled. This prevents an empty roster from being shared prematurely.</p>

<table>
  <thead><tr><th>Session status</th><th>is_public = on</th><th>is_public = off</th></tr></thead>
  <tbody>
    <tr><td>Scheduled</td><td>404</td><td>404</td></tr>
    <tr><td>Live</td><td>Shows live roster</td><td>404</td></tr>
    <tr><td>Ended</td><td>Shows final roster</td><td>404</td></tr>
  </tbody>
</table>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'Share the link before you start the net — it will 404 until you hit Start, so early visitors will just see a page-not-found. That is intentional: the roster only goes live when you go on-air.',
]) ?>
