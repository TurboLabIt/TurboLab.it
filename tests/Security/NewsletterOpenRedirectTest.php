<?php
namespace App\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;


/**
 * 🔒 REGRESSION GUARD — docs/security-audit.md finding #7
 * ("Open redirect su /newsletter/open (backslash)", RESOLVED 2026-07-08).
 *
 * Black-box test: it hits the running dev instance over real HTTP, with no key and no login — exactly what a
 * remote attacker (or a newsletter recipient clicking a crafted link) can do. It asserts the open-redirect on
 * NewsletterController::opener() stays CLOSED.
 *
 * Background — the parser differential this guards against:
 *   - opener() extracts the host with parse_url() and allow-lists it against UrlGenerator::INTERNAL_DOMAINS.
 *   - parse_url() and browsers disagree on a URL with a backslash before "@": for "https://evil.com\@turbolab.it/"
 *     parse_url() returns host "turbolab.it" (passes the allowlist) while the browser normalizes "\" ➡ "/",
 *     making it "https://evil.com/@turbolab.it/" and navigating to evil.com.
 *   - WHILE VULNERABLE (verified live on dev0 before the fix): that payload returned
 *     `302 Location: https://evil.com\@turbolab.it/` — a working phishing redirect wrapping every newsletter link.
 *
 * THE FIX: opener() rejects any `url` containing a backslash, a control char, or "@" (our own opener URLs are
 * plain internal links and never contain those) BEFORE parse_url() runs, so the parser differential is
 * unreachable. Those payloads now return HTTP 400; a clean internal URL still redirects (302).
 *
 * A 302 to an external/undesired host here is NOT a flake: it means the open redirect reopened (the pre-parse
 * guard was removed or weakened). Treat it as a security regression on finding #7.
 *
 * Side-effect free: every request below either 400s at the guard or 302s to an internal URL; none carries a
 * valid `opener` token, so no subscriber state is touched.
 */
class NewsletterOpenRedirectTest extends TestCase
{
    /** Default dev instance; override with SECURITY_POC_TARGET to point elsewhere. */
    private const string DEFAULT_TARGET = 'https://dev0.turbolab.it';

    private const string ENDPOINT       = '/newsletter/open';


    /**
     * Malicious `url` values that must be refused with HTTP 400 — the guard rejects them before parse_url().
     * Each is the RAW (decoded) value; the test percent-encodes it into the query string.
     *
     * @return array<string, array{0:string}>
     */
    public static function maliciousUrlProvider() : array
    {
        return [
            // the canonical finding #7 exploit: parse_url() sees host "turbolab.it", the browser goes to evil.com
            'backslash before @ (the #7 exploit)' => ['https://evil.com\@turbolab.it/'],
            // pure userinfo confusion — "@" is refused outright (our real URLs never contain it)
            'userinfo @ with internal-looking host' => ['https://evil.com@turbolab.it/'],
            // backslash without "@" — still a normalization mismatch, still refused
            'backslash in authority' => ['https:\\\\evil.com/'],
            // control chars (here CRLF) — refused; also blocks header/redirect smuggling
            'CRLF injection after an internal host' => ["https://dev0.turbolab.it/\r\nSet-Cookie:x=1"],
            'raw tab in the url' => ["https://dev0.turbolab.it/\tfoo"],
        ];
    }


    #[DataProvider('maliciousUrlProvider')]
    public function testOpenRedirectPayloadsAreRejected(string $maliciousUrl) : void
    {
        $base = $this->reachableBaseOrSkip();

        [$status, $location] = $this->requestOpener($base, $maliciousUrl);

        $this->assertSame(
            400, $status,
            "SECURITY REGRESSION (finding #7): the open-redirect guard did not reject a hostile `url`.\n"
            . "Payload: " . var_export($maliciousUrl, true) . "\n"
            . "Expected HTTP 400 (rejected before parse_url), got HTTP $status"
            . ( $location !== null ? " with Location: $location" : "" ) . ".\n"
            . "The pre-parse backslash/@/control-char guard in NewsletterController::opener() was removed or weakened."
        );

        // belt-and-suspenders: whatever the status, we must never emit a redirect to a non-internal host
        if( $location !== null ) {
            $this->assertStringNotContainsString(
                'evil.com', $location,
                "SECURITY REGRESSION (finding #7): opener() issued a redirect towards evil.com (Location: $location)."
            );
        }
    }


    /** A clean internal `url` (no opener token) must still be honored with a 302 to that same internal URL. */
    public function testLegitimateInternalUrlStillRedirects() : void
    {
        $base = $this->reachableBaseOrSkip();

        $internalUrl = $base . '/';
        [$status, $location] = $this->requestOpener($base, $internalUrl);

        $this->assertSame(
            302, $status,
            "A clean internal `url` must still redirect (302), got HTTP $status. The guard is too aggressive."
        );
        $this->assertSame(
            $internalUrl, $location,
            "Expected a 302 to the internal URL $internalUrl, got Location: " . var_export($location, true) . "."
        );
    }


    /**
     * GET the opener endpoint with the given raw `url` value (percent-encoded here) and NO opener token.
     * Returns [httpStatus, locationHeaderOrNull]. Redirects are NOT followed.
     */
    private function requestOpener(string $base, string $rawUrl) : array
    {
        $endpoint = $base . self::ENDPOINT . '?url=' . rawurlencode($rawUrl);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => true,
            CURLOPT_FOLLOWLOCATION  => false,   // we assert on the redirect itself, never chase it
            CURLOPT_SSL_VERIFYPEER  => false,   // dev cert
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 20,
        ]);

        $response   = curl_exec($ch);
        $status     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr((string)$response, 0, $headerSize);

        return [$status, $this->extractLocation($rawHeaders)];
    }


    private function extractLocation(string $rawHeaders) : ?string
    {
        if( preg_match('/^location:\s*(.+?)\s*$/im', $rawHeaders, $m) === 1 ) {
            return $m[1];
        }

        return null;
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
            $this->markTestSkipped("Target $base is not reachable from this host — skipping live open-redirect guard.");
        }
        if( $home === 401 ) {
            $this->markTestSkipped("Target $base answers 401 (basic-auth gate) from this host — run from a whitelisted IP.");
        }

        return $base;
    }
}
