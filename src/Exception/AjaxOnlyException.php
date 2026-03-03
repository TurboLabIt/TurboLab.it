<?php
namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;


class AjaxOnlyException extends BadRequestHttpException
{
    public function __construct(string $message = 'This page can only be requested via AJAX', Throwable $previous = null, int $code = 0, array $headers = [])
    {
        parent::__construct($message, $previous, $code, $headers);
    }
}
