<?php
/**
 * DEV COPIES ONLY. What every dev-copy helper starts with: this checkout's Symfony kernel, booted in dev, and its
 * database connection, once it's certain that this checkout is a dev copy. Required by dev-session.php (here) and by
 * stored-body.php (the tli-unlinked-downloads skill).
 *
 * It defines PROJECT_DIR and fail(), and leaves $db (the Doctrine connection), $site (the copy's own address) and
 * $host in the scope that requires it. It refuses to go on unless APP_ENV is dev and the copy's address is a
 * subdomain of turbolab.it: the database it opens is the one this checkout is configured for, never production's.
 */
declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

const PROJECT_DIR = __DIR__ . '/../../../../';

function fail(string $message, int $code = 1) : never
{
    fwrite(STDERR, "FAILED: $message\n");
    exit($code);
}

require PROJECT_DIR . 'vendor/autoload.php';
(new Dotenv())->bootEnv(PROJECT_DIR . '.env');
if (($_SERVER['APP_ENV'] ?? '') !== 'dev') {
    fail("APP_ENV is «" . ($_SERVER['APP_ENV'] ?? '') . "», not dev: this only runs on a dev copy");
}
$site = rtrim((string) ($_SERVER['APP_SITE_URL'] ?? ''), '/');
$host = (string) parse_url($site, PHP_URL_HOST);
if (parse_url($site, PHP_URL_SCHEME) !== 'https' || !preg_match('/^(?:[a-z0-9-]+\.)+turbolab\.it$/', $host) || $host === 'www.turbolab.it') {
    fail("this copy's address (APP_SITE_URL) isn't a dev copy under turbolab.it: nothing done");
}

$kernel = new Kernel('dev', true);
$kernel->boot();
$db = $kernel->getContainer()->get('doctrine')->getConnection();
