#!/usr/bin/env php
<?php
/**
 * DEV COPIES ONLY. Opens a forum session for any user of this copy's database, without their password, and writes
 * the session file that forum.py (tli-issue skill) and browser.mjs (tli-browser-test skill) take as their state.
 *
 *   php dev-session.php who              accounts worth testing as, by what they are allowed to do
 *   php dev-session.php start USER       USER: a numeric ID or a username. Prints the state file
 *   php dev-session.php end STATE        closes that session and deletes the file
 *
 * It refuses to run unless APP_ENV is dev and the site's own address is a subdomain of turbolab.it. It writes to
 * the database this checkout is configured for, and nowhere else: it can't touch production.
 */
declare(strict_types=1);

const USER_AGENT  = 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0 tli-dev-session/1.0';
const FORUM       = 'turbolab_it_forum.phpbb_';

// this checkout's kernel and database, once it's certain this is a dev copy: PROJECT_DIR, fail(), $db, $site, $host
require __DIR__ . '/dev-copy.php';

$command  = $argv[1] ?? '';
$argument = $argv[2] ?? '';

/** The groups of a user that are not pending, as phpBB names them. */
$groups = static fn(int $userId) : array => $db->fetchFirstColumn(
    "SELECT g.group_name FROM " . FORUM . "user_group ug JOIN " . FORUM . "groups g ON g.group_id = ug.group_id
     WHERE ug.user_id = ? AND ug.user_pending = 0 ORDER BY g.group_name", [$userId]);

if ($command === 'who') {
    // what each kind of account is to the site: src/Entity/PhpBB/User.php::getRoles()
    $kinds = [
        'administrator (and editor)'          => "g.group_name = 'ADMINISTRATORS'",
        'editor: can edit and publish all'    => "g.group_name IN ('TLI-Staff', 'GLOBAL_MODERATORS')",
        'registered user'                     => "g.group_name = 'REGISTERED' AND u.user_posts > 20",
        'new user: first posts need approval' => "g.group_name = 'NEWLY_REGISTERED'",
    ];
    foreach ($kinds as $kind => $where) {
        $users = $db->fetchAllAssociative(
            "SELECT DISTINCT u.user_id, u.username, u.user_lastvisit FROM " . FORUM . "users u
             JOIN " . FORUM . "user_group ug ON ug.user_id = u.user_id AND ug.user_pending = 0
             JOIN " . FORUM . "groups g ON g.group_id = ug.group_id
             WHERE u.user_type IN (0, 3) AND $where ORDER BY u.user_lastvisit DESC LIMIT 4");
        echo $kind, ":\n";
        foreach ($users as $user) {
            echo "  ", $user['user_id'], "\t", html_entity_decode($user['username'], ENT_QUOTES), "\n";
        }
    }
    echo "an article's author: take the ID from the article (article_author.user_id)\n";
    exit(0);
}

if ($command === 'start' && $argument !== '') {
    $user = $db->fetchAssociative(
        "SELECT user_id, username, user_type FROM " . FORUM . "users WHERE user_id = ? OR username = ? OR username_clean = ? LIMIT 1",
        [ctype_digit($argument) ? (int) $argument : 0, htmlspecialchars($argument, ENT_COMPAT), mb_strtolower(htmlspecialchars($argument, ENT_COMPAT))]);
    if (!$user) {
        fail("no user «{$argument}» in this copy");
    }
    if (!in_array((int) $user['user_type'], [0, 3], true)) {
        fail("{$user['username']} isn't an active account here (a bot, or not activated): the site wouldn't log it in");
    }
    $cookieName = (string) $db->fetchOne("SELECT config_value FROM " . FORUM . "config WHERE config_name = 'cookie_name'");
    if (!preg_match('/^\w+$/', $cookieName)) {
        fail("can't read the forum's cookie name");
    }
    $sid = bin2hex(random_bytes(16));
    $key = bin2hex(random_bytes(16));
    $now = time();
    // phpBB compares the session's browser with the User-Agent of every request: the state file carries the same one
    $db->insert(FORUM . 'sessions', [
        'session_id' => $sid, 'session_user_id' => $user['user_id'], 'session_last_visit' => $now, 'session_start' => $now,
        'session_time' => $now, 'session_ip' => '127.0.0.1', 'session_browser' => substr(USER_AGENT, 0, 149),
        'session_forwarded_for' => '', 'session_page' => '', 'session_autologin' => 1,
    ]);
    // the site looks the key up by its md5, as phpBB stores it
    $db->insert(FORUM . 'sessions_keys', ['key_id' => md5($key), 'user_id' => $user['user_id'], 'last_ip' => '127.0.0.1', 'last_login' => $now]);

    // the dev certificate, when this copy has a self-signed one (the same file forum.py trusts for dev0)
    $nginx  = @file_get_contents(PROJECT_DIR . 'config/custom/dev/nginx-dev-shared.conf') ?: '';
    $cafile = preg_match('/^\s*ssl_certificate\s+([^;]+);/m', $nginx, $match) && is_file(trim($match[1])) ? trim($match[1]) : null;

    $directory = sys_get_temp_dir() . '/tli-forum-session.' . bin2hex(random_bytes(4));
    if (!mkdir($directory, 0700)) {
        fail("can't create $directory");
    }
    $state  = $directory . '/state.json';
    $handle = fopen($state, 'x');
    chmod($state, 0600);
    fwrite($handle, json_encode([
        'base_url' => $site, 'cafile' => $cafile, 'ua' => USER_AGENT, 'dev_session' => true,
        'username' => html_entity_decode($user['username'], ENT_QUOTES),
        'cookies'  => ["{$cookieName}_sid" => $sid, "{$cookieName}_u" => (string) $user['user_id'], "{$cookieName}_k" => $key],
    ]));
    fclose($handle);
    echo "OK session for ", html_entity_decode($user['username'], ENT_QUOTES), " (ID {$user['user_id']}; ",
         implode(', ', $groups((int) $user['user_id'])), ") on $host\n";
    echo "state file: $state\n";
    echo "when done: php ", realpath(__FILE__), " end $state\n";
    exit(0);
}

if ($command === 'end' && $argument !== '') {
    $state = realpath($argument);
    if (!$state || basename($state) !== 'state.json' || !str_starts_with(basename(dirname($state)), 'tli-forum-session.')) {
        fail("«{$argument}» isn't a state file written by start");
    }
    $session = json_decode((string) file_get_contents($state), true);
    $sid = $key = null;
    foreach (($session['cookies'] ?? []) as $name => $value) {
        $sid = str_ends_with((string) $name, '_sid') ? (string) $value : $sid;
        $key = str_ends_with((string) $name, '_k') ? (string) $value : $key;
    }
    if (empty($session['dev_session']) || !$sid || !$key) {
        fail("that state file wasn't made by dev-session.php: a real login ends with forum.py logout");
    }
    $sessions = $db->delete(FORUM . 'sessions', ['session_id' => $sid]);
    $keys     = $db->delete(FORUM . 'sessions_keys', ['key_id' => md5($key)]);
    unlink($state);
    rmdir(dirname($state));
    echo "OK session closed ($sessions session row, $keys key row removed), state deleted\n";
    exit(0);
}

fail("usage: php dev-session.php who | start USER | end STATE", 2);
