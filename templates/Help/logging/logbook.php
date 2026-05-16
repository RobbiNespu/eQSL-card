<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Browse your logbook — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How the logbook listing works at /qsos — filters, bulk render, and the mobile card view that replaces the table on phones.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'The logbook at /qsos shows every QSO you have logged. Filter, paginate, and bulk-render eQSL cards from here.',
]) ?>

<h2>Layout — desktop</h2>
<p>On screens 768 px wide or larger (most tablets in landscape, every desktop), the logbook renders as a classic table:</p>
<ul>
  <li><strong>Bulk select</strong> — a checkbox column on the left lets you pick rows to bulk-render.</li>
  <li><strong>Callsign</strong> — with badges showing the QSO type (Contact / Net check-in) and transport (RF / Echolink / Mumble / etc.).</li>
  <li><strong>Date/Time UTC</strong>, <strong>Freq</strong>, <strong>Band</strong>, <strong>Mode</strong>, <strong>RST sent / received</strong> — one column each.</li>
  <li><strong>Actions</strong> — View / Render (or View card if one already exists).</li>
</ul>

<h2>Layout — mobile (&lt; 768 px)</h2>
<p>Nine columns don't fit on a 375 px phone. Instead of forcing a horizontal scroll, the listing collapses each QSO row into a self-contained card (M5 T5, 2026-05-16):</p>
<ul>
  <li><strong>Callsign</strong> is the card heading, large and bold, with the type / transport badges next to it.</li>
  <li>Each remaining field becomes a labelled row inside the card — Date/Time UTC, Freq, Band, Mode, RST — with the label on the left and the value right-aligned.</li>
  <li>The bulk-select checkbox sits in the top-right corner of the card so it's still one tap to add to a batch.</li>
  <li>Action buttons (View / Render / View card) become a full-width row at the bottom of the card with side-by-side equal-width buttons — bigger tap targets, easier thumb reach.</li>
</ul>

<p>No data is hidden behind a tap-to-expand on the cards — every field is visible. The trade-off is vertical scroll instead of horizontal; mobile users overwhelmingly prefer that, and the audit decision was deliberate: hiding fields behind expand controls would create a discoverability problem during fast portable ops.</p>

<h2>Filters</h2>
<p>The filter row at the top of the page accepts search by callsign, QSO type (Contact / Net), transport (RF / Echolink / etc.), band, mode, and a date range. Filters combine via AND — picking <em>Band: 40m</em> and <em>Mode: SSB</em> shows only QSOs matching both.</p>
<p>Filters survive pagination — clicking page 2 keeps your filter URL parameters. To reset, hit the page name in the navbar (or just click the eQSL Card brand to reload the unfiltered view).</p>

<h2>Bulk render</h2>
<p>Check the boxes next to the QSOs you want, then click <strong>Bulk render selected</strong>. A modal opens asking which template to use — the background is part of the template, so every card in the batch shares the same look. The render runs in the browser with a progress bar, and any QSO that already has a rendered card is skipped (delete the existing card from <a href="/cards">your library</a> first to re-render).</p>

<h2>Sorting</h2>
<p>The list defaults to newest-first by <code>qso_datetime_utc</code>. Column-header click-to-sort is not yet implemented — for now, use the date range filter to narrow.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'Bulk render is the fastest way to ship a whole net or activation\'s cards. Filter by date or net title (search the callsign field with the participant\'s call), select all, render — typical batch of 30 cards takes about a minute on a mid-range laptop.',
]) ?>
