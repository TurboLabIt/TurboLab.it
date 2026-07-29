<?php
namespace App\Tests\Security;

use App\Security\phpBBCookiesAuthenticator;
use App\Service\Factory;
use App\Tests\BaseT;
use Symfony\Component\BrowserKit\Cookie;


/**
 * Anti-regression guard for docs/security-audit.md finding #44
 * ("Cookie u non numerico ➡ TypeError non intercettato ➡ 500 su ogni route") — now RESOLVED.
 *
 * phpBBCookiesAuthenticator read the phpBB "u" cookie (user id) with only trim() + a non-empty check
 * and passed it straight into UserRepository::findOneByUserSidKey(int $userId, ...). A non-numeric
 * value (settable in the attacker's own browser, or cookie-tossed from a sibling domain) hit PHP's
 * coercive typing and raised a TypeError; authenticate() has no try/catch, Symfony's
 * AuthenticatorManager catches only AuthenticationException (not Throwable), and there is no
 * kernel.exception listener ➡ HTTP 500 on nearly every route (supports() covers all but
 * AUTH_IGNORED_ROUTES). Self-DoS normally; a real issue under cross-subdomain cookie tossing.
 *
 * Fix: the authenticator validates "u" as a real positive int up front (filter_var FILTER_VALIDATE_INT)
 * and treats a malformed value as "no login data" ➡ the request proceeds ANONYMOUSLY (200) instead of
 * throwing. These tests hit a normal route with a malformed "u" (plus sid+k so the remember-me branch,
 * i.e. the int-typed repository call, is reached) and assert 200, not 500.
 */
class LoginMalformedUserIdCookieTest extends BaseT
{
    private function statusForUserCookie(string $u) : int
    {
        static::getService(Factory::class); // boots + configures the shared client
        static::$client->catchExceptions(true);

        $base = phpBBCookiesAuthenticator::COOKIE_BASENAME_PHPBB;
        $jar  = static::$client->getCookieJar();
        // sid + k present so getUserFromPhpBBCookies() reaches the int-typed repository call (the crash site)
        $jar->set( new Cookie($base . 'sid', str_repeat('a', 32)) );
        $jar->set( new Cookie($base . 'u',   $u) );
        $jar->set( new Cookie($base . 'k',   str_repeat('b', 32)) );

        static::$client->request('GET', '/');

        return static::$client->getResponse()->getStatusCode();
    }


    public function testNonNumericUserIdCookieYieldsAnonymousNot500() : void
    {
        $this->assertSame(200, $this->statusForUserCookie('abc'),
            'A non-numeric "u" cookie must fall back to anonymous, not raise an uncaught TypeError (security-audit #44)');
    }


    public function testOutOfRangeNumericUserIdCookieYieldsAnonymousNot500() : void
    {
        // all-digits but far beyond PHP_INT_MAX — a real positive-int check must reject this too
        $this->assertSame(200, $this->statusForUserCookie('99999999999999999999'),
            'An out-of-range numeric "u" cookie must also fall back to anonymous (security-audit #44)');
    }
}
