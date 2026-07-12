<?php
namespace App\Tests\Security;

use PHPUnit\Framework\TestCase;


/**
 * 🔒 REGRESSION GUARD — docs/security-audit.md finding #24
 * ("Login (login.php) senza validazione CSRF", RESOLVED 2026-07-12).
 *
 * Black-box test over real HTTP against the running dev instance — exactly what a remote attacker sees. The
 * CSRF defense on /ajax/login/ is an "ajaxOnly" gate: login.php requires the header
 * "X-Requested-With: XMLHttpRequest" (the same-origin login form sends it via jQuery $.post) and rejects
 * anything else with 400. It is caching-immune — no per-user token baked into the cached page — and it blocks
 * login CSRF: a cross-site <form> POST can't set a custom header, and a cross-origin fetch/XHR that adds it
 * trips a CORS preflight the site never allows.
 *
 *   - a forged cross-site POST (NO X-Requested-With), even with valid-looking fields  ➡ 400 (blocked);
 *   - a genuine AJAX POST (WITH X-Requested-With) reaches auth                          ➡ 401 (non-existent user).
 *
 * A 400 that turns into 401/200 on the no-header case is NOT a flake: the ajaxOnly guard on login.php was
 * removed or weakened, reopening login CSRF (finding #24). A 400 on the WITH-header case means the guard is
 * broken CLOSED, blocking every genuine login.
 *
 * Side-effect free: every login uses a random, non-existent username ➡ LOGIN_ERROR_USERNAME (401), so no real
 * account is touched and phpBB's attempt throttling (which only bites existing users) is never engaged.
 */
class LoginAjaxOnlyTest extends TestCase
{
    /** Default dev instance; override with SECURITY_POC_TARGET to point elsewhere. */
    private const string DEFAULT_TARGET = 'https://dev0.turbolab.it';

    private const string LOGIN_ENDPOINT = '/ajax/login/';


    /** The core guard: a cross-site forgery (no X-Requested-With header) must be refused with 400. */
    public function testForgedCrossSiteLoginWithoutAjaxHeaderIsRejected() : void
    {
        $base = $this->reachableBaseOrSkip();

        // Well-formed username+password on purpose: the ajaxOnly check runs BEFORE field validation, so the
        // only thing that can produce a 400 here is the missing header ➡ a 400 unambiguously means "blocked".
        [$status, $body] = $this->postLogin($base, [
            'username' => $this->throwawayUsername(),
            'password' => 'whatever',
        ]);   // ← no X-Requested-With header, exactly like a cross-site <form> POST

        $this->assertSame(
            400, $status,
            "SECURITY REGRESSION (finding #24): a login POST with NO 'X-Requested-With' header — what a "
            . "cross-site <form> produces — was not rejected with 400 (got {$status}). The ajaxOnly CSRF guard "
            . "on " . self::LOGIN_ENDPOINT . " is gone or weakened; login CSRF is reopened."
        );

        $this->assertStringContainsStringIgnoringCase(
            'AJAX', $body,
            "The no-header rejection returned 400 but not the ajaxOnly message — confirm it is the ajaxOnly "
            . "gate firing (before field validation), not another 400."
        );
    }


    /** The guard must not be broken CLOSED: a genuine AJAX login (header present) reaches auth. */
    public function testGenuineAjaxLoginReachesAuth() : void
    {
        $base = $this->reachableBaseOrSkip();

        [$status, ] = $this->postLogin($base, [
            'username' => $this->throwawayUsername(),
            'password' => 'whatever',
        ], ['X-Requested-With: XMLHttpRequest']);

        $this->assertNotSame(
            400, $status,
            "A genuine AJAX login (carrying 'X-Requested-With: XMLHttpRequest') was rejected with 400 — the "
            . "ajaxOnly guard is broken CLOSED, blocking every real login (got {$status})."
        );

        $this->assertSame(
            401, $status,
            "A genuine AJAX login with a non-existent username should reach auth and get 401 "
            . "(LOGIN_ERROR_USERNAME), got {$status}."
        );
    }


    // --- helpers ---------------------------------------------------------------------------------------------

    private function throwawayUsername() : string
    {
        return 'ajaxonly_regression_' . bin2hex(random_bytes(5));   // never an existing account ➡ 401, no throttling
    }


    /**
     * POST to the login endpoint. $headers is a raw curl header list (empty = a bare cross-site-style POST).
     * Returns [httpStatus, body].
     */
    private function postLogin(string $base, array $post, array $headers = []) : array
    {
        $ch = curl_init($base . self::LOGIN_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => http_build_query($post),
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_SSL_VERIFYPEER  => false,   // dev cert
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 20,
        ]);

        $body   = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // no curl_close(): a no-op, and deprecated since PHP 8.0

        return [$status, $body];
    }


    private function reachableBaseOrSkip() : string
    {
        $base = rtrim(getenv('SECURITY_POC_TARGET') ?: self::DEFAULT_TARGET, '/');

        $ch = curl_init($base . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_NOBODY          => true,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 20,
        ]);
        curl_exec($ch);
        $home = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if( $home === 0 ) {
            $this->markTestSkipped("Target $base is not reachable from this host — skipping live login ajaxOnly guard.");
        }
        if( $home === 401 ) {
            $this->markTestSkipped("Target $base answers 401 (basic-auth gate) from this host — run from a whitelisted IP.");
        }

        return $base;
    }
}
