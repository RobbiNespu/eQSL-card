<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Share a card publicly — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="Create a public share link for an eQSL card, optionally protected with a password.">
<?php $this->end(); ?>
<?php $this->set('useMermaid', true); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Generate a public link to a rendered card. Optionally password-protect it. Revoke it whenever you want.',
]) ?>

<h2>The flow</h2>

<pre class="mermaid">
sequenceDiagram
  participant U as Operator
  participant App as eQSL Card
  participant V as Recipient
  U->>App: Click "Share"
  App->>U: Public link + optional password
  U->>V: Sends link
  V->>App: GET /qsl/{slug}
  alt password set
    App->>V: Password prompt
    V->>App: Submits password
    App->>V: 200 + card
  else no password
    App->>V: 200 + card
  end
</pre>

<h2>Creating a share link</h2>
<p>Open any card from your <a href="/cards">library</a>. The right column has a <strong>Sharing</strong> block. If the card isn't currently shared, you'll see a small password field (optional) and a <strong>Share</strong> button.</p>

<p>Click <strong>Share</strong> with the password field empty for a no-auth link — anyone who has the URL can view the card. Fill in a password before clicking to require it. The password is hashed with Argon2id at rest; the plain text never persists.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/cards/share/share-toggle.webp',
    'alt' => 'The Sharing block showing the password field and Share button',
    'caption' => 'Generate the link. Password field is optional.',
]) ?>

<h2>The public URL</h2>
<p>Successful sharing replaces the form with the public link in a copy-friendly code block:</p>
<pre><code>https://your-domain.example/qsl/abc-def-123</code></pre>
<p>The slug is a non-guessable random token — there's no enumeration risk. Send the link via whatever channel makes sense (email, chat, forum post, QR code printed on a paper QSL). The recipient doesn't need to sign up for anything.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/cards/share/public-view.webp',
    'alt' => 'A recipient viewing the shared card — the full image plus download buttons and QSO metadata',
    'caption' => 'What the recipient sees at the share URL.',
]) ?>

<h2>Revoking a share</h2>
<p>From the same card's view, click <strong>Revoke share</strong>. The slug stops resolving — anyone hitting the old URL gets a 410 Gone status code (which tells search engines "this is permanently gone" rather than "try again later"). The card itself stays in your library — only its public link is killed.</p>

<?= $this->element('ui/callout', [
    'variant' => 'warning',
    'body' => 'Revoking is permanent for that slug. If you re-share the same card later, you get a new slug — anyone who saved the old link will no longer see anything. Plan accordingly if the share is mass-distributed.',
]) ?>

<h2>Password protection</h2>
<p>Passwords are most useful for private events (closed-net check-in confirmations, club-only certificates). Recipients see a password prompt before the card; correct password sets a per-slug session flag and lands them on the card. The flag is session-scoped — closing the browser and returning re-prompts.</p>
