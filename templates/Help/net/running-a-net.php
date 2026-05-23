<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Running a net — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How to create a net session, move it through the scheduled → live → ended lifecycle, and use the live cockpit to log check-ins quickly.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'From creating a session to closing the net — a step-by-step walkthrough of the NCS dashboard cockpit.',
]) ?>

<h2>Creating a net session</h2>
<p>Go to <a href="/net-sessions">/net-sessions</a> and click <strong>New net session</strong>. Fill in the session details:</p>

<table>
  <thead><tr><th>Field</th><th>Notes</th></tr></thead>
  <tbody>
    <tr><td><strong>Net title</strong></td><td>The name of the net (e.g. "MARTS Daily Net"). Used as the heading in the cockpit and exported ADIF.</td></tr>
    <tr><td><strong>Organisation</strong></td><td>Optional. The sponsoring club or network (e.g. "MARTS").</td></tr>
    <tr><td><strong>Frequency (MHz)</strong></td><td>The primary operating frequency. Auto-derives the band.</td></tr>
    <tr><td><strong>Band</strong></td><td>Pre-filled from frequency; can be overridden if needed.</td></tr>
    <tr><td><strong>Mode</strong></td><td>The emission mode (FM, SSB, CW, etc.).</td></tr>
    <tr><td><strong>Public</strong></td><td>Toggle whether the public live view is accessible. See <a href="/help/net/public-view">The public live view</a>.</td></tr>
    <tr><td><strong>Notes</strong></td><td>Internal notes — not shown on the public view.</td></tr>
  </tbody>
</table>

<p>Saving creates the session in <strong>Scheduled</strong> status. You can edit the details or add co-loggers before starting.</p>

<h2>The lifecycle: scheduled → live → ended</h2>

<p>Every net session moves through three states in order:</p>

<ol>
  <li><strong>Scheduled</strong> — the session exists but the net has not started. The cockpit is not accessible yet. You can edit session details and add co-loggers.</li>
  <li><strong>Live</strong> — the net is on-air. The cockpit is open; the NCS and any co-loggers can log check-ins. The public view (if enabled) shows the live roster.</li>
  <li><strong>Ended</strong> — the net is closed. No new check-ins can be added. Analytics, ADIF export, and the PDF report become available.</li>
</ol>

<p>Transitions happen via two buttons on the session view page (<code>/net-sessions/{id}</code>):</p>

<ul>
  <li><strong>Start net</strong> — moves the session from Scheduled to Live and redirects you straight to the cockpit. The <code>started_at</code> timestamp is recorded at this moment.</li>
  <li><strong>End net</strong> — moves the session from Live to Ended and redirects back to the session detail view. The <code>ended_at</code> timestamp is recorded. This button is also available inside the cockpit itself so you don't have to leave to close the net.</li>
</ul>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'Once a net is ended it cannot be re-opened. If you need to add a missed check-in after closing, contact your site administrator, or use the standard QSO log form to add it manually.',
]) ?>

<h2>The live cockpit</h2>
<p>The cockpit at <code>/net-sessions/{id}/cockpit</code> is the main working screen during a net. It has three areas:</p>

<ul>
  <li><strong>Top bar</strong> — the net title, organisation, frequency, band, mode, elapsed time since start, a Public link button, and the End net button.</li>
  <li><strong>Entry bar</strong> — the fast check-in form (described below).</li>
  <li><strong>Roster</strong> — the live list of everyone who has checked in, newest at the top, updating automatically as entries arrive.</li>
  <li><strong>Stat tiles</strong> — live counts of total check-ins and unique callsigns, visible on the right side of the screen.</li>
</ul>

<h2>The fast entry bar</h2>
<p>The entry bar is designed for speed. It has five fields:</p>

<ol>
  <li><strong>Callsign</strong> — the only required field. Type the station's callsign. All-caps, the cursor starts here on every page load.</li>
  <li><strong>Name</strong> — the operator's name. Optional; useful for generating the PDF report.</li>
  <li><strong>Grid</strong> — the Maidenhead grid square (e.g. <code>OJ02</code>). Used for the participant map on the analytics page.</li>
  <li><strong>RST</strong> — the received signal report from the checking-in station (e.g. <code>59</code>). Defaults to 59. Used for the signal distribution chart.</li>
  <li><strong>Role</strong> — select: NCS, Relay, Check-in (default), or Traffic.</li>
</ol>

<p>Click <strong>+ Log</strong> (or press Enter). The check-in is saved immediately and appears at the top of the roster without a page reload. The callsign, name, and grid fields clear; RST and role keep their last value so the next entry needs only the callsign typed.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'During a fast net, the loop is: type callsign → Tab to name if needed → press Enter. RST defaults to 59 and role defaults to Check-in, so most check-ins require only one field.',
]) ?>

<h2>Editing and removing check-ins</h2>
<p>Any check-in in the roster can be edited inline — click the row to expand it, change the fields, and save. Co-loggers can edit check-ins they logged, and the owner can edit any check-in. If you logged a wrong callsign, edit the row rather than deleting and re-adding — the roster will update in place across all connected screens.</p>

<p>To remove a check-in entirely, use the delete control on the expanded row. Deletions are reflected immediately on the cockpit and public view.</p>

<h2>After the net ends</h2>
<p>Once you click End net the cockpit shows the roster in read-only mode with a note that the net has ended. From the session detail view you can then:</p>
<ul>
  <li>Open <strong>Analytics</strong> to review the signal distribution, participant map, and retention data.</li>
  <li>Download an <strong>ADIF export</strong> for LoTW upload.</li>
  <li>Download the <strong>PDF net report</strong> with the full roster and stats.</li>
</ul>
<p>See <a href="/help/net/analytics-and-exports">Analytics &amp; exports</a> for details.</p>
