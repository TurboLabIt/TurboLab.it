<?php
namespace App\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;


/**
 * The PHP scripts under public/special-pages/ are internal plumbing: they must be reachable ONLY through the
 * designated rewrites in config/custom/nginx.conf (/ajax/login/, /ajax/logout/, /ajax/commenti/{id},
 * /comments-topic-update/, /comments-topic-delete/, /issue-add-to-post/) — NEVER by their direct
 * `/special-pages/<file>.php` filename. The fix marks `location ^~ /special-pages/` as `internal`, so a request
 * "changed by the rewrite directive" still lands there while any direct external hit gets a bare nginx 404 and
 * PHP-FPM is never invoked.
 *
 * This is a black-box test over real HTTP against the running server (nginx, NOT the Symfony test kernel — the
 * kernel never sees this nginx routing). Two complementary guarantees:
 *
 *   1. DIRECT ACCESS IS DEAD — every *.php under public/special-pages/ (discovered dynamically, so a new file
 *      is covered automatically), requested by its direct /special-pages/… URL, returns 404 and leaks none of
 *      the internal markers a direct execution used to emit. Before the fix, the include-only helpers had no
 *      THIS_SPECIAL_PAGE_PATH guard and, hit directly, ran far enough to 500 / emit a PHP warning that spelled
 *      out absolute filesystem paths (e.g. phpBBCookies.php ➡ "Failed to open stream: …/src/Trait/…").
 *
 *   2. THE REWRITES STILL WORK — each of the six designated rewrite URLs still reaches its special page (any
 *      status EXCEPT 404). None of these pages ever answers 404 from application logic (they use
 *      400/401/403/405/429/500/200), so a 404 here would mean the `internal` block also swallowed the rewrite —
 *      i.e. the fix was applied so bluntly it broke every non-Symfony endpoint at once.
 *
 * A 200/400/500 on a direct-access URL is a security regression (finding #25 reopened: the `internal` marker was
 * dropped, or a new fastcgi handler exposed the path). A 404 on a rewrite URL is a functional regression (the
 * rewrites were broken while locking the door). Both fail loudly here.
 *
 * Side-effect free: every probe is a GET with no body — the state-changing pages (login/logout/update/delete/
 * issues) reject a bare GET at their method/loopback guard long before touching any data. The test skips when
 * the live server is unreachable from the runner.
 */
class SpecialPagesInternalOnlyTest extends TestCase
{
    /** Default dev instance; override with SECURITY_POC_TARGET to point elsewhere. */
    private const string DEFAULT_TARGET = 'https://dev0.turbolab.it';

    /**
     * The six designated rewrites (config/custom/nginx.conf). A GET to each must REACH its special page — i.e.
     * return anything except 404. The comments id is the dev0 quality-test article's topic; even a
     * format-valid-but-missing id answers 500 ("Topic non trovato"), never 404, so this stays portable.
     */
    private const array DESIGNATED_REWRITES = [
        '/ajax/login/',
        '/ajax/logout/',
        '/ajax/commenti/1939',
        '/comments-topic-update/',
        '/comments-topic-delete/',
        '/issue-add-to-post/',
    ];

    /**
     * Known scripts that MUST 404 on direct access, listed explicitly as a floor under the dynamic discovery:
     * if the glob ever silently returns nothing, these still get checked. Discovery is asserted to cover them
     * in {@see testDiscoveryCoversTheKnownScripts}.
     */
    private const array KNOWN_DIRECT_PATHS = [
        '/special-pages/includes/00_begin.php',
        '/special-pages/includes/10_phpbb_start.php',
        '/special-pages/includes/phpBBCookies.php',
        '/special-pages/login.php',
        '/special-pages/logout.php',
        '/special-pages/comments.php',
        '/special-pages/comments-topic-update.php',
        '/special-pages/comments-topic-delete.php',
        '/special-pages/issues.php',
    ];

