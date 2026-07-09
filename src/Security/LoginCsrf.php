<?php
namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;


/**
 * CSRF protection for the phpBB-side login endpoint (public/special-pages/login.php).
 *
 * The login form is rendered by Symfony but processed by a standalone phpBB special page, so a session-backed
 * Symfony CSRF token can't be validated there. Instead a single random token is placed in BOTH a `__Host-`
 * cookie (here) and the login form's hidden field (via the `login_csrf_token()` Twig function); login.php then
 * checks that the two match. Same-origin policy makes the pair unforgeable cross-site — an attacker can neither
 * read the victim's cookie (to copy it into a forged field) nor set that cookie.
 *
 * The token is stable per browser session (read back from the cookie when present), so the field and cookie
 * always agree even across navigations; the cookie is (re)issued only when it is missing.
 */
class LoginCsrf implements EventSubscriberInterface
{
    public const string COOKIE_NAME = '__Host-tli_login_csrf';

    private ?string $token  = null;
    private bool $isNew     = false;
    private bool $used      = false;


    public function __construct(private readonly RequestStack $requestStack) {}


    /**
     * The per-request token: reused from the cookie when present, otherwise freshly generated (and flagged so
     * the response subscriber issues the cookie). Called by the `login_csrf_token()` Twig function.
     */
    public function getToken() : string
    {
        $this->used = true;

        if( $this->token !== null ) {
            return $this->token;
        }

        $existing = $this->requestStack->getCurrentRequest()?->cookies->get(self::COOKIE_NAME);
        if( is_string($existing) && $existing !== '' ) {
            return $this->token = $existing;
        }

        $this->isNew = true;
        return $this->token = bin2hex( random_bytes(32) );
    }


    /**
     * Issue the cookie only when the form was actually rendered (token used) and no cookie existed yet — so we
     * never add a Set-Cookie to responses that don't need it (keeps cacheable/asset responses untouched).
     */
    public function onKernelResponse(ResponseEvent $event) : void
    {
        if( !$event->isMainRequest() || !$this->used || !$this->isNew ) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create(self::COOKIE_NAME, $this->token)
                ->withPath('/')                             // required by the __Host- prefix
                ->withSecure(true)                          // required by the __Host- prefix
                ->withHttpOnly(true)                        // login.php reads it server-side; JS never needs it
                ->withSameSite(Cookie::SAMESITE_STRICT)      // no domain ⇒ host-only, also required by __Host-
        );
    }


    public static function getSubscribedEvents() : array
    {
        return [ KernelEvents::RESPONSE => 'onKernelResponse' ];
    }
}
