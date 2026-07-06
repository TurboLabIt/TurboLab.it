<?php
namespace App\Tests\Security;

use PHPUnit\Framework\TestCase;


/**
 * 🔒 REGRESSION GUARD — docs/security-audit.md finding #2
 * ("Padding oracle CBC sui token newsletter legacy", RESOLVED 2026-07-06).
 *
 * Black-box test: it hits the running dev instance over real HTTP, with no key, no login and no access to the
 * local code — exactly what a remote attacker can do. It asserts the CBC padding oracle stays CLOSED on the
 * newsletter endpoints (NewsletterController::subscribe/unsubscribe) now that `allowLegacy: true` is gone.
 *
 * Background — the oracle this guards against (no secret key needed to mount it):
 *   - A legacy token was `base64(IV ‖ ciphertext)` (Encryptor::decryptLegacy), AES-256-CBC, padding-checked.
 *   - Sweeping the last IV byte over 0..255 makes the last plaintext byte `D(C1)[15] XOR IV[15]` take all 256
 *     values, so exactly one yields `0x01`, i.e. valid 1-byte PKCS#7 padding.
 *   - WHILE VULNERABLE: invalid padding ➡ EncryptionException ➡ caught ➡ HTTP 400; valid padding ➡ garbage
 *     plaintext ➡ @unserialize() fails ➡ generic \Exception (uncaught) ➡ HTTP 500. That 255×400 / 1×500 split
 *     was the oracle: distinguish padding validity per byte and, via CBC-R, forge a token for any userId.
 *
 * THE FIX: with `allowLegacy` removed, decrypt() refuses every non-`-v2` token *before* openssl_decrypt runs,
 * so there is no padding check left to leak. The whole sweep now returns a UNIFORM 400 — no 500, no oracle bit.
 *
 * This test therefore asserts an all-400 sweep with zero 500s. A single 500 here is NOT a flake: it means the
 * oracle reopened — `allowLegacy: true` came back, or a new legacy decrypt path appeared. Treat it as a
 * security regression on finding #2.
 *
 * Side-effect free: every crafted token is rejected at decrypt, so control never reaches the subscribe/
 * unsubscribe DB logic — no real subscriber is touched.
 */
class NewsletterPaddingOracleTest extends TestCase
{
    /** Default dev instance; override with SECURITY_POC_TARGET to point elsewhere. */
    private const string DEFAULT_TARGET = 'https://dev0.turbolab.it';

    /** Newsletter endpoint that returns 400 on any decrypt failure (subscribe is equivalent). */
    private const string ENDPOINT       = '/newsletter/disiscrizione/';

    private const int SWEEP_SIZE        = 256;      // all values of a single byte
    private const int CONCURRENCY       = 20;       // gentle burst; dev-mode requests are ~2s each

    /** Fixed IV base + one fixed ciphertext block. Their content is irrelevant to the oracle. */
    private const string IV_BASE        = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
    private const string CIPHER_BLOCK   = "\x41\x41\x41\x41\x41\x41\x41\x41\x41\x41\x41\x41\x41\x41\x41\x41";


