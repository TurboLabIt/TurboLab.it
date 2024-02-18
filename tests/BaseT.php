<?php
namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


abstract class BaseT extends WebTestCase
{
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
        }

        $container = static::getContainer();
        $connector = $container->get($name);
        return $connector;
    }


    protected static function getEntityManager() : EntityManagerInterface
    {
        return static::getService('doctrine.orm.entity_manager');
    }


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



                // article
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
            if( empty($src) || empty(trim($src)) ) {
                continue;
            }

            $this->fetchImage($src);
        }

        return $this;
    }


    protected function internalPaginatorChecker(string $url, ?int $expectedTotalPageNum) : static
    {
        // first page
        $crawler        = $this->fetchDomNode($url, 'article');
        $paginator      = $crawler->filter('div.pagination-container');
        $paginatorHtml  = mb_strtolower($paginator->html());

        $this->assertStringNotContainsString('precedente', $paginatorHtml);
        $this->assertStringNotContainsString('prev', $paginatorHtml);

        $this->assertStringContainsString('successiv', $paginatorHtml);
        $this->assertStringContainsString('next', $paginatorHtml);
        $nextLink = $paginator->filter('.pagination-next > a');
        $nextHref = $nextLink->attr('href');
        $this->assertStringEndsWith('/2', $nextHref);

        // loop on every page but first and last
        for($i = 2; $i < $expectedTotalPageNum; $i++) {

            // URL of the current page. It will be checked against "prev" on the next page
            $nextPagePrevLinkUrl = $url;

            // this is the URL of the page to request and test now
            $url = $nextHref;

            $crawler        = $this->fetchDomNode($url, 'article');
            $paginator      = $crawler->filter('div.pagination-container');
            $paginatorHtml  = mb_strtolower($paginator->html());

            // prev checks
            $this->assertStringContainsString('precedente', $paginatorHtml);
            $this->assertStringContainsString('prev', $paginatorHtml);
            $prevLink = $paginator->filter('.pagination-prev > a');
            $prevHref = $prevLink->attr('href');
            $this->assertEquals($nextPagePrevLinkUrl, $prevHref);

            // next checks
            $this->assertStringContainsString('successiv', $paginatorHtml);
            $this->assertStringContainsString('next', $paginatorHtml);
            $nextLink = $paginator->filter('.pagination-next > a');
            $nextHref = $nextLink->attr('href');
            $this->assertStringEndsWith('/' . $i + 1, $nextHref);
        }

        // URL of the current page. It will be checked against "prev" on the next page
        $nextPagePrevLinkUrl = $url;

        // this is the URL of the page to request and test now
        $lastPageUrl = $nextHref;

        $crawler = $this->fetchDomNode($lastPageUrl, 'article');
        $paginator = $crawler->filter('div.pagination-container');
        $paginatorHtml = mb_strtolower($paginator->html());

        // prev checks
        $this->assertStringContainsString('precedente', $paginatorHtml);
        $this->assertStringContainsString('prev', $paginatorHtml);
        $prevLink = $paginator->filter('.pagination-prev > a');
        $prevHref = $prevLink->attr('href');
        $this->assertEquals($nextPagePrevLinkUrl, $prevHref);

        // next checks
        $this->assertStringNotContainsString('successiv', $paginatorHtml);

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
