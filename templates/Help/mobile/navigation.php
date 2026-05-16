<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Bottom-tab navigation — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How the mobile navigation works in eQSL Card — five thumb-reachable tabs at the bottom of every page, with a More sheet for secondary destinations.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'On screens narrower than 992 px (most phones in portrait), the top navbar is replaced by a thumb-reachable bottom-tab bar with the five most-used screens. Secondary destinations live behind a More sheet.',
]) ?>

<h2>The five tabs</h2>
<p>The bottom bar is fixed to the viewport — it follows you as you scroll. Tap any tab to jump there.</p>

<dl>
  <dt>Home</dt>
  <dd>Your <a href="/dashboard">dashboard</a> — recent QSOs, recent cards, and the audit snippet.</dd>

  <dt>Logbook</dt>
  <dd>Your full QSO log at <a href="/qsos">/qsos</a>. Filter by date, callsign, band, mode.</dd>

  <dt>Quick add</dt>
  <dd>Log a new contact. Today this opens the full <a href="/qsos/new">manual entry form</a>; a dedicated stripped-down portable-entry route (<code>/qsos/quick</code>) lands in a later release. The icon has a primary accent because this is the action you'll do most often during an activation.</dd>

  <dt>Cards</dt>
  <dd>Your generated <a href="/cards">card library</a> — download, re-share, revoke.</dd>

  <dt>More</dt>
  <dd>Opens a sheet from the bottom of the screen with secondary destinations: Backgrounds, Templates, Help, every admin tool (if you're an admin), and Sign out.</dd>
</dl>

<h2>The More sheet</h2>
<p>Tapping <strong>More</strong> slides a panel up from the bottom of the screen. Tap any item to jump to it; the sheet closes automatically. Tap outside the panel (or press <kbd>Escape</kbd> on a Bluetooth keyboard) to dismiss without navigating.</p>

<p>Admin users see an extra <em>Admin</em> section with the full admin menu — Dashboard, Settings, Pending templates, Users, All cards, All backgrounds, Audit log, Callsign auto-complete, Cleanup, Run migrations.</p>

<h2>Why bottom tabs?</h2>
<p>The original navbar used a hamburger menu that collapsed to a vertical list when you tapped it. That works on a tablet but is awkward one-handed on a phone — the hamburger sits at the top of the screen, out of thumb reach on most phones, and the dropdown then anchored awkwardly inside the collapsed menu.</p>

<p>Bottom tabs put your five most-used destinations within thumb reach on a 6-inch phone and don't require an initial tap to reveal the menu. iOS Safari's home indicator and Android's gesture bar are respected via the <code>env(safe-area-inset-bottom)</code> CSS — the bar sits above them rather than colliding.</p>

<h2>Where it doesn't apply</h2>
<p>On screens 992 px wide or larger (most tablets in landscape, every desktop), the bottom-tab bar disappears entirely and the classic top navbar takes over. There's no toggle — the breakpoint is automatic based on viewport width. If you rotate your tablet from portrait to landscape, the nav style switches with it.</p>

<p>The installer wizard at <code>/install</code> deliberately doesn't show the bottom tabs either — it's a one-time setup flow and not part of the navigable app.</p>
