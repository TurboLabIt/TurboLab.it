<?php
namespace App\Twig;

use App\Security\LoginCsrf;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


class LoginCsrfExtension extends AbstractExtension
{
    public function __construct(private readonly LoginCsrf $loginCsrf) {}


    public function getFunctions() : array
    {
        // returns the double-submit token to render in the login form's hidden field (see App\Security\LoginCsrf)
        return [
            new TwigFunction('tli_login_csrf_token', [$this->loginCsrf, 'getToken']),
        ];
    }
}