    /**
     * Substrings that betray the script actually EXECUTED (rather than being 404'd by nginx). None may appear in
     * a direct-access response body. `/var/www/` and the trait filename are the exact leak phpBBCookies.php
     * emitted pre-fix; the others are die() messages from the include-only helpers.
     */
    private const array EXECUTION_LEAK_MARKERS = [
        '/var/www/',
        'phpBBCookiesAuthenticatorTrait',
        'Special page path is undefined',
        'TLI_PROJECT_DIR',
    ];


    // ---------------------------------------------------------------------------------------------------------
    // 1) DIRECT ACCESS IS DEAD
    // ---------------------------------------------------------------------------------------------------------

    #[DataProvider('directSpecialPageUrlProvider')]
    public function testDirectAccessToSpecialPageIsBlocked(string $url) : void
    {
        $base = $this->reachableBaseOrSkip();

        [$status, $body] = $this->httpGet($base . $url);

        $this->assertSame(
            404, $status,
            "SECURITY REGRESSION (finding #25): a DIRECT request to {$url} returned {$status}, not 404. The " .
            "`internal` marker on `location ^~ /special-pages/` in config/custom/nginx.conf was dropped or " .
            "bypassed — special pages are executable by their raw filename again, exposing include-only helpers " .
            "and letting entry pages be driven around their intended rewrite URL."
        );

        foreach( self::EXECUTION_LEAK_MARKERS as $marker ) {
            $this->assertStringNotContainsString(
                $marker, $body,
                "SECURITY REGRESSION (finding #25): the direct-access response for {$url} contains '{$marker}', " .
                "which only appears when the PHP actually runs. nginx must 404 the request before PHP-FPM is " .
                "ever invoked."
            );
        }
    }


    /** Case / dot-segment / slash-normalisation variants must not sneak past the `^~ /special-pages/` prefix. */
    #[DataProvider('bypassVariantProvider')]
    public function testNormalisationBypassesAreBlocked(string $rawPath) : void
    {
        $base = $this->reachableBaseOrSkip();

        [$status] = $this->httpGet($base . $rawPath);

        $this->assertSame(
            404, $status,
            "SECURITY REGRESSION (finding #25): the crafted path {$rawPath} returned {$status}, not 404. nginx " .
            "normalises case/dot-segments/duplicate-and-encoded slashes BEFORE location matching, so every one of " .
            "these must still resolve into the `internal` /special-pages/ block and 404. A non-404 means a " .
            "normalisation gap re-exposed the scripts."
        );
    }


    // ---------------------------------------------------------------------------------------------------------
    // 2) THE REWRITES STILL WORK
    // ---------------------------------------------------------------------------------------------------------

    #[DataProvider('designatedRewriteProvider')]
    public function testDesignatedRewriteStillReachesPhp(string $url) : void
    {
        $base = $this->reachableBaseOrSkip();

        [$status] = $this->httpGet($base . $url);

        $this->assertNotSame(
            404, $status,
            "FUNCTIONAL REGRESSION (finding #25 fix applied too bluntly): the designated rewrite {$url} returned " .
            "404 — the same as a blocked direct hit. The `internal` block is meant to let requests 'changed by " .
            "the rewrite directive' through; a 404 here means the rewrite no longer reaches its special page and " .
            "the whole non-Symfony surface (login, logout, comments, issue linking) is down."
        );
    }


    // ---------------------------------------------------------------------------------------------------------
    // 3) DISCOVERY SANITY — the dynamic glob must actually see the known scripts (else the suite is vacuous)
    // ---------------------------------------------------------------------------------------------------------

