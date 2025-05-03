<?php
namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


abstract class BaseT extends WebTestCase
{
    const int HOME_TOTAL_PAGES          = 171;  // 👀 https://turbolab.it/#contact
    const int NEWS_TOTAL_PAGES          = 44;   // 👀 https://turbolab.it/news#contact
    const int TAG_TLI_TOTAL_PAGES       = 2;    // 👀 https://turbolab.it/turbolab.it-1/#contact
    const int TAG_WINDOWS_TOTAL_PAGES   = 63;   // 👀 https://turbolab.it/windows-10/#contact
    const int USER_ZANE_TOTAL_PAGES     = 45;   // 👀 https://turbolab.it/utenti/zane#contact

    protected static ?KernelBrowser $client = null;
    protected static ?Crawler $crawler;


    protected function tearDown() : void
    {
        self::ensureKernelShutdown();
        static::$crawler = null;
    }


    protected static function getService(string $name)
    {
        // boots the kernel and prevents LogicException:
        // Booting the kernel before calling "WebTestCase::createClient()" is not supported, the kernel should only be booted once.
        if( empty(static::$client) ) {

            static::$client = static::createClient();
            static::$client->setServerParameter('HTTP_HOST', $_ENV["APP_SITE_DOMAIN"]);
            static::$client->setServerParameter('HTTPS', 'https');
        }

        $container = static::getContainer();
        $connector = $container->get($name);
        return $connector;
    }


    protected static function getEntityManager() : EntityManagerInterface
        { return static::getService('doctrine.orm.entity_manager'); }


    protected function generateUrl($name = 'app_home', $arrUrlParams = [], $urlType = UrlGeneratorInterface::ABSOLUTE_URL)
        { return static::getService('router.default')->generate($name, $arrUrlParams, $urlType); }


    protected function fetchHtml(string $url, string $method = Request::METHOD_GET, bool $toLower = true) : string
    {
        $this->browse($url, $method);
        $this->assertResponseIsSuccessful("Failing URL: " . $url);

        $html = static::$client->getResponse()->getContent();
        $this->assertNotEmpty($html, "Failing URL: " . $url);

        if($toLower) {
            $html = mb_strtolower($html);
        }

        return $html;
    }


    protected function fetchImage(string $url, string $contentType = 'image/avif') : string
    {
        // WebTestCase doesn't support fetching static files from /public/ and just returns 404
        // https://stackoverflow.com/a/41518169
        if( stripos($url, '/images/') !== false ) {

            $siteUrl    = trim('https://' . $_ENV["APP_SITE_DOMAIN"], '/');
            $filePath   = getcwd() . '/public' . str_ireplace($siteUrl, '', $url);
            $this->assertFileExists($filePath);
            return file_get_contents($filePath);
        }

        $image = $this->fetchHtml($url, Request::METHOD_GET, false);
        if( !empty($contentType) ) {
            $this->assertResponseHeaderSame('content-type', $contentType);
        }

        return $image;
    }


    public function fetchFormByName(string $url, string $formName) : Form
    {
        return $this->fetchForm($url, "[name=" . $formName . "]");
    }


    public function fetchForm(string $url, string $formSelector) : Form
    {
        $crawler = $this->browse($url);
        $this->assertResponseIsSuccessful("Failure before extracting form from $url");
        $form = $crawler->filter("form" . $formSelector)->form();
        return $form;
    }


    public function browse(string $url, string $method = 'GET') : Crawler
    {
        // prevent "Kernel has already been booted"
        if( empty(static::$client) ) {

            static::$client = static::createClient();
            static::$client->setServerParameter('HTTP_HOST', $_ENV["APP_SITE_DOMAIN"]);
            static::$client->setServerParameter('HTTPS', 'https');

        // prevent add the path twice on second call
        } else {

            static::$client->restart();
        }

        static::$crawler = static::$client->request($method, $url);
        return static::$crawler;
    }


    public function fetchDomNode(string $url, string $selector = "html") : Crawler
    {
        $crawler = $this->browse($url);
        $this->assertResponseIsSuccessful("Failing URL: " . $url);

        $node = $crawler->filter($selector);
        return $node;
    }


    public function expectRedirect(string $urlFirst, string $expectedUrlRedirectTo, int $expectedHttpStatus = Response::HTTP_MOVED_PERMANENTLY)
    {
        $crawler = $this->browse($urlFirst);
        $this->assertResponseRedirects(
            $expectedUrlRedirectTo, $expectedHttpStatus,
            'Redirect failed! URL: ##' . $urlFirst . '## doesn\'t redirect to ##' . $expectedUrlRedirectTo . "##"
        );
    }


