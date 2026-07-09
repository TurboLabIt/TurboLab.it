<?php
namespace App\Tests\Security;

use PHPUnit\Framework\TestCase;


/**
 * 🔒 REGRESSION GUARD — docs/security-audit.md finding #24
 * ("Login (login.php) senza validazione CSRF", RESOLVED 2026-07-07).
 *
 * Black-box test over real HTTP against the running dev instance — exactly what a remote attacker sees. It
 * asserts the double-submit CSRF guard on /ajax/login/ stays CLOSED:
 *
 *   - a forged POST (no `__Host-tli_login_csrf` cookie, no matching field) from a REMOTE peer      ➡ 403;
 *   - the same forgery + a spoofed `X-Forwarded-For: 127.0.0.1` still can't reach the exemption     ➡ 403;
 *   - a genuine POST carrying the cookie+field pair minted by the login form                        ➡ NOT 403;
 *   - a POST from a real `127.0.0.1` peer is deliberately EXEMPT (local test harness)               ➡ NOT 403.
 *
 * WHY THE PEER IP MATTERS (and why this test pins it):
 *   login.php skips the CSRF check when the REAL TCP peer (`$realip_remote_addr`) is 127.0.0.1. On the dev/CI
 *   box the hostname itself resolves to 127.0.0.1, so a naive request would hit that exemption and never
 *   exercise the enforced path. To test enforcement we pin curl (CURLOPT_RESOLVE) to this box's NON-loopback
 *   LAN IP, so nginx sees a non-loopback peer — the same thing a real browser produces. The exemption itself
 *   is verified separately by pinning to 127.0.0.1.
 *
 * A 403 that turns into 401/200 on the forged remote-peer cases is NOT a flake: the CSRF guard was removed, or
 * the loopback exemption was widened to trust spoofable input (REMOTE_ADDR / X-Forwarded-For instead of
 * `$realip_remote_addr`). Treat it as a security regression on finding #24 (and, for the XFF case, #5).
 *
 * Side-effect free: every login uses a random, non-existent username ➡ LOGIN_ERROR_USERNAME (401), so no real
 * account is touched and phpBB's attempt throttling (which only bites existing users) is never engaged.
 */
class LoginCsrfTest extends TestCase
{
    private const string DEFAULT_TARGET = 'https://dev0.turbolab.it';
    private const string LOGIN_ENDPOINT = '/ajax/login/';
    private const string FORM_PAGE      = '/info';                  // renders the anonymous login form
    private const string CSRF_COOKIE    = '__Host-tli_login_csrf';

    private string $base;
    private string $host;
    private int $port;
    private ?string $remotePeerIp = null;   // a non-loopback IP that reaches the same nginx


    protected function setUp() : void
    {
        $this->base = rtrim(getenv('SECURITY_POC_TARGET') ?: self::DEFAULT_TARGET, '/');
        $this->host = (string)parse_url($this->base, PHP_URL_HOST);
        $this->port = (int)(parse_url($this->base, PHP_URL_PORT) ?: (str_starts_with($this->base, 'https') ? 443 : 80));

        $home = $this->request('GET', '/', null)['status'];
        if( $home === 0 ) {
            $this->markTestSkipped("Target {$this->base} is not reachable from this host.");
        }
        if( $home === 401 ) {
            $this->markTestSkipped("Target {$this->base} answers 401 (basic-auth gate) from this host.");
        }

        $this->remotePeerIp = $this->pickNonLoopbackPeerIp();
    }


    /** The core guard: a cross-site forgery (no cookie, no field) from a real remote peer must be refused. */
    public function testForgedCrossSiteLoginFromRemotePeerIsRejected() : void
    {
        $this->requireRemotePeer();

        $status = $this->request('POST', self::LOGIN_ENDPOINT, $this->remotePeerIp, [
            'username' => $this->throwawayUsername(),
            'password' => 'whatever',
        ])['status'];

        $this->assertSame(
            403, $status,
            "SECURITY REGRESSION (finding #24): a login POST with NO CSRF cookie/field, from a non-loopback "
            . "peer, was not rejected with 403 (got {$status}). The double-submit CSRF guard on "
            . self::LOGIN_ENDPOINT . " is gone, or the loopback exemption now trusts spoofable input."
        );
    }


    /** The exemption must key off the raw TCP peer, never a client header: a spoofed XFF can't unlock it. */
    public function testSpoofedXForwardedForCannotReachTheLoopbackExemption() : void
    {
        $this->requireRemotePeer();

        $status = $this->request('POST', self::LOGIN_ENDPOINT, $this->remotePeerIp, [
            'username' => $this->throwawayUsername(),
            'password' => 'whatever',
        ], [], [
            'X-Forwarded-For: 127.0.0.1',
            'CF-Connecting-IP: 127.0.0.1',
        ])['status'];

        $this->assertSame(
            403, $status,
            "SECURITY REGRESSION (findings #24/#5): a forged login carrying 'X-Forwarded-For: 127.0.0.1' was "
            . "not rejected with 403 (got {$status}). The CSRF exemption must use \$realip_remote_addr "
            . "(tliRealClientIp), which is header-immune — not REMOTE_ADDR / X-Forwarded-For."
        );
    }


