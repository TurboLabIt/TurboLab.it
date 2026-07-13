<?php
namespace App\Tests\Security;

use App\Entity\Cms\File as FileEntity;
use App\Service\Factory;
use App\Tests\BaseT;
use Symfony\Component\HttpFoundation\File\UploadedFile;


/**
 * Authors may upload ANY file type (the site distributes tools: .exe/.ps1/.bat/.sh/.apk/.iso/…), so the defence
 * is NOT an input allow-list — it is making the upload inert on delivery. Two nginx-level guarantees, both
 * asserted here against the RUNNING server (the Symfony test kernel bypasses nginx, so only real HTTP through
 * config/custom/nginx.conf exercises X-Sendfile, the security headers, and the execution deny):
 *
 *   1. Every download carries the site-wide security headers (nosniff/HSTS/CSP/…). These were silently absent
 *      before the fix: the X-Sendfile `location` declares `add_header x-tli-xsent`, and nginx inherits
 *      server-level add_headers ONLY when a location declares none of its own — so the marker stripped every
 *      security header from file downloads. `Content-Disposition: attachment` + `nosniff` keep an arbitrary
 *      upload inert in the browser (no inline render ⇒ no stored XSS via HTML/SVG).
 *
 *   2. The only extensions this stack can EXECUTE — the ones PHP-FPM runs (.php/.phar/.phtml/.pht) — are hard
 *      denied (HTTP 403) as belt-and-suspenders over finding #1. Non-PHP "scripts" (.ps1/.sh/…) are NOT denied:
 *      nginx has no handler for them and the site hosts them legitimately ("upload any file").
 *
 * A missing header, a served/executed .php, or a denied .ps1 here is a security (or feature) regression on #20.
 *
 * Fixtures are created through the real upload path (committed rows + bytes on disk, which the running server
 * shares) and always cleaned up. The test skips when the live server is unreachable from the runner.
 */
class FileDownloadHardeningTest extends BaseT
{
    /** Default dev instance; override with SECURITY_POC_TARGET to point elsewhere. */
    private const string DEFAULT_TARGET = 'https://dev0.turbolab.it';

    private const string FIXTURE_TITLE_PREFIX = 'tli-sec-download-hardening-';

    /** A PHP payload whose marker would appear in the body IFF the upload were ever executed or served. */
    private const string PHP_EXEC_MARKER = 'TLI-PHP-EXECUTED-MARKER-20';
    private const string PHP_PAYLOAD     = '<?php echo "' . self::PHP_EXEC_MARKER . '"; ?>' . "\ninert-body\n";

    /** header (lower-case) => substring that must appear in its value on every download. */
    private const array REQUIRED_SECURITY_HEADERS = [
        'x-content-type-options'    => 'nosniff',
        'x-frame-options'           => 'SAMEORIGIN',
        'content-security-policy'   => "frame-ancestors 'self'",
        'referrer-policy'           => 'strict-origin-when-cross-origin',
        'strict-transport-security' => 'max-age=',
    ];


    /**
     * GUARANTEE 1 — a normal (non-executable) upload downloads (200), through X-Sendfile, with the full
     * site-wide security-header set and an attachment disposition.
     */
    public function testRealDownloadCarriesSecurityHeaders() : void
    {
        $base   = $this->reachableBaseOrSkip();
        $fileId = null;

        try {
            $fileId = $this->createLocalFileFixture('bin', "tli security-header probe\n");

            [$status, $headers] = $this->httpGet($base . '/scarica/' . $fileId);

            $this->assertSame(200, $status, "A normal upload must download (200), got HTTP $status.");

            $this->assertArrayHasKey(
                'x-tli-xsent', $headers,
                "The download did not go through X-Sendfile (no x-tli-xsent header) — the assertions below would " .
                "be meaningless. Check the /xsend-uploaded-assets location."
            );

            foreach( self::REQUIRED_SECURITY_HEADERS as $header => $expectedSubstring ) {
                $this->assertArrayHasKey(
                    $header, $headers,
                    "SECURITY REGRESSION (finding #20): the download response is missing the '$header' header.\n" .
                    "nginx only inherits server-level add_headers when a location declares NONE of its own; the " .
                    "/xsend-uploaded-assets block declares 'x-tli-xsent', so the security headers must be " .
                    "re-declared there. One of them was dropped again."
                );
                $this->assertStringContainsString(
                    strtolower($expectedSubstring), strtolower($headers[$header]),
                    "The '$header' header is present but its value is unexpected: {$headers[$header]}"
                );
            }

            $this->assertStringStartsWith(
                'attachment', $headers['content-disposition'] ?? '',
                "SECURITY REGRESSION (finding #20): downloads must force 'Content-Disposition: attachment' so an " .
                "arbitrary-type upload is never rendered inline by the browser. Got: " .
                var_export($headers['content-disposition'] ?? null, true)
            );

        } finally {
            $this->deleteLocalFileFixture($fileId);
        }
    }


