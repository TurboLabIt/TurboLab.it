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
const THIS_SPECIAL_PAGE_PATH = '/ajax/login/';
require './includes/00_begin.php';

/*$devCredentialsFilePath = '../../backup/dev-credentials.php';
if( stripos($siteUrl, 'https://dev') === 0 && file_exists($devCredentialsFilePath) ) {
    include($devCredentialsFilePath);
}*/

foreach (["username", "password"] as $field) {

    $fieldValue = $_POST[$field] ?? '';
    unset($_POST[$field]);

    $fieldValue = trim($fieldValue);
    if( empty($fieldValue) ) {
        tliResponse("Per eseguire login devi fornire username e password", 400);
    }

    // declare+assign $username and $password
    $$field = $fieldValue;
}

$rememberMe = !empty($_POST['remember-me']);
unset($_POST['remember-me']);

require './includes/10_phpbb_start.php';

// from: public/forum/phpbb/auth/auth.php
// sign: function login($username, $password, $autologin = false, $viewonline = 1, $admin = 0)
$result = $auth->login($username, $password, $rememberMe);

if( ($result["status"] ?? null) == LOGIN_SUCCESS && !$rememberMe ) {

    require_once '../../vendor/turbolabit/php-encryptor/src/Encryptor.php';
    require_once 'includes/phpBBCookies.php';

    (new phpBBCookies())
        ->setNoRememberMeCookie([
            "id"            => $user->id(),
            "session_id"    => $user->session_id,
            "timestamp"     => (new DateTime())->modify('-1 minute')->format('Y-m-d H:i:s')
        ], '../../');
}


$forumLoginUrl              = $siteUrl . "/forum/ucp.php?mode=login";
$forumRegisterUrl           = $siteUrl . "/forum/ucp.php?mode=register";
$forumForgotPasswordUrl     = $siteUrl . "/forum/user/forgot_password";
$forumResendActivationUrl   = $siteUrl . "/forum/ucp.php?mode=resend_act";

match( $result["status"] ?? null ) {

    LOGIN_ERROR_USERNAME    => tliResponse('Username non trovato 😓 Sei sicuro di esserti già <a href="' . $forumRegisterUrl . '">iscritto</a>?', 401),
    LOGIN_ERROR_PASSWORD    => tliResponse('Password errata 🦖 Assicurati di averla scritta correttamente, poi riprova! Oppure: <a href="' . $forumForgotPasswordUrl . '">hai dimenticato la password?</a>', 403),
    LOGIN_ERROR_ACTIVE      => tliResponse('Questo account non è attivo 📧 Puoi <a href="' . $forumResendActivationUrl . '">richiedere l\'attivazione qui</a>', 401),
    LOGIN_ERROR_ATTEMPTS    => tliResponse('Hai eseguito troppi tentativi 🛑 <a href="' . $forumLoginUrl . '">Ora puoi solo eseguire login tramite il forum</a>', 429),
    LOGIN_SUCCESS           => tliResponse("Bentornato " . strip_tags($username) . " 😊!", 200),
    default                 => null
};

tliResponse('Errore sul server (è colpa nostra, non tua) 🐙 Per favore, <a href="' . $forumLoginUrl . '">esegui login dal forum</a>', 500);