    public function testDiscoveryCoversTheKnownScripts() : void
    {
        $discovered = self::discoverSpecialPageUrls();

        $this->assertGreaterThanOrEqual(
            count(self::KNOWN_DIRECT_PATHS), count($discovered),
            'Dynamic discovery under public/special-pages/ found fewer files than the known-scripts floor — the ' .
            'glob is broken, which would make testDirectAccessToSpecialPageIsBlocked silently under-cover.'
        );

        foreach( self::KNOWN_DIRECT_PATHS as $known ) {
            $this->assertContains(
                $known, $discovered,
                "Dynamic discovery did not surface {$known}. Every *.php under public/special-pages/ must be " .
                "enumerated so new scripts are auto-covered by the direct-access guard."
            );
        }
    }


    // ---------------------------------------------------------------------------------------------------------
    // data providers
    // ---------------------------------------------------------------------------------------------------------

    /** Every discovered special-page URL, unioned with the known-scripts floor. @return array<string,array{string}> */
    public static function directSpecialPageUrlProvider() : array
    {
        $urls = array_unique( array_merge(self::discoverSpecialPageUrls(), self::KNOWN_DIRECT_PATHS) );
        sort($urls);

        $cases = [];
        foreach( $urls as $url ) {
            $cases[$url] = [$url];
        }

        return $cases;
    }


    /** @return array<string,array{string}> */
    public static function designatedRewriteProvider() : array
    {
        $cases = [];
        foreach( self::DESIGNATED_REWRITES as $url ) {
            $cases[$url] = [$url];
        }

        return $cases;
    }


    /** @return array<string,array{string}> */
    public static function bypassVariantProvider() : array
    {
        $variants = [
            'uppercase prefix'        => '/SPECIAL-PAGES/login.php',
            'mixed-case prefix'       => '/Special-Pages/login.php',
            'dot-dot segment'         => '/special-pages/includes/../login.php',
            'dot segment'             => '/special-pages/./login.php',
            'leading double slash'    => '//special-pages/login.php',
            'inner double slash'      => '/special-pages//login.php',
            'trailing path-info'      => '/special-pages/login.php/x',
            'external dot-dot pivot'  => '/foo/../special-pages/login.php',
            'encoded slash (lower)'   => '/special-pages%2flogin.php',
            'encoded slash (upper)'   => '/special-pages%2Flogin.php',
        ];

        $cases = [];
        foreach( $variants as $label => $path ) {
            $cases[$label] = [$path];
        }

        return $cases;
    }


    // ---------------------------------------------------------------------------------------------------------
    // discovery + HTTP helpers
    // ---------------------------------------------------------------------------------------------------------

    /**
     * Enumerate every *.php under public/special-pages/ and map it to its direct URL path, e.g.
     * public/special-pages/includes/00_begin.php ➡ /special-pages/includes/00_begin.php.
     *
     * @return list<string>
     */
    private static function discoverSpecialPageUrls() : array
    {
        $root = dirname(__DIR__, 2) . '/public/special-pages';
        if( !is_dir($root) ) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $urls = [];
        /** @var SplFileInfo $file */
        foreach( $iterator as $file ) {
            if( !$file->isFile() || strtolower($file->getExtension()) !== 'php' ) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
            $urls[]   = '/special-pages' . $relative;
        }

        sort($urls);
        return $urls;
    }


    /**
     * Real HTTP GET against the running nginx. Path is sent verbatim (CURLOPT_PATH_AS_IS) so dot-segments and
     * encoded slashes reach nginx un-normalised — that is the whole point of the bypass probes. Redirects are
     * NOT followed (a 301/302 would mask the real status). Returns [status, body].
     *
     * @return array{0:int, 1:string}
     */
    private function httpGet(string $url) : array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_FOLLOWLOCATION  => false,
            CURLOPT_PATH_AS_IS      => true,
            CURLOPT_SSL_VERIFYPEER  => false,   // dev cert
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 20,
        ]);

        $body   = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

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
            $this->markTestSkipped("Target $base is not reachable from this host — skipping special-pages internal-only guard.");
        }
        if( $home === 401 ) {
            $this->markTestSkipped("Target $base answers 401 (basic-auth gate) from this host — run from a whitelisted IP.");
        }

        return $base;
    }
}