    /**
     * GUARANTEE 2a — an uploaded PHP-FPM-executable file is hard-denied (403) and its body never leaks/executes.
     */
    public function testExecutablePhpUploadIsDenied() : void
    {
        $base   = $this->reachableBaseOrSkip();
        $fileId = null;

        try {
            $fileId = $this->createLocalFileFixture('php', self::PHP_PAYLOAD);

            [$status, , $body] = $this->httpGet($base . '/scarica/' . $fileId);

            $this->assertSame(
                403, $status,
                "SECURITY REGRESSION (finding #20 / #1): an uploaded .php was served instead of denied (got HTTP " .
                "$status, expected 403). The `location ~* \\.(php…|phar|phtml|pht)$ { return 403; }` guard in the " .
                "/xsend-uploaded-assets block was removed or weakened."
            );

            $this->assertStringNotContainsString(
                self::PHP_EXEC_MARKER, $body,
                "CRITICAL SECURITY REGRESSION (finding #1): the uploaded PHP was EXECUTED or served — its marker " .
                "appeared in the response body. Uploaded files must never reach PHP-FPM."
            );

        } finally {
            $this->deleteLocalFileFixture($fileId);
        }
    }


    /**
     * GUARANTEE 2b — the deny is scoped to PHP-FPM-executable extensions ONLY. A non-PHP "script" such as .ps1
     * (which nginx cannot execute and the site legitimately hosts) must still download: "upload any file".
     */
    public function testNonPhpScriptUploadStaysDownloadable() : void
    {
        $base   = $this->reachableBaseOrSkip();
        $fileId = null;

        try {
            $fileId = $this->createLocalFileFixture('ps1', "Write-Host 'tli'\n");

            [$status] = $this->httpGet($base . '/scarica/' . $fileId);

            $this->assertSame(
                200, $status,
                "REGRESSION (finding #20): a non-PHP script upload (.ps1) was denied (HTTP $status). The execution " .
                "deny is too broad — it must cover ONLY PHP-FPM-executable extensions, or it breaks 'upload any " .
                "file' for the .ps1/.bat/.sh/… the site legitimately distributes."
            );

        } finally {
            $this->deleteLocalFileFixture($fileId);
        }
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 fixtures + HTTP helpers ***">

    /**
     * Create a real LOCAL file (committed DB row + bytes in var/uploaded-assets/files/{id}.{format}) via the
     * production upload path, then pin the on-disk extension to exactly $format. Returns the new file id.
     */
    private function createLocalFileFixture(string $format, string $bytes) : int
    {
        static::loginAsSystem();
        $factory = static::getService(Factory::class);

        $tmpPath = tempnam(sys_get_temp_dir(), 'tli_sec_dl_');
        file_put_contents($tmpPath, $bytes);

        // move()s the bytes into var/uploaded-assets/files/{id}.{guessedExt} and commits the row
        $uploaded = new UploadedFile($tmpPath, 'fixture.' . $format, null, null, true);
        $editor   = $factory->createFileEditor()
            ->createFromUploadedFile($uploaded, self::FIXTURE_TITLE_PREFIX . $format . '-' . uniqid());

        $fileId = $editor->getId();

        // guessExtension() may resolve to something other than $format ⇒ force it (load() primes previousFilePath,
        // so save() renames {id}.{guessed} → {id}.{format} on disk)
        $editorReloaded = $factory->createFileEditor()->load($fileId);
        if( $editorReloaded->getFormat() !== $format ) {
            $editorReloaded->setFormat($format)->save();
        }

        return $fileId;
    }


    private function deleteLocalFileFixture(?int $fileId) : void
    {
        if( empty($fileId) ) {
            return;
        }

        $em     = static::getEntityManager();
        $entity = $em->find(FileEntity::class, $fileId);
        if( $entity === null ) {
            return;
        }

        $filePath = static::getService(Factory::class)->createFile()->setEntity($entity)->getOriginalFilePath();
        if( !empty($filePath) && is_file($filePath) ) {
            @unlink($filePath);
        }

        $em->remove($entity);
        $em->flush();
    }


    /**
     * Real HTTP GET against the running nginx (NOT the test kernel — only nginx applies X-Sendfile, the security
     * headers and the execution deny). Redirects are not followed. Returns [status, lowerCasedHeaders, body].
     *
     * @return array{0:int, 1:array<string,string>, 2:string}
     */
    private function httpGet(string $url) : array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => true,
            CURLOPT_FOLLOWLOCATION  => false,
            CURLOPT_SSL_VERIFYPEER  => false,   // dev cert
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 30,
        ]);

        $response   = (string)curl_exec($ch);
        $status     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $rawHeaders = substr($response, 0, $headerSize);
        $body       = substr($response, $headerSize);

        return [$status, $this->parseHeaders($rawHeaders), $body];
    }


    /** @return array<string,string> last header block, keys lower-cased */
    private function parseHeaders(string $rawHeaders) : array
    {
        // on the off chance of multiple header blocks, keep the last (final response)
        $blocks = preg_split('/\r?\n\r?\n/', trim($rawHeaders));
        $last   = end($blocks) ?: '';

        $headers = [];
        foreach( preg_split('/\r?\n/', $last) as $line ) {
            if( str_contains($line, ':') ) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        return $headers;
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
            $this->markTestSkipped("Target $base is not reachable from this host — skipping live download-hardening guard.");
        }
        if( $home === 401 ) {
            $this->markTestSkipped("Target $base answers 401 (basic-auth gate) from this host — run from a whitelisted IP.");
        }

        return $base;
    }
    //</editor-fold>
}