    public function expect404(string $url)
    {
        $crawler = $this->browse($url);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND,
            'Expected 404 check failed! URL: ##' . $url . '## doesn\'t return ' . Response::HTTP_NOT_FOUND
        );
    }


    protected function listingChecker(array $arrUrlsToTest, array $arrExpectedStrings) : array
    {
        foreach($arrUrlsToTest as $url) {

            $html = $this->fetchHtml($url);

            foreach($arrExpectedStrings as $expectedString) {

                $expectedString = mb_strtolower($expectedString);
                $this->assertStringContainsString($expectedString, $html, "Failing URL: $url");
            }
        }

        return $arrUrlsToTest;
    }


    protected function encodeQuotes(string $H1FromCrawler) : string
    {
        // workaround for: the quotes are decoded automatically by the crawler - this is unacceptable in a test!
        $arrQuoteEncodeMap = [
            '"' => '&quot;',
            "'" => '&apos;'
        ];

        $H1FromCrawler = str_ireplace(array_keys($arrQuoteEncodeMap), $arrQuoteEncodeMap, $H1FromCrawler);
        return $H1FromCrawler;
    }


    protected function internalLinksChecker(Crawler $crawler) : static
    {
        $aNodes = $crawler->filter('a');
        foreach($aNodes as $a) {

            $href = $a->getAttribute("href");
            if( empty($href) || empty(trim($href)) ) {
                continue;
            }

            // local file: Windows Bootable DVD Generator
            if(
                static::getService('App\\Service\\Cms\\FileUrlGenerator')->isUrl($href) &&
                str_ends_with($href, "/scarica/1")
            ) {
                $file = $this->fetchHtml($href, Request::METHOD_GET, false);
                $this->assertNotEmpty($file);
                $this->assertResponseHeaderSame('content-type', 'application/zip');

                // local file: Estensioni video HEVC (appx 64 bit)
            } else if(
                static::getService('App\\Service\\Cms\\FileUrlGenerator')->isUrl($href) &&
                str_ends_with($href, "/scarica/400") !== false
            ) {
                $file = $this->fetchHtml($href, Request::METHOD_GET, false);
                $this->assertNotEmpty($file);
                $this->assertResponseHeaderSame('content-type', 'application/zip');

                // local file: Batch configurazione macOS in VirtualBox
            } else if(
                static::getService('App\\Service\\Cms\\FileUrlGenerator')->isUrl($href) &&
                str_ends_with($href, "/scarica/362") !== false
            ) {
                $file = $this->fetchHtml($href, Request::METHOD_GET, false);
                $this->assertNotEmpty($file);
                $this->assertResponseHeaderSame('content-type', 'text/x-msdos-batch; charset=UTF-8');

                // remote file (must redirect... somewhere)
            } else if( static::getService('App\\Service\\Cms\\FileUrlGenerator')->isUrl($href) ) {

                $this->browse($href);
                $this->assertResponseRedirects();

                // author
            } elseif( stripos($href, "/utenti/") !== false ) {

                $this->browse($href);
                $this->assertResponseIsSuccessful();

                // article or tag
            } elseif(
                static::getService('App\\Service\\Cms\\ArticleUrlGenerator')->isUrl($href) ||
                static::getService('App\\Service\\Cms\\TagUrlGenerator')->isUrl($href)
            ) {
                $this->fetchHtml($href);
            }
        }

        return $this;
    }


    protected function internalImagesChecker(Crawler $crawler) : static
    {
        $imgNodes = $crawler->filter('img');
        foreach($imgNodes as $img) {

            $src = $img->getAttribute("src");
            $this->assertNotEmpty($src);

            $siteUrl = trim('https://' . $_ENV["APP_SITE_DOMAIN"], '/');
            if( !str_starts_with($src, '/') && !str_starts_with($src, $siteUrl) ) {
                // testing external images, such as YouTube thumbnails, is not supported
                return $this;
            }

            if( str_starts_with($src, '/forum/') || str_starts_with($src, "{$siteUrl}forum/") ) {
                // testing forum images is not supported
                return $this;
            }

            $this->fetchImage($src);
        }

        return $this;
    }


    protected function internalPaginatorChecker(string $url, ?int $expectedTotalPageNum) : static
    {
        // first page
        $crawler        = $this->fetchDomNode($url, 'body');
        $paginator      = $crawler->filter('ul.pagination');

        // "go to the beginning" and "go to the previous page" must be disabled on the first page
        foreach(['tli-pagination-first', 'tli-pagination-prev'] as $class) {

            $li = $paginator->filter('.' . $class);
            $this->assertTrue(
                $li->getNode(0)->hasAttribute('class') &&
                in_array('disabled', explode(' ', $li->attr('class')))
            );

            $link = $li->filter('a')->getNode(0);
            $this->assertEmpty($link);
        }


        // "go to the next page" and "go to the last page" must be available
        foreach(['tli-pagination-next', 'tli-pagination-last'] as $class) {

            $li = $paginator->filter('.' . $class);
            $this->assertTrue(
                $li->getNode(0)->hasAttribute('class') &&
                !in_array('disabled', explode(' ', $li->attr('class')))
            );

            $link = $li->filter('a');
            $this->assertNotEmpty( $link->getNode(0) );

            if( $class != 'tli-pagination-next' ) {
                continue;
            }

            $nextHref = $link->attr('href');
            $this->assertStringEndsWith('/2', $nextHref);
        }


        // loop on every page but first and last
        for($i = 2; $i < $expectedTotalPageNum; $i++) {

            // URL of the current page. It will be checked against "prev" on the next page
            $nextPagePrevLinkUrl = $url;

            // this is the URL of the page to request and test now
            $url = $nextHref;

            $crawler    = $this->fetchDomNode($url, 'body');
            $paginator  = $crawler->filter('ul.pagination');

            // "go to the beginning" and "go to the previous page" must be available on the 2nd+ page
            foreach(['tli-pagination-first', 'tli-pagination-prev'] as $class) {

                $li = $paginator->filter('.' . $class);
                $this->assertTrue(
                    $li->getNode(0)->hasAttribute('class') &&
                    !in_array('disabled', explode(' ', $li->attr('class')))
                );

                $link = $li->filter('a');
                $this->assertNotEmpty( $link->getNode(0) );

                if( $class != 'tli-pagination-prev' ) {
                    continue;
                }

                $prevHref = $link->attr('href');
                $this->assertEquals($nextPagePrevLinkUrl, $prevHref);
            }


            // "go to the next page" and "go to the last page" must be available
            foreach(['tli-pagination-next', 'tli-pagination-last'] as $class) {

                $li = $paginator->filter('.' . $class);
                $this->assertTrue(
                    $li->getNode(0)->hasAttribute('class') &&
                    !in_array('disabled', explode(' ', $li->attr('class')))
                );

                $link = $li->filter('a');
                $this->assertNotEmpty( $link->getNode(0) );

                if( $class != 'tli-pagination-next' ) {
                    continue;
                }

                $nextHref = $link->attr('href');
                $this->assertStringEndsWith('/' . $i + 1, $nextHref);
            }
        }


        // last page
        $lastPageUrl = $nextHref;

        // URL of the current page. It will be checked against "prev" on the next page
        $nextPagePrevLinkUrl = $url;

        $crawler = $this->fetchDomNode($lastPageUrl, 'body');
        $paginator = $crawler->filter('ul.pagination');

        // "go to the beginning" and "go to the previous page" must be available on the last page
        foreach(['tli-pagination-first', 'tli-pagination-prev'] as $class) {

            $li = $paginator->filter('.' . $class);
            $this->assertTrue(
                $li->getNode(0)->hasAttribute('class') &&
                !in_array('disabled', explode(' ', $li->attr('class')))
            );

            $link = $li->filter('a');
            $this->assertNotEmpty( $link->getNode(0) );

            if( $class != 'tli-pagination-prev' ) {
                continue;
            }

            $prevHref = $link->attr('href');
            $this->assertEquals($nextPagePrevLinkUrl, $prevHref);
        }

        // "go to the next page" and "go to the last page" must be disabled on the first page
        foreach(['tli-pagination-next', 'tli-pagination-last'] as $class) {

            $li = $paginator->filter('.' . $class);
            $this->assertTrue(
                $li->getNode(0)->hasAttribute('class') &&
                in_array('disabled', explode(' ', $li->attr('class')))
            );

            $link = $li->filter('a')->getNode(0);
            $this->assertEmpty($link);
        }

        // requesting an over-limit page must redirect to the last page
        $basePageUrl = substr($lastPageUrl, 0, strrpos($lastPageUrl, "/")) . "/";
        foreach([1, 2, 3, 50, 99, 175, 7948] as $lastPageMultiplier)
        {
            $overlimitPageNum =
                $lastPageMultiplier < 5
                    ? ( $expectedTotalPageNum + $lastPageMultiplier )
                    : ( $expectedTotalPageNum * $lastPageMultiplier );


            $overlimitUrl = $basePageUrl . $overlimitPageNum;
            $this->expectRedirect($overlimitUrl, $lastPageUrl, Response::HTTP_FOUND);
        }

        return $this;
    }
}
