<?php $this->extend('/Help/view'); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Set up your station profile so eQSL Card can prefill QSO fields and put your callsign on the cards you generate.',
]) ?>

<h2>What you'll need</h2>
<ul>
  <li>An <strong>email address</strong> — used for sign-in and password resets.</li>
  <li>Your <strong>callsign</strong> — appears on every eQSL card you generate.</li>
  <li>A <strong>password</strong> — at least 8 characters; longer passphrases are stronger.</li>
</ul>

<h2>The registration form</h2>
<p>Visit <a href="/register">/register</a> and fill in the four fields. Required fields are marked with a red asterisk; helper text under each field explains what it's for and how it'll be used.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/getting-started/create-account/register-form.webp',
    'alt' => 'Empty registration form showing name, callsign, email, and password fields',
    'caption' => 'The registration form — required fields marked with an asterisk.',
]) ?>

<h2>After registering</h2>
<p>If email verification is enabled on this site, you'll receive a link to confirm your address before you can sign in. Otherwise the system signs you in immediately and lands you on your dashboard.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'Pick a passphrase rather than a complex password — "horse-rocket-battery-staple" is both easier to remember and harder to crack than "P@ssw0rd!".',
]) ?>

<h2>Next up</h2>
<p><a href="/help/getting-started/first-card">Your first eQSL card →</a> walks the 5-minute path from sign-up to download.</p>
