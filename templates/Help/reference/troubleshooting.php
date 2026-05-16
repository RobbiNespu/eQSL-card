<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Troubleshooting — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="Common problems and fixes for eQSL Card — install errors, card render failures, SMTP issues, and more.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Something not working? Start here. Most problems fall into one of a handful of categories — this page covers the most common ones and how to fix them.',
]) ?>

<h2>Installation</h2>

<h3>"PHP ≥ 8.1: ✗" or missing extension</h3>
<p>Your host is running an older PHP version or a required extension is disabled. Most cPanel/DirectAdmin hosts let you choose the PHP version per directory — look for "PHP Version Manager" or "MultiPHP Manager" in the hosting panel. After switching versions, also enable the required extensions: <code>mbstring</code>, <code>intl</code>, <code>gd</code>, <code>pdo_mysql</code>, <code>openssl</code>, <code>fileinfo</code>.</p>

<h3>"webroot/files writable: ✗"</h3>
<p>The PHP user can't write to the upload directory. On shared hosting, FTP-uploaded files are typically owned by your FTP user rather than the PHP user (<code>www-data</code> or <code>nobody</code>). Fix:</p>
<pre><code>chmod 775 webroot/files webroot/files/uploads webroot/files/cards webroot/files/templates tmp logs</code></pre>
<p>If that doesn't help, check ownership: <code>ls -la webroot/files</code>. The PHP user needs to own or have group-write on the directory.</p>

<h3>Install wizard reappears after setup</h3>
<p>The wizard guards itself with <code>config/installed.lock</code> and <code>tmp/installed.lock</code>. If either file is missing or unreadable, the wizard shows again. Check that both files exist and that PHP can read them. FTP clients sometimes don't transfer empty lock files — use your host's file manager to verify.</p>

<h2>Sign-in</h2>

<h3>"Too many attempts" / rate-limited</h3>
<p>The login form rate-limits to 5 failed attempts per 15 minutes per IP. If you're locked out, wait 15 minutes. If you're testing from localhost and keep hitting the limit, ask the admin to enable <strong>Bypass rate limit for private IPs</strong> in settings — or add your dev IP to the bypass list.</p>

<h3>Forgot password / no reset email</h3>
<p>Password reset requires SMTP to be configured in <a href="/admin/settings">settings</a>. If no email arrives: (1) check the SMTP credentials are correct; (2) check your spam folder; (3) ask the admin to test SMTP from a mail client. If SMTP is entirely broken and you need emergency access, a direct SQL reset works:</p>
<pre><code>UPDATE users SET password = '{new_argon2id_hash}' WHERE email = 'you@example.com';</code></pre>
<p>Generate the hash with <code>php -r "echo password_hash('newpassword', PASSWORD_ARGON2ID);"</code>.</p>

<h2>Card rendering</h2>

<h3>"Card image missing on disk"</h3>
<p>The database row exists but the <code>webroot/files/cards/{path}</code> file was deleted externally (e.g. a host "cleanup" script wiped the directory). Delete the card from your library and re-render it.</p>

<h3>Card renders blank or shows only the background</h3>
<p>The template's field elements may be positioned outside the canvas bounds (negative X/Y or beyond width/height). Open the template in the designer, select each element, and check that all fields are within the canvas. Also verify that the template has at least one data field — a template with only free text renders correctly but produces the same text for every card.</p>

<h3>Render fails with "GD is not available"</h3>
<p>The <code>gd</code> PHP extension is not loaded. Enable it in <code>php.ini</code> (<code>extension=gd</code>) or ask your host to turn it on.</p>

<h3>Bulk render stalls at N%</h3>
<p>Each bulk render chunk is a separate HTTP request. If the server returns a 500 or times out on a chunk, the progress bar freezes. Open the browser's developer tools Network tab and look for the failing <code>POST /qsos/bulk-render-next/{token}</code> request — the response body contains the error. Common cause: a background image that's too large exceeds the PHP memory limit. Raise <code>memory_limit</code> in <code>php.ini</code> or upload a smaller background.</p>

<h2>Callsign auto-complete</h2>

<h3>Auto-complete doesn't prefill anything</h3>
<p>Check in <a href="/admin/settings">settings</a> that <strong>Enable callsign auto-complete</strong> is on and at least one provider is ticked. If it's on, open the browser's Network tab and look for the <code>GET /api/callsign/{call}</code> request — a 404 means lookup is disabled; a 204 means no provider has that callsign; a 200 with an empty result object means a provider returned empty fields.</p>

<h3>Auto-complete returns stale data</h3>
<p>Results are cached for 90 days. To force a re-lookup, go to <a href="/admin/cleanup">/admin/cleanup</a> and click <strong>Clear callsign cache</strong>. The next lookup re-queries the providers.</p>

<h2>Templates</h2>

<h3>Template thumbnail shows the wrong layout</h3>
<p>Thumbnails are generated at save time. If you edited the template after the thumbnail was generated, re-save it from the designer — saving regenerates the thumbnail.</p>

<h3>"You cannot edit this template"</h3>
<p>You're trying to edit a system template or an approved public template you don't own. Clone it first, then edit the clone.</p>

<h2>Uploads and storage</h2>

<h3>"Not a valid image"</h3>
<p>The file upload was rejected. The server validates the actual image content (not just the extension) using <code>getimagesize()</code>. Make sure the file is a genuine JPEG, PNG, or WebP — not a renamed file with the wrong contents.</p>

<h3>"Image too large"</h3>
<p>The upload was rejected because the image's pixel area exceeds 50 megapixels. Resize the image to under 4000×4000 px before uploading. The server auto-resizes to at most 2000×1500 px when saving, but it refuses to load a truly enormous source image into RAM.</p>

<h2>Getting more help</h2>
<p>If none of the above covers your problem, check the CakePHP logs under <code>logs/error.log</code> for the PHP stack trace. Then reach the site operator or open an issue on the project's git repository with the log excerpt, the PHP version, and the exact steps to reproduce.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'When reporting a bug, include: your PHP version (<code>php -v</code>), the browser and OS, and the full error message from the log file. "It doesn\'t work" is hard to diagnose; "I see \'Column not found: audit_logs.metadata\' in logs/error.log after upgrading to v1.4" is fixable in minutes.',
]) ?>
