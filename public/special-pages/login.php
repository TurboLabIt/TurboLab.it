<?php
/**
 * @link https://github.com/TurboLabIt/TurboLab.it/tree/main/docs/users.md
 *
 * https://turbolab.it/ajax/login/
 *
 * 400: generic client error
 * 500: server serror
 * 401: username not found
 * 403: worng password
 * 429: too many retries
 * 200: OK
 */

const THIS_SPECIAL_PAGE_PATH = '/ajax/login/';
$siteUrl = 'https://' . $_SERVER["SERVER_NAME"];


function tliResponse(string $message, int $httpStatusCode) : never
{
    $arrResult = [
        "code" => $httpStatusCode,
        "message" => $message
    ];

    http_response_code($httpStatusCode);
    die( json_encode($arrResult, JSON_PRETTY_PRINT) );
}


$requestUri = $_SERVER["REQUEST_URI"] ?? null;
if( $requestUri != '/ajax/login/' ) {
    tliResponse("L'URL di questa pagina è " . $siteUrl . THIS_SPECIAL_PAGE_PATH);
}

$devCredentialsFilePath = '../../backup/dev-credentials.php';
if( stripos($siteUrl, 'https://dev') === 0 && file_exists($devCredentialsFilePath) ) {
    include($devCredentialsFilePath);
}


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

// 📚 https://www.phpbb.com/support/docs/en/3.0/kb/article/phpbb3-cross-site-sessions-integration/
define('IN_PHPBB', true);
$phpbb_root_path = '../../public/forum/';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

require($phpbb_root_path . 'common.' . $phpEx);
require($phpbb_root_path . 'includes/functions_user.' . $phpEx);

$user->session_begin();
$auth->acl($user->data);
$user->setup();

// from: public/forum/phpbb/auth/auth.php
// sign: function login($username, $password, $autologin = false, $viewonline = 1, $admin = 0)
$result = $auth->login($username, $password, true, 1, 0);

$forumLoginUrl              = $siteUrl . "/forum/ucp.php?mode=login";
$forumRegisterUrl           = $siteUrl . "/forum/ucp.php?mode=register";
$forumResendActivationUrl   = $siteUrl . "/forum/ucp.php?mode=resend_act";

match( $result["status"] ?? null ) {

    LOGIN_ERROR_USERNAME    => tliResponse('Username non trovato 😓 Sei sicuro di esserti già <a href="' . $forumRegisterUrl . '">iscritto</a>?', 401),
    LOGIN_ERROR_PASSWORD    => tliResponse("Password errata 🦖 Assicurati di averla scritta correttamente, poi riprova!", 403),
    LOGIN_ERROR_ACTIVE      => tliResponse('Questo account non è attivo 📧 Puoi <a href="' . $forumResendActivationUrl . '">richiedere l\'attivazione qui</a>', 401),
    LOGIN_ERROR_ATTEMPTS    => tliResponse('Hai eseguito troppi tentativi 🛑 <a href="' . $forumLoginUrl . '">Ora puoi solo eseguire login tramite il forum</a>', 429),
    LOGIN_SUCCESS           => tliResponse("Bentornato " . strip_tags($username) . " 😊!", 200),
    default                 => null
};

tliResponse('Errore sul server (è colpa nostra, non tua) 🐙 Per favore, <a href="' . $forumLoginUrl . '">esegui login dal forum</a>', 500);
