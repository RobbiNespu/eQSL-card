<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'About + credits — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="About the eQSL Card project — what it is, who built it, the open-source pieces it stands on, and how to contribute.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'A free, self-hosted workbench for amateur radio operators who want to generate, share, and archive electronic QSL cards.',
]) ?>

<h2>What this project is</h2>
<p><strong>eQSL Card</strong> is an open-source web application for logging QSOs and turning them into shareable, downloadable electronic QSL cards. It runs on any modest PHP host — no Node runtime, no exotic dependencies, no SaaS lock-in. One operator can install it on a $5 shared hosting plan and confirm contacts for life.</p>

<p>The project was started because existing eQSL services either required paid accounts, locked your data behind closed APIs, or wouldn't let you customise the card design beyond a small set of templates. Owning the rendering pipeline means owning the cards: they live on your host, in your storage, with your typography and your background images.</p>

<h2>Built by</h2>
<p>Maintained by <a href="https://robbi.my" rel="noopener"><strong>Robbi Nespu</strong></a> — callsign <span class="callsign">9W2NSP</span>, Malaysia.</p>
<p>Source code: <a href="https://github.com/RobbiNespu/eQSL-card" rel="noopener"><code>github.com/RobbiNespu/eQSL-card</code></a></p>
<p>Pull requests, bug reports, and documentation fixes from other operators are welcome.</p>

<h2>Open-source pieces it stands on</h2>
<p>The app would not exist without the following projects. Each is used under its original licence:</p>
<dl class="row dl-stack">
  <dt class="col-sm-3">CakePHP 5</dt>
  <dd class="col-sm-9">The PHP web framework. Routing, ORM, migrations, view layer. MIT licence.</dd>

  <dt class="col-sm-3">Tailwind CSS 3</dt>
  <dd class="col-sm-9">Utility-first CSS — provides the spacing, flex, and grid utilities at the heart of the layout. MIT licence.</dd>

  <dt class="col-sm-3">DaisyUI 4</dt>
  <dd class="col-sm-9">Tailwind component library — buttons, cards, alerts, badges, dropdowns. Themed to a shadcn-inspired look. MIT licence.</dd>

  <dt class="col-sm-3">Alpine.js 3</dt>
  <dd class="col-sm-9">Lightweight reactive sprinkles for the QSO form, the template designer, and the dark-mode toggle. MIT licence.</dd>

  <dt class="col-sm-3">Fabric.js 6</dt>
  <dd class="col-sm-9">Canvas library powering the visual template designer. MIT licence.</dd>

  <dt class="col-sm-3">PHP-GD + FPDF</dt>
  <dd class="col-sm-9">Server-side image compositing and PDF wrapping for the actual card render. PHP-GD ships with PHP; FPDF is in the public domain.</dd>

  <dt class="col-sm-3">Inter + Geist Mono</dt>
  <dd class="col-sm-9">The two variable web fonts that carry the whole UI. Both SIL Open Font Licence; served via Fontsource on jsdelivr.</dd>

  <dt class="col-sm-3">Mermaid</dt>
  <dd class="col-sm-9">Browser-side diagram rendering for the flowcharts in these help pages. MIT licence.</dd>

  <dt class="col-sm-3">Argon2id</dt>
  <dd class="col-sm-9">Modern password hashing for user accounts. Built into PHP's <code>password_hash()</code>.</dd>
</dl>

<h2>Inspirations + acknowledgements</h2>
<ul>
  <li><strong>RoIPMARS</strong> (Malaysian Amateur Radio over Internet Protocol Society) and the wider asian radio over IP community for the amateur radio scene was always conducting local and international net check-in events</li>
  <li><strong>9W2LGX</strong> — Who is the one of the main person behind ROIPMARS which asking me to develop the application but I dont have much time that time.</li>
</ul>

<h2>Licence</h2>
<p>The application source is released under the <strong>MIT licence</strong> — use it, modify it, host it yourself, sell services on top of it; just keep the copyright notice. The bundled icons and fonts retain their own licences listed above. Background images uploaded by operators stay theirs — eQSL Card never claims copyright on user-supplied content.</p>

<h2>How to contribute</h2>
<p>Found a bug? Have an idea for a feature? Spot a typo in the documentation? Open an issue or send a pull request on the project's git repository at <a href="https://github.com/RobbiNespu/eQSL-card" rel="noopener"><code>github.com/RobbiNespu/eQSL-card</code></a>. Contributions of all sizes are welcome — including doc fixes, new help articles, and translated templates for non-English locales.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => "If you're an operator who'd like to improve these guides — the missing articles are listed in the sidebar with a 'coming soon' note. Submit a PR with the prose and a couple of screenshots and your callsign goes on the contributor list.",
]) ?>

<h2>Contact</h2>
<p>Questions, suggestions, or just want to say hello? Reach the maintainer via the contact details on <a href="https://robbi.my" rel="noopener">robbi.my</a>.</p>

<p class="form-text mt-5">73 de <span class="callsign">9W2NSP</span> — keep the bands warm.</p>
