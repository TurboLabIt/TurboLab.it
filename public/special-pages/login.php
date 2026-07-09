<?php
/**
 * @link https://github.com/TurboLabIt/TurboLab.it/tree/main/docs/users.md
 *
 * https://turbolab.it/ajax/login/
 *
 * 400: generic client error
 * 500: server error
 * 401: username not found
 * 403: wrong password
 * 429: too many retries
 * 200: OK
 */

const TLI_PROJECT_DIR = '/var/www/turbolab.it/';
$txtPleaseReport = $db = null;


const THIS_SPECIAL_PAGE_PATH = '/ajax/login/';
require TLI_PROJECT_DIR . 'public/special-pages/includes/00_begin.php';


// CSRF check. The same random token lives in both the __Host- cookie
// (set by Symfony) and the form's hidden field, sent together by the same-origin AJAX POST; a cross-site
// attacker can neither read nor set that cookie, so it can't produce a matching pair. Cookie name mirrors
// App\Security\LoginCsrf::COOKIE_NAME.
//
// Skipped when the REAL TCP peer is loopback (local test harness / internal calls): a cross-site CSRF attack
// always originates from a remote browser, never from 127.0.0.1, so loopback can't be a CSRF vector. The check
// uses tliRealClientIp() ($realip_remote_addr), NOT REMOTE_ADDR, so it can't be spoofed via X-Forwarded-For (cf. #5).
if( tliRealClientIp() !== '127.0.0.1' ) {
    $csrfCookie = $_COOKIE['__Host-tli_login_csrf'] ?? '';
    if( $csrfCookie === '' || !hash_equals($csrfCookie, $_POST['_csrf_token'] ?? '') ) {
        tliHtmlResponse('Sessione scaduta 🔄 Ricarica la pagina e riprova', 403);
    }
}


foreach (["username", "password"] as $field) {

    $fieldValue = $_POST[$field] ?? '';
    unset($_POST[$field]);

    $fieldValue = trim($fieldValue);
    if( empty($fieldValue) ) {
        tliHtmlResponse("Per eseguire login devi fornire username e password", 400);
    }

    // declare+assign $username and $password
    $$field = $fieldValue;
}

$rememberMe = !empty($_POST['remember-me']);
unset($_POST['remember-me']);

require TLI_PROJECT_DIR . 'public/special-pages/includes/10_phpbb_start.php';

// from: public/forum/phpbb/auth/auth.php
// sign: function login($username, $password, $autologin = false, $viewonline = 1, $admin = 0)
$result = $auth->login($username, $password, $rememberMe);

if( ($result["status"] ?? null) == LOGIN_SUCCESS && !$rememberMe ) {

    require_once TLI_PROJECT_DIR . 'vendor/turbolabit/php-encryptor/src/Encryptor.php';
    require_once TLI_PROJECT_DIR . 'public/special-pages/includes/phpBBCookies.php';

    (new phpBBCookies())
        ->setNoRememberMeCookie([
            "id"            => $user->id(),
            "session_id"    => $user->session_id,
            "timestamp"     => (new DateTime())->modify('-1 minute')->format('Y-m-d H:i:s')
        ]);
}


$forumLoginUrl              = TLI_SITE_URL . "/forum/ucp.php?mode=login";
$forumRegisterUrl           = TLI_SITE_URL . "/forum/ucp.php?mode=register";
$forumResendActivationUrl   = TLI_SITE_URL . "/forum/ucp.php?mode=resend_act";

match( $result["status"] ?? null ) {

    LOGIN_ERROR_USERNAME    => tliHtmlResponse('Username non trovato 😓 Sei sicuro di esserti già <a href="' . $forumRegisterUrl . '">iscritto</a>?', 401),
    LOGIN_ERROR_PASSWORD    => tliHtmlResponse('Password errata 🦖 Assicurati di averla scritta correttamente, poi riprova!', 403),
    LOGIN_ERROR_ACTIVE      => tliHtmlResponse('Questo account non è attivo 📧 Puoi <a href="' . $forumResendActivationUrl . '">richiedere l\'attivazione qui</a>', 401),
    LOGIN_ERROR_ATTEMPTS    => tliHtmlResponse('Hai eseguito troppi tentativi 🛑 <a href="' . $forumLoginUrl . '">Ora puoi solo eseguire login tramite il forum</a>', 429),
    LOGIN_SUCCESS           => tliHtmlResponse("Bentornato " . strip_tags($username) . " 😊!", 200),
    default                 => null
};

tliHtmlResponse('Errore sul server (è colpa nostra, non tua) 🐙 Per favore, <a href="' . $forumLoginUrl . '">esegui login dal forum</a>', 500);
