<?php
namespace App\Tests\Smoke;

use App\Controller\SearchController;
use App\Service\Cms\Article;
use App\Service\Cms\Tag;
use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use SimpleXMLElement;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class SearchTest extends BaseT
{
    public static function legacySearchProvider() : Generator
    {
        yield from [
            ['/cerca/?query=windows', '/cerca/windows'], ['/cerca?query=windows', '/cerca/windows'],
        ];
    }

    #[DataProvider('legacySearchProvider')]
    public function testLegacyRedirect(string $origin, $expected) { $this->expectRedirect($origin, $expected); }


    public static function searchProvider() : Generator
    {
        yield from [
            ['/cerca/windows 11 iso', 'Scaricare Windows 11 DVD/ISO'],
            ['/cerca/windows su usb', 'installare Windows 11 o Windows 10 su chiavetta USB'],
            ['/cerca/siti torrent', 'Siti BitTorrent italiani'],
            ['/cerca/flex', 'installare ChromeOS Flex']
        ];
    }


    #[DataProvider('searchProvider')]
    public function testSearchPage(string $urlToFetch, string $mustContain)
    {
        $this->fetchDomNode($urlToFetch);
    }



    #[DataProvider('searchProvider')]
    public function testSearchAjax(string $urlToFetch, string $mustContain)
    {
        $ajaxUrlToFetch = str_ireplace('/cerca/', '/cerca/ajax/', $urlToFetch);
        $crawler = $this->fetchDomNode($ajaxUrlToFetch, '.card.article-card');
        $count   = $crawler->count();
        $this->assertGreaterThanOrEqual(4, $count);

        $html = $this->fetchHtml($ajaxUrlToFetch, Request::METHOD_GET, false);
        $this->assertStringContainsString($mustContain, $html, "Failing URL: $urlToFetch");
        $this->assertStringNotContainsStringIgnoringCase('Nessun risultato', $html, "Failing URL: $urlToFetch");
    }


    public function testNoResults()
    {
        $urlToFetch = '/cerca/ajax/' . uniqid() . uniqid();

        $crawler = $this->fetchDomNode($urlToFetch, '.alert.alert-warning');
        $count   = $crawler->count();
        $this->assertEquals(1, $count);

        $html = $this->fetchHtml($urlToFetch, Request::METHOD_GET, false);
        $this->assertStringContainsString('Nessun risultato', $html, "Failing URL: $urlToFetch");
    }


    public static function mainPagesProvider() : Generator
    {
        $articleUrl = static::getArticle()->getUrl();
        $tagUrl     = static::getTag(Tag::ID_WINDOWS)->getUrl();

        yield from [['/'], [$articleUrl], [$tagUrl]];
    }


    #[DataProvider('mainPagesProvider')]
    public function testOpenSearchXmlAutodiscovery(string $url)
    {
        $html       = $this->fetchHtml($url);
        $pattern    = '/<link\s+rel="search"\s+type="application\/opensearchdescription\+xml"[^>]+href="([^"]+)"/';
        $arrMatches = [];
        preg_match($pattern, $html, $arrMatches);
        $this->assertNotEmpty($arrMatches, "Autodiscovery HTML tag not found! Failing URL: $url");
        $openSearchXmlUrl = $arrMatches[1];
        $this->assertStringContainsString('/open-search.xml', $openSearchXmlUrl);
        $openSearchXmlUrl = $this->generateUrl() . ltrim($openSearchXmlUrl, '/');

        $httpClient =
            HttpClient::create([
                'max_redirects' => 0,
                'verify_peer'   => false,
                'verify_host'   => false
            ]);

        $response = $httpClient->request('GET', $openSearchXmlUrl);
        $httpStatusCode = $response->getStatusCode();
        $this->assertSame(Response::HTTP_OK, $httpStatusCode);

        $txtXml = $response->getContent();
        $this->assertStringContainsString('/cerca/{searchTerms}', $txtXml);

        $oXml = simplexml_load_string($txtXml);
        $this->assertInstanceOf(SimpleXMLElement::class, $oXml);
        $searchUrl = (string)$oXml->Url["template"];
        $this->assertStringContainsString('/cerca/{searchTerms}', $searchUrl);
    }
}
