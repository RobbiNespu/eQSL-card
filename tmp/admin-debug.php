<?php
/**
 * ONE-SHOT debug script — runs THREE auth checks against the same
 * email + password to identify exactly where login is failing.
 *
 * Upload to: webroot/admin-debug.php on the server.
 * Visit:      https://tools.robbi.my/qsl/admin-debug.php
 * Delete after use.
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

chdir(__DIR__);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

use Cake\ORM\TableRegistry;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\AuthenticationService;
use Authentication\Identifier\PasswordIdentifier;

$users = TableRegistry::getTableLocator()->get('Users');

$action = $_POST['action'] ?? 'show';
$message = null;
$verifyResult = null;
$identifierResult = null;
$authServiceResult = null;

if ($action === 'reset') {
    $email = $_POST['email'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $user = $users->find()->where(['email' => $email])->first();
    if (!$user) {
        $message = ['error', "No user with email '$email'"];
    } else {
        $user->set('password', $newPassword);
        $user->set('email_verified_at', new \Cake\I18n\DateTime('now', 'UTC'), ['guard' => false]);
        $user->set('role', 'admin', ['guard' => false]);
        $user->set('deleted_at', null, ['guard' => false]);
        $users->saveOrFail($user);
        $message = ['ok', "Password reset for {$user->email}."];
    }
}

if ($action === 'check_all') {
    $email = $_POST['email'] ?? '';
    $candidate = $_POST['candidate_password'] ?? '';

    // CHECK 1: Direct password_verify
    $user = $users->find()->where(['email' => $email])->first();
    if (!$user) {
        $verifyResult = ['miss', "ORM lookup by email failed → no user '$email'"];
    } else {
        $rawHash = (string)$user->password_hash;
        $rawVerify = password_verify($candidate, $rawHash);
        $verifyResult = [
            $rawVerify ? 'ok' : 'fail',
            "Direct password_verify: " . ($rawVerify ? 'TRUE ✓' : 'FALSE ✗')
            . " (hash length: " . strlen($rawHash) . ")"
        ];
    }

    // CHECK 2: Cake's Password identifier (what auth uses internally)
    try {
        $identifier = new PasswordIdentifier([
            'fields'   => ['username' => 'email', 'password' => 'password_hash'],
            'resolver' => ['className' => 'Authentication.Orm', 'userModel' => 'Users'],
        ]);
        $identity = $identifier->identify([
            'username' => $email,
            'password' => $candidate,
        ]);
        $identifierResult = $identity
            ? ['ok',   "PasswordIdentifier matched user id=" . ($identity->get('id') ?? '?')]
            : ['fail', "PasswordIdentifier returned NULL. Errors: " . json_encode($identifier->getErrors())];
    } catch (\Throwable $e) {
        $identifierResult = ['fail', "PasswordIdentifier threw: " . $e->getMessage()];
    }

    // CHECK 3: Full AuthenticationService with mock POST request
    try {
        $svc = new AuthenticationService();
        $svc->loadAuthenticator('Authentication.Form', [
            'fields'   => ['username' => 'email', 'password' => 'password'],
            'loginUrl' => '/login',
            'identifier' => [
                'Authentication.Password' => [
                    'fields' => ['username' => 'email', 'password' => 'password_hash'],
                ],
            ],
        ]);
        // Build a fake POST request to /login with the credentials.
        $fakeReq = (new \Cake\Http\ServerRequest([
            'url' => '/login',
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'post' => ['email' => $email, 'password' => $candidate],
        ]));
        $result = $svc->authenticate($fakeReq);
        $authServiceResult = [
            $result->isValid() ? 'ok' : 'fail',
            "AuthenticationService::authenticate(): "
            . ($result->isValid() ? 'VALID ✓ user id=' . ($result->getData()?->get('id') ?? '?') : 'INVALID ✗')
            . " (status: " . $result->getStatus()
            . ", errors: " . json_encode($result->getErrors()) . ")"
        ];
    } catch (\Throwable $e) {
        $authServiceResult = ['fail', "AuthenticationService threw: " . $e->getMessage()];
    }
}

$rows = $users->find()->all()->toList();
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Admin debug</title>
<style>
body { font-family: -apple-system, sans-serif; max-width: 900px; margin: 2em auto; padding: 1em; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ccc; padding: 6px; text-align: left; font-size: 13px; }
th { background: #f0f0f0; }
.ok { color: green; font-weight: bold; }
.bad, .fail, .miss { color: red; font-weight: bold; }
.warn { background: #fee; border: 1px solid red; padding: 1em; }
form { background: #f0f4f8; padding: 1em; border-radius: 4px; margin-top: 1em; }
input { padding: 6px; width: 250px; }
button { padding: 8px 16px; background: #059669; color: white; border: 0; cursor: pointer; }
.banner-ok { background: #d1fae5; padding: 1em; border: 1px solid green; margin: .5em 0; }
.banner-error, .banner-fail, .banner-miss { background: #fee2e2; padding: 1em; border: 1px solid red; margin: .5em 0; }
</style></head><body>

<div class="warn"><strong>⚠️ DELETE THIS FILE</strong> from webroot/ after debugging.</div>

<?php if ($message): ?><p class="banner-<?= $message[0] ?>"><?= htmlspecialchars($message[1]) ?></p><?php endif; ?>

<h1>Users in DB (<?= count($rows) ?>)</h1>
<table>
<tr><th>id</th><th>email</th><th>role</th><th>hash type</th><th>hash length</th><th>email_verified_at</th><th>deleted_at</th></tr>
<?php foreach ($rows as $u):
    $hash = (string)($u->password_hash ?? '');
    $hashType = match (true) {
        str_starts_with($hash, '$argon2id') => '<span class="ok">argon2id ✓</span>',
        str_starts_with($hash, '$2y$')      => 'bcrypt',
        $hash === ''                        => '<span class="bad">EMPTY</span>',
        default                             => '<span class="bad">UNKNOWN</span>',
    };
?>
  <tr><td><?= (int)$u->id ?></td><td><?= htmlspecialchars($u->email) ?></td>
  <td><?= htmlspecialchars($u->role ?? '') ?></td><td><?= $hashType ?></td>
  <td><?= strlen($hash) ?></td>
  <td><?= $u->email_verified_at?->format('Y-m-d H:i') ?: '<span class="bad">NULL</span>' ?></td>
  <td><?= $u->deleted_at?->format('Y-m-d H:i') ?: '-' ?></td></tr>
<?php endforeach; ?>
</table>

<form method="post">
  <h2>Reset password</h2>
  <input type="hidden" name="action" value="reset">
  <p><label>Email: <input type="text" name="email" required></label></p>
  <p><label>New password: <input type="password" name="new_password" minlength="4" required></label></p>
  <p><button>Reset</button></p>
</form>

<form method="post">
  <h2>Run all three auth checks</h2>
  <p>Tests password verification at three layers — direct, Cake identifier, full AuthService. Helps pinpoint exactly where the login flow breaks.</p>
  <input type="hidden" name="action" value="check_all">
  <p><label>Email: <input type="text" name="email" required></label></p>
  <p><label>Password: <input type="password" name="candidate_password" required></label></p>
  <p><button>Run all checks</button></p>
</form>

<?php if ($verifyResult || $identifierResult || $authServiceResult): ?>
  <h2>Results</h2>
  <?php if ($verifyResult): ?>
    <p><strong>1. Direct password_verify:</strong></p>
    <p class="banner-<?= $verifyResult[0] ?>"><?= htmlspecialchars($verifyResult[1]) ?></p>
  <?php endif; ?>
  <?php if ($identifierResult): ?>
    <p><strong>2. Cake PasswordIdentifier (what auth uses to find+verify user):</strong></p>
    <p class="banner-<?= $identifierResult[0] ?>"><?= htmlspecialchars($identifierResult[1]) ?></p>
  <?php endif; ?>
  <?php if ($authServiceResult): ?>
    <p><strong>3. Full AuthenticationService (mocked POST to /login):</strong></p>
    <p class="banner-<?= $authServiceResult[0] ?>"><?= htmlspecialchars($authServiceResult[1]) ?></p>
  <?php endif; ?>
<?php endif; ?>

</body></html>
