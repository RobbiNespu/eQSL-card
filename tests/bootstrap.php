<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.0.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Chronos\Chronos;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\ConnectionHelper;
use Migrations\TestSuite\Migrator;

/**
 * Test runner bootstrap.
 *
 * Add additional configuration/setup your application needs when running
 * unit tests in this file.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

require dirname(__DIR__) . '/config/bootstrap.php';

if (empty($_SERVER['HTTP_HOST']) && !Configure::read('App.fullBaseUrl')) {
    Configure::write('App.fullBaseUrl', 'http://localhost');
}

// DebugKit skips settings these connection config if PHP SAPI is CLI / PHPDBG.
// But since PagesControllerTest is run with debug enabled and DebugKit is loaded
// in application, without setting up these config DebugKit errors out.
ConnectionManager::setConfig('test_debug_kit', [
    'className' => 'Cake\Database\Connection',
    'driver' => 'Cake\Database\Driver\Sqlite',
    'database' => TMP . 'debug_kit.sqlite',
    'encoding' => 'utf8',
    'cacheMetadata' => true,
    'quoteIdentifiers' => false,
]);

// Force the default email transport to "Debug" during tests so EmailVerification
// and any other code path that triggers a real `send()` doesn't try to reach
// an SMTP server (and dump "SMTP server did not accept the connection" lines
// all over the phpunit output). The Debug transport stores delivered messages
// in memory; tests that care can inspect them via the TestEmailTransport
// helper from the cakephp/cakephp suite.
\Cake\Mailer\TransportFactory::drop('default');
\Cake\Mailer\TransportFactory::setConfig('default', ['className' => 'Debug']);

ConnectionManager::alias('test_debug_kit', 'debug_kit');

// Fixate now to avoid one-second-leap-issues
Chronos::setTestNow(Chronos::now());

// Fixate sessionid early on, as php7.2+
// does not allow the sessionid to be set after stdout
// has been written to.
session_id('cli');

// Connection aliasing needs to happen before migrations are run.
// Otherwise, table objects inside migrations would use the default datasource
ConnectionHelper::addTestAliases();

// Use migrations to build test database schema.
//
// Will rebuild the database if the migration state differs
// from the migration history in files.
//
// If you are not using CakePHP's migrations you can
// hook into your migration tool of choice here or
// load schema from a SQL dump file with
// use Cake\TestSuite\Fixture\SchemaLoader;
// (new SchemaLoader())->loadSqlFiles('./tests/schema.sql', 'test');

(new Migrator())->run();

// Point InstallationCheckMiddleware at a per-run temp lock so integration
// tests skip the redirect to /install. The middleware reads this Configure
// key when set, otherwise falls back to CONFIG . 'installed.lock'.
$testInstallLock = TMP . 'installed.lock';
if (!file_exists($testInstallLock)) {
    touch($testInstallLock);
}
Configure::write('Installation.lockFile', $testInstallLock);

// Clear rate-limit cache between phpunit runs so RateLimitMiddleware starts
// from a clean count. Without this, accumulated state from prior runs (or
// across processes sharing TMP) can trip 429s in tests that exercise login
// or generate endpoints.
$rateLimitDir = TMP . 'cache/rate_limits';
if (is_dir($rateLimitDir)) {
    foreach (glob($rateLimitDir . '/*') ?: [] as $f) {
        @unlink($f);
    }
}
