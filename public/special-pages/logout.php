<?php
/**
 * @link https://github.com/TurboLabIt/TurboLab.it/tree/main/docs/users.md
 *
 * https://turbolab.it/ajax/logout/
 *
 * Server-side phpBB logout: destroys the current session (and, for "remember me", its autologin key)
 * via phpBB's own session_kill(). Invoked server-to-server (curl) by the Symfony logout, forwarding
 * the user's phpBB cookies — so the session row is removed from the DB, not just cleared client-side.
 */

const TLI_PROJECT_DIR = '/var/www/turbolab.it/';
$txtPleaseReport = $db = null;


const THIS_SPECIAL_PAGE_PATH = '/ajax/logout/';
require TLI_PROJECT_DIR . 'public/special-pages/includes/00_begin.php';

if( !in_array($_SERVER['REMOTE_ADDR'] ?? null, ['127.0.0.1']) ) {
    tliHtmlResponse('This page is for internal use only', 403);
}

if( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    tliHtmlResponse('This page requires the POST method', 405);
}

require TLI_PROJECT_DIR . 'public/special-pages/includes/10_phpbb_start.php';

// after session_begin() (in 10_phpbb_start), $user is the session owner loaded from the forwarded
// phpBB cookies. session_kill() removes the session row + (for remember-me) the autologin key.
if( $user->data['user_id'] != ANONYMOUS ) {
    $user->session_kill(false);
}

tliHtmlResponse('OK', 200);
