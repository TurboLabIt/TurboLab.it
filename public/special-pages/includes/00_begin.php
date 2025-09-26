<?php
define('TLI_SITE_URL', 'https://' . $_SERVER["SERVER_NAME"]);

header('Content-Type: text/plain; charset=utf-8');

set_exception_handler(function(Throwable $exception) {

    if( method_exists($exception, 'getStatusCode') ) {

        http_response_code($exception->getStatusCode());

    } else {

        http_response_code(500);
    }

    die( $exception->getMessage() );
});


set_error_handler(function (int $severity, string $message, string $file, int $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Fatal Error Catcher (Shutdown Function)
 *
 * This function is registered to run at the end of the script's execution.
 * Its primary purpose here is to catch fatal errors (like E_ERROR) that
 * cannot be handled by the custom error handler.
 */
register_shutdown_function(function() {

    $error = error_get_last();

    // Check if a fatal error occurred.
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {

        if (!headers_sent()) {
            http_response_code(500);
        }

        echo $error['message'];
    }
});


// HTML response
function tliHtmlResponse(string $message, int $httpStatusCode) : never
{
    http_response_code($httpStatusCode);
    die($message);
}


if( !defined('THIS_SPECIAL_PAGE_PATH') ) {
    tliHtmlResponse('Special page path is undefined', 500);
}

$requestUri = $_SERVER["REQUEST_URI"] ?? null;
if( strpos($requestUri, THIS_SPECIAL_PAGE_PATH) !== 0 ) {
    tliHtmlResponse("L'URL di questa pagina è " . TLI_SITE_URL . THIS_SPECIAL_PAGE_PATH, 400);
}

$txtPleaseReport = '🪲 Per favore, <a href="/forum/posting.php?mode=post&f=6">segnalaci subito il problema</a>, grazie!';

require TLI_PROJECT_DIR . 'src/Entity/BaseEntity.php';
require TLI_PROJECT_DIR . 'src/Entity/PhpBB/Forum.php';
