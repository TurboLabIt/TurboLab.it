<?php
namespace App\Tests\Security;

use App\Service\Factory;
use App\Service\User;
use App\Tests\BaseT;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;


/**
 * Anti-regression guard for docs/security-audit.md finding #40
 * ("Logout senza CSRF: termina la sessione phpBB server-side") — now RESOLVED.
 *
 * Logout used to be a plain GET link (`<a href="/logout">`) in the userbar, with no CSRF on the
 * firewall `logout` block. Because phpBB cookies carry no SameSite, a cross-site top-level GET
 * (`<img src=".../logout">`, a link, a redirect) reached the LogoutListener, which — thanks to #15 —
 * kills the phpBB session row + the "remember me" autologin key server-side and clears the cookies.
 * A third-party page could therefore repeatedly log a visitor out of both site AND forum.
 *
 * Fix: `enable_csrf: true` on the logout firewall block (security.yaml). The `logout` id is declared
 * stateless (csrf.yaml), so Symfony validates it with SameOriginCsrfTokenManager — the token is the
 * non-secret constant "csrf-token" (cache-safe for the per-user-cached, AJAX-loaded userbar) and the
 * real check is same-origin (Sec-Fetch-Site / Origin / Referer). The userbar logout is now a
 * same-origin POST form carrying that token, so legit logout works while cross-site logout is refused.
 *
 * NB: `killPhpBBSessionServerSide()` is best-effort and early-returns when there is no HTTP_COOKIE
 * (the test case), so the accepted-logout path below has no HTTP side effect. Origin/Referer are set
 * explicitly on every request because the shared BrowserKit client would otherwise auto-add a Referer
 * from history and make the origin check non-deterministic.
 */
class LogoutCsrfTest extends BaseT
{
    private const string LOGOUT = '/logout';


    private function client() : KernelBrowser
    {
        static::getService(CsrfTokenManagerInterface::class); // ensures the shared client is booted
        static::$client->catchExceptions(true);
        static::$client->followRedirects(false);
        return static::$client;
    }


    private function logoutToken() : string
    {
        return static::getService(CsrfTokenManagerInterface::class)->getToken('logout')->getValue();
    }


    public function testGetLogoutIsRefused() : void
    {
        // The original attack vector: a top-level GET (`<img src="/logout">`, a link, a redirect). The
        // CSRF token is read from the POST body only, so a GET can never carry it ➡ always refused,
        // regardless of origin. This is the strongest, origin-independent half of the guard.
        $client = $this->client();
        $client->request('GET', self::LOGOUT);

        $this->assertFalse($client->getResponse()->isRedirect(),
            'A GET to /logout must never perform a logout (security-audit #40)');
    }


    public function testCrossSiteLogoutIsRefused() : void
    {
        // A token-AWARE attacker: the stateless logout token is a non-secret constant, so the attacker
        // CAN submit the correct value via an auto-POST form. Only the origin check saves the victim.
        $client = $this->client();
        $client->request('POST', self::LOGOUT, ['_csrf_token' => $this->logoutToken()], [], [
            'HTTP_ORIGIN'  => 'https://evil.example',
            'HTTP_REFERER' => 'https://evil.example/attack',
        ]);

        $this->assertFalse($client->getResponse()->isRedirect(),
            'A cross-site POST to /logout must be refused even with the correct (non-secret) token (security-audit #40)');
    }


    public function testSameOriginLogoutIsAccepted() : void
    {
        // The legit path: a same-origin POST carrying the token ➡ CSRF passes ➡ 302 to the logout target.
        $client = $this->client();
        $client->request('POST', self::LOGOUT, ['_csrf_token' => $this->logoutToken()], [], [
            'HTTP_ORIGIN' => 'https://' . $_ENV['APP_SITE_DOMAIN'],
        ]);

        $this->assertTrue($client->getResponse()->isRedirect(),
            'A same-origin POST to /logout with the token must succeed (302)');
    }


    public function testUserbarLogoutIsPostFormWithCsrfTokenAndNotAGetLink() : void
    {
        $twig = static::getService(Environment::class);
        $user = static::getService(Factory::class)->createUser()->load(User::ID_SYSTEM);

        $html = $twig->render('user/userbar-logged.html.twig', [
            'User'          => $user,
            'ucpUrl'        => 'https://example.test/ucp',
            'newsletterUrl' => 'https://example.test/newsletter',
        ]);

        // logout is a POST form carrying the CSRF token…
        $this->assertMatchesRegularExpression('#<form[^>]*\bmethod="post"#i', $html);
        $this->assertStringContainsString('action="/logout"', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
        // …and is NOT a plain GET link any more (the #40 hole)
        $this->assertDoesNotMatchRegularExpression('#<a\s[^>]*href="[^"]*logout#i', $html);
    }
}