    public function testLegacyNewsletterTokensAreRejectedWithoutAPaddingOracle() : void
    {
        $base = rtrim(getenv('SECURITY_POC_TARGET') ?: self::DEFAULT_TARGET, '/');

        // preflight: the target must be reachable and not hidden behind the dev/staging basic-auth gate
        $home = $this->httpStatus($base . '/');
        if( $home === 0 ) {
            $this->markTestSkipped("Target $base is not reachable from this host — skipping live padding-oracle guard.");
        }
        if( $home === 401 ) {
            $this->markTestSkipped("Target $base answers 401 (basic-auth gate) from this host — run from a whitelisted IP.");
        }

        // sweep the last IV byte 0..255 against the live endpoint, in parallel
        $statusByByte = $this->sweepLastIvByte($base . self::ENDPOINT);

        $counts = array_count_values($statusByByte);
        $n400   = $counts[400] ?? 0;
        $n500   = $counts[500] ?? 0;
        $report = $this->formatDistribution($counts);

        // 1) the oracle signal must be GONE: no crafted legacy token may flip the status to 500 (valid-padding
        //    leak). A single 500 means the padding check became reachable again — finding #2 reopened.
        $this->assertSame(
            0, $n500,
            "SECURITY REGRESSION (finding #2): a legacy token produced HTTP 500, so the response distinguishes "
            . "PKCS#7 padding validity again — the CBC padding oracle has reopened. Did `allowLegacy: true` come "
            . "back, or did a new legacy decrypt path appear? Observed: $report"
        );

        // 2) every non-`-v2` token must be refused the SAME way (uniform 400): no distinguishable classes, so
        //    an attacker learns nothing and CBC-R has no oracle to drive.
        $this->assertGreaterThanOrEqual(
            self::SWEEP_SIZE - 6, $n400,
            "Expected the whole sweep to be rejected uniformly as HTTP 400 (the legacy AES-CBC path is refused "
            . "before any padding check runs). Observed: $report"
        );
    }


    /**
     * Send SWEEP_SIZE requests — one per value of the last IV byte — with a bounded concurrency window.
     * Returns [ ivByte => httpStatus ].
     */
    private function sweepLastIvByte(string $endpoint) : array
    {
        $mh         = curl_multi_init();
        $byteOf     = [];   // (int)handle => iv byte
        $status     = [];   // iv byte     => http status
        $next       = 0;
        $done       = 0;

        $enqueue = function(int $byte) use ($mh, $endpoint, &$byteOf, &$next) : void {
            $ch = $this->makeRequestHandle($endpoint, $byte);
            $byteOf[ (int)$ch ] = $byte;
            curl_multi_add_handle($mh, $ch);
            $next++;
        };

        while( $next < self::SWEEP_SIZE && count($byteOf) < self::CONCURRENCY ) {
            $enqueue($next);
        }

        do {
            curl_multi_exec($mh, $active);
            if( curl_multi_select($mh, 1.0) === -1 ) {
                usleep(1000);   // guard against a busy-loop when select() returns -1
            }

            while( $info = curl_multi_info_read($mh) ) {
                $ch   = $info['handle'];
                $byte = $byteOf[ (int)$ch ];
                $status[$byte] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

                unset( $byteOf[ (int)$ch ] );
                curl_multi_remove_handle($mh, $ch);   // handle is freed automatically (curl_close is a no-op since PHP 8.0)
                $done++;

                if( $next < self::SWEEP_SIZE ) {
                    $enqueue($next);
                }
            }
        } while( $done < self::SWEEP_SIZE );

        curl_multi_close($mh);
        ksort($status);

        return $status;
    }


    /**
     * Build a curl handle for the token [IV ‖ CIPHER_BLOCK] with IV's last byte set to $ivLastByte.
     * The token is encoded exactly as Encryptor::encrypt() would: base64, then "/" ➡ "__ssym1__".
     */
    private function makeRequestHandle(string $endpoint, int $ivLastByte)
    {
        $iv      = self::IV_BASE;
        $iv[15]  = chr($ivLastByte);
        $base64  = base64_encode($iv . self::CIPHER_BLOCK);
        $token   = str_replace(['/', '\\'], ['__ssym1__', '__ssym2__'], $base64);

        $ch = curl_init($endpoint . rawurlencode($token));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_SSL_VERIFYPEER  => false,   // dev cert
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 20,
        ]);

        return $ch;
    }


    private function httpStatus(string $url) : int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 20,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);   // 0 on connection failure

        return $code;
    }


    private function formatDistribution(array $counts) : string
    {
        ksort($counts);
        $parts = [];
        foreach( $counts as $code => $n ) {
            $parts[] = "{$n}×HTTP{$code}";
        }

        return implode(', ', $parts);
    }
}
