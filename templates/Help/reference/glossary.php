<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Glossary — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="Definitions of common amateur-radio and eQSL terms used across the eQSL Card site.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Newcomer-friendly definitions of the amateur-radio + eQSL Card terms that show up across these docs. Skim the list, then jump into the guides.',
]) ?>

<dl class="row dl-stack">

  <dt class="col-sm-3">ADIF</dt>
  <dd class="col-sm-9"><strong>A</strong>mateur Data <strong>I</strong>nterchange <strong>F</strong>ormat — the de-facto plain-text standard for exchanging logbooks between amateur radio programs. eQSL Card imports <code>.adi</code> / <code>.adif</code> files via the <a href="/help/logging/import">log importer</a>.</dd>

  <dt class="col-sm-3">Band</dt>
  <dd class="col-sm-9">A range of frequencies allocated for amateur use, named by the wavelength in metres (e.g. 20m, 40m, 2m). Many logging programs derive the band from the frequency automatically.</dd>

  <dt class="col-sm-3">Callsign</dt>
  <dd class="col-sm-9">A unique licence identifier assigned by a regulator (FCC in the US, MCMC in Malaysia, etc.). Prefix-suffix format — for example <span class="callsign">W1AW</span> (United States) or <span class="callsign">9W2NSP</span> (Malaysia).</dd>

  <dt class="col-sm-3">DX</dt>
  <dd class="col-sm-9">Slang for long-distance, usually intercontinental. A "DX contact" is a QSO with a far-away station. Operators chasing DX often collect QSLs as proof of contact.</dd>

  <dt class="col-sm-3">eQSL</dt>
  <dd class="col-sm-9">An <strong>e</strong>lectronic <strong>QSL</strong> card — the digital counterpart to the classic paper QSL. Same purpose (confirming a contact), no postage. eQSL Card generates them as image + optional PDF; share via link, email, or chat.</dd>

  <dt class="col-sm-3">Grid square</dt>
  <dd class="col-sm-9">Also called a <em>Maidenhead locator</em> — a compact way to encode a geographic location for amateur radio. Four characters give ~1° resolution (e.g. <code>OJ02</code>); six characters refine it further (<code>OJ02wx</code>). Some logging programs and contests award points based on the number of unique grids worked.</dd>

  <dt class="col-sm-3">Mode</dt>
  <dd class="col-sm-9">The signal type used for the QSO. Common ones: <strong>CW</strong> (Morse code), <strong>SSB</strong> (single-sideband voice), <strong>FM</strong> (frequency-modulated voice, usual on VHF/UHF repeaters), <strong>FT8</strong> / <strong>FT4</strong> (modern weak-signal digital modes), <strong>RTTY</strong> (radio teletype), <strong>PSK31</strong> (a phase-shift-keyed digital mode).</dd>

  <dt class="col-sm-3">NCS</dt>
  <dd class="col-sm-9"><strong>N</strong>et <strong>C</strong>ontrol <strong>S</strong>tation — the operator who runs a net, takes check-ins, and coordinates traffic. In eQSL Card, an NCS uses the "Net check-in" QSO type to issue confirmation cards to each participant.</dd>

  <dt class="col-sm-3">Net</dt>
  <dd class="col-sm-9">A scheduled on-air meeting, usually on the same frequency at the same time each week. Participants "check in" to the NCS by callsign; the NCS records the list. Common formats: traffic nets, emergency nets, technical nets, social nets.</dd>

  <dt class="col-sm-3">QSL card</dt>
  <dd class="col-sm-9">A confirmation of a radio contact, traditionally a postcard-sized print mailed via the postal service or a QSL bureau. eQSL Card generates the electronic version.</dd>

  <dt class="col-sm-3">QSO</dt>
  <dd class="col-sm-9">A two-way radio contact. From the international "Q-signal" <em>QSO</em> meaning "I can communicate with...". A logbook entry corresponds to exactly one QSO.</dd>

  <dt class="col-sm-3">QTH</dt>
  <dd class="col-sm-9">From the Q-signal meaning "my location is...". Used as a noun: "What's your QTH?" — "Kuala Lumpur." eQSL Card stores it as a free-text field on each QSO.</dd>

  <dt class="col-sm-3">RST report</dt>
  <dd class="col-sm-9">A three-digit signal-quality report — <strong>R</strong>eadability (1–5), <strong>S</strong>trength (1–9), <strong>T</strong>one (1–9, CW only). A "599" is a perfect signal report; "59" on voice (no tone digit) likewise. Each station exchanges its own RST during the QSO.</dd>

  <dt class="col-sm-3">Transport</dt>
  <dd class="col-sm-9">In eQSL Card's QSO form, the medium the contact used. Default is <strong>RF (over the air)</strong>; alternatives include internet-mediated systems like <strong>Echolink</strong>, <strong>Zello</strong>, <strong>Mumble</strong>, <strong>TeamSpeak</strong>, and <strong>Discord</strong>. Picking a non-RF transport hides frequency/band as required fields and surfaces a free-text channel/node/server field instead.</dd>

  <dt class="col-sm-3">UTC</dt>
  <dd class="col-sm-9"><strong>C</strong>oordinated <strong>U</strong>niversal <strong>T</strong>ime — the global reference time, equivalent to GMT for amateur purposes. Always log QSOs in UTC, not local time, so logs from operators in different time zones line up.</dd>

</dl>