    /** The guard must not be broken CLOSED: a genuine login carrying the matching pair reaches auth. */
    public function testGenuineLoginWithMatchingTokenPassesCsrf() : void
    {
        $this->requireRemotePeer();

        [$cookie, $field] = $this->fetchCsrfPair();
        if( $cookie === null || $field === null ) {
            $this->markTestSkipped("Could not read the CSRF cookie+field from " . self::FORM_PAGE . ".");
        }
        $this->assertSame($cookie, $field, "double-submit invariant: the form field must equal the cookie value.");

        $status = $this->request('POST', self::LOGIN_ENDPOINT, $this->remotePeerIp, [
            'username'    => $this->throwawayUsername(),
            'password'    => 'whatever',
            '_csrf_token' => $field,
        ], [self::CSRF_COOKIE . '=' . $cookie])['status'];

        $this->assertNotSame(
            403, $status,
            "A login carrying the matching CSRF cookie+field was rejected as 403 — the guard is broken CLOSED, "
            . "blocking genuine logins. Expected to reach auth (401 on a non-existent username, got {$status})."
        );
    }


    /** The loopback exemption is what keeps the local/CI HTTP harness working; guard that it stays. */
    public function testLoopbackPeerIsExemptFromCsrf() : void
    {
        if( $this->request('GET', self::FORM_PAGE, '127.0.0.1')['status'] !== 200 ) {
            $this->markTestSkipped("127.0.0.1 does not serve this app from here — cannot test the loopback exemption.");
        }

        $status = $this->request('POST', self::LOGIN_ENDPOINT, '127.0.0.1', [
            'username' => $this->throwawayUsername(),
            'password' => 'whatever',
        ])['status'];

        $this->assertNotSame(
            403, $status,
            "A login POST from the 127.0.0.1 peer was rejected as 403 — the loopback CSRF exemption "
            . "(security-audit.md #24) is gone, which will break local/CI HTTP login tests (got {$status})."
        );
    }


    // --- helpers ---------------------------------------------------------------------------------------------

    private function requireRemotePeer() : void
    {
        if( $this->remotePeerIp === null ) {
            $this->markTestSkipped(
                "No non-loopback route to {$this->host} from this host — cannot exercise the enforced CSRF path."
            );
        }
    }


    /** A non-loopback IP that reaches the same nginx: the host's own resolution if remote, else this box's LAN IP. */
    private function pickNonLoopbackPeerIp() : ?string
    {
        $resolved = gethostbyname($this->host);
        if( $resolved !== $this->host && !str_starts_with($resolved, '127.') ) {
            return $resolved;   // target is genuinely remote ➡ its peer is already non-loopback
        }

        // the hostname loops back to this box ➡ use our own LAN IP so nginx sees a non-loopback peer
        $lan = $this->localLanIp();
        if( $lan === null ) {
            return null;
        }

        return $this->request('GET', self::FORM_PAGE, $lan)['status'] === 200 ? $lan : null;
    }


    /** This host's primary non-loopback IPv4, via the kernel's routing choice for an outbound UDP socket. */
    private function localLanIp() : ?string
    {
        $sock = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
        if( $sock === false ) {
            return null;
        }
        $local = stream_socket_get_name($sock, false);   // "192.168.0.80:PORT"
        fclose($sock);

        if( !is_string($local) || !str_contains($local, ':') ) {
            return null;
        }
        $ip = substr($local, 0, (int)strrpos($local, ':'));

        return ($ip === '' || str_starts_with($ip, '127.')) ? null : $ip;
    }


    /** GET the login form and return [cookieValue, fieldValue] of the CSRF double-submit token. */
    private function fetchCsrfPair() : array
    {
        $res    = $this->request('GET', self::FORM_PAGE, $this->remotePeerIp);
        $cookie = preg_match('/\b' . preg_quote(self::CSRF_COOKIE, '/') . '=([^;\s]+)/', $res['headers'], $m) ? $m[1] : null;
        $field  = preg_match('/name="_csrf_token"\s+value="([a-f0-9]{64})"/', $res['body'], $m2) ? $m2[1] : null;

        return [$cookie, $field];
    }


    private function throwawayUsername() : string
    {
        return 'csrf_regression_' . bin2hex(random_bytes(5));   // never an existing account ➡ 401, no throttling
    }


    /**
     * One HTTP request. $peerIp pins the TCP peer via CURLOPT_RESOLVE (so nginx's $realip_remote_addr is
     * deterministic); null = default resolution. Returns ['status'=>int, 'headers'=>string, 'body'=>string].
     */
    private function request(string $method, string $path, ?string $peerIp, array $post = [], array $cookies = [], array $headers = []) : array
    {
        $ch = curl_init($this->base . $path);

        $opts = [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => true,
            CURLOPT_SSL_VERIFYPEER  => false,   // dev cert
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 20,
        ];

        if( $peerIp !== null ) {
            $opts[CURLOPT_RESOLVE] = ["{$this->host}:{$this->port}:{$peerIp}"];
        }
        if( $method === 'POST' ) {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
        }
        if( $cookies !== [] ) {
            $opts[CURLOPT_COOKIE] = implode('; ', $cookies);
        }
        if( $headers !== [] ) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $opts);

        $raw       = (string)curl_exec($ch);
        $status    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerLen = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // no curl_close(): a no-op, and deprecated since PHP 8.0

        return [
            'status'  => $status,
            'headers' => substr($raw, 0, $headerLen),
            'body'    => substr($raw, $headerLen),
        ];
    }
}
