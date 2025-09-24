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

define('TLI_PROJECT_DIR', '/var/www/turbolab.it/');
$txtPleaseReport = $db = null;


const THIS_SPECIAL_PAGE_PATH = '/ajax/login/';
require TLI_PROJECT_DIR . 'public/special-pages/includes/00_begin.php';


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
    require_once TLI_PROJECT_DIR . 'special-pages/includes/phpBBCookies.php';

    (new phpBBCookies())
        ->setNoRememberMeCookie([
            "id"            => $user->id(),
            "session_id"    => $user->session_id,
            "timestamp"     => (new DateTime())->modify('-1 minute')->format('Y-m-d H:i:s')
        ], '../../');
}


$forumLoginUrl              = $siteUrl . "/forum/ucp.php?mode=login";
$forumRegisterUrl           = $siteUrl . "/forum/ucp.php?mode=register";
$forumResendActivationUrl   = $siteUrl . "/forum/ucp.php?mode=resend_act";

match( $result["status"] ?? null ) {

    LOGIN_ERROR_USERNAME    => tliHtmlResponse('Username non trovato 😓 Sei sicuro di esserti già <a href="' . $forumRegisterUrl . '">iscritto</a>?', 401),
    LOGIN_ERROR_PASSWORD    => tliHtmlResponse('Password errata 🦖 Assicurati di averla scritta correttamente, poi riprova!', 403),
    LOGIN_ERROR_ACTIVE      => tliHtmlResponse('Questo account non è attivo 📧 Puoi <a href="' . $forumResendActivationUrl . '">richiedere l\'attivazione qui</a>', 401),
    LOGIN_ERROR_ATTEMPTS    => tliHtmlResponse('Hai eseguito troppi tentativi 🛑 <a href="' . $forumLoginUrl . '">Ora puoi solo eseguire login tramite il forum</a>', 429),
    LOGIN_SUCCESS           => tliHtmlResponse("Bentornato " . strip_tags($username) . " 😊!", 200),
    default                 => null
};

tliHtmlResponse('Errore sul server (è colpa nostra, non tua) 🐙 Per favore, <a href="' . $forumLoginUrl . '">esegui login dal forum</a>', 500);
