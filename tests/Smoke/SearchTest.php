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
use Symfony\Component\HttpFoundation\Response;


class SearchTest extends BaseT
{
    public static function legacySearchProvider() : Generator
    {
        yield from [
            ['/cerca/', '/'], ['/cerca', '/'],
            ['/cerca/?query=', '/'], ['/cerca?query=', '/'],
            ['/cerca/?query=windows', '/cerca/windows'], ['/cerca?query=windows', '/cerca/windows'],
        ];
    }


    #[DataProvider('legacySearchProvider')]
    public function testLegacyRedirect(string $origin, $expected)
    {
        $this->expectRedirect($origin, $expected);
    }


    public static function searchProvider() : Generator
    {
        yield from [
            ['/cerca/windows 11 iso', 'Scaricare Windows 11 DVD/ISO'],
            ['/cerca/windows su usb', 'installare Windows 11 o Windows 10 su chiavetta USB'],
            ['/cerca/siti torrent', 'Siti BitTorrent italiani']
        ];
    }


    #[DataProvider('searchProvider')]
    public function testSearch(string $urlToFetch, string $mustContain)
    {
        $crawler = $this->fetchDomNode($urlToFetch);

        foreach(['.google-result' => 5, '.local-result' => 0] as $container => $minResults) {

            $results = $crawler->filter($container);
            $count   = $results->count();
            $this->assertGreaterThan($minResults, $count);

            $html =
                implode('', $results->each(function ($nodeCrawler) {
                    return $nodeCrawler->html();
                }));

            $text = strip_tags($html);

            $this->assertStringContainsString($mustContain, $text, "Failing URL: $urlToFetch");
        }

        $html = $crawler->html();
        $this->assertStringNotContainsString(SearchController::NO_RESULTS_MESSAGE, $html, "Failing URL: $urlToFetch");
    }


    public static function mainPagesProvider() : Generator
    {
        /** @var Article $article */
        $article = static::getService("App\\Service\\Cms\\Article");
        $article->load(Article::ID_QUALITY_TEST);
        $articleUrl = $article->getUrl();

        /** @var Tag $tag */
        $tag = static::getService("App\\Service\\Cms\\Tag");
        $tag->load(Tag::ID_WINDOWS);
        $tagUrl = $tag->getUrl();

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
