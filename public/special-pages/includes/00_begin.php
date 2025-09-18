<?php
// JSON response
function tliResponse(string $message, int $httpStatusCode) : never
{
    $arrResult = [
        "code"      => $httpStatusCode,
        "message"   => $message
    ];

    http_response_code($httpStatusCode);
    die( json_encode($arrResult, JSON_PRETTY_PRINT) );
}

// HTML response
function tliHtmlResponse(string $message, int $httpStatusCode) : never
{
    http_response_code($httpStatusCode);
    die($message);
}


if( !defined('THIS_SPECIAL_PAGE_PATH') ) {
    tliResponse('Special page path is undefined', 500);
}

$siteUrl = 'https://' . $_SERVER["SERVER_NAME"];
$projectDir = realpath('../../') . '/';

$requestUri = $_SERVER["REQUEST_URI"] ?? null;
if( strpos($requestUri, THIS_SPECIAL_PAGE_PATH) !== 0 ) {
    tliResponse("L'URL di questa pagina è " . $siteUrl . THIS_SPECIAL_PAGE_PATH, 400);
}

$txtPleaseReport = '🪲 Per favore, <a href="/forum/posting.php?mode=post&f=6">segnalaci subito il problema</a>, grazie!';

const ID_USER_SYSTEM = 5103;
