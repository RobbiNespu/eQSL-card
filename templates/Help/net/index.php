<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Net control dashboard — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="Overview of the eQSL Card NCS dashboard — create and run amateur radio nets, log check-ins live, share a public view, and export ADIF or PDF reports.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'eQSL Card includes a built-in net control station (NCS) dashboard. Create a net session, start it when you go on-air, log check-ins from the live cockpit, and export the roster when you are done.',
]) ?>

<h2>Who this is for</h2>
<p>The NCS dashboard is for the operator who <em>runs</em> the net — the net control station. If you are a station checking in to someone else's net, your check-in is logged by the NCS and appears in your logbook automatically; you don't need to use this section. This section is only relevant if you are the person calling the net and logging participants.</p>

<h2>Articles in this section</h2>

<dl>
  <dt><a href="/help/net/running-a-net">Running a net</a></dt>
  <dd>Creating a net session, the scheduled → live → ended lifecycle, the live cockpit, the fast entry bar (callsign / name / grid / RST / role), and the save-and-stay loop.</dd>

  <dt><a href="/help/net/collaborative-logging">Collaborative logging</a></dt>
  <dd>Adding co-loggers so a second operator can share check-in duty. How the owner adds a co-logger directly, how the per-session invite link works, and how everyone's entries merge live in the roster.</dd>

  <dt><a href="/help/net/public-view">The public live view</a></dt>
  <dd>The shareable read-only link (<code>/net/{slug}</code>) that lets listeners watch the roster update in real time without logging in. The <code>is_public</code> toggle and what information is visible.</dd>

  <dt><a href="/help/net/analytics-and-exports">Analytics &amp; exports</a></dt>
  <dd>The post-net analytics page — signal distribution, participant map, retention metrics. ADIF export for LoTW / portal upload and the PDF net report.</dd>
</dl>

<h2>Suggested reading order</h2>
<p>If you are running a net for the first time, read the articles in this order:</p>
<ol>
  <li><a href="/help/net/running-a-net">Running a net</a> — start here to understand the lifecycle and the cockpit.</li>
  <li><a href="/help/net/collaborative-logging">Collaborative logging</a> — if you have a second operator helping log, set this up before you go live.</li>
  <li><a href="/help/net/public-view">The public live view</a> — share the link with listeners before you start.</li>
  <li><a href="/help/net/analytics-and-exports">Analytics &amp; exports</a> — review after the net ends; export ADIF for LoTW.</li>
</ol>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'The dashboard is at /net-sessions. Bookmark it — you will visit it at the start of every net to hit Start and open the cockpit.',
]) ?>
