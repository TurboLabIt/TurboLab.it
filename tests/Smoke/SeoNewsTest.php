<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;
use DateTime;
use DOMDocument;
use DOMXPath;
use Symfony\Component\HttpFoundation\Request;


/**
 * What search engines need to treat an article as news: structured data, large image previews
 * and a Google News sitemap.
 */
class SeoNewsTest extends BaseT
{
    protected function extractStructuredData(string $html) : ?array
    {
        if( !preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $arrMatches) ) {
            return null;
        }

        // the edge-case title must survive inside a <script>: nothing in the JSON may close it or open a tag
        $this->assertStringNotContainsString('<', $arrMatches[1]);

        return json_decode($arrMatches[1], true, 512, JSON_THROW_ON_ERROR);
    }


    public function testArticlePageCarriesStructuredDataAndMeta()
    {
        $article = static::getArticle();
        $html    = $this->fetchHtml($article->getUrl(), Request::METHOD_GET, false);

        $this->assertStringContainsString('<meta name="robots" content="index,follow,max-image-preview:large">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta property="article:published_time" content="', $html);
        $this->assertStringContainsString('<meta property="article:modified_time" content="', $html);

        $arrData = $this->extractStructuredData($html);
        $this->assertNotNull($arrData, "No JSON-LD on " . $article->getUrl());

        $this->assertSame('Article', $arrData['@type']);
        $this->assertSame($article->getTitle(), $arrData['headline']);
        $this->assertSame($article->getUrl(), $arrData['mainEntityOfPage']['@id']);
        $this->assertSame($article->getPublishedAt()->format(DATE_ATOM), $arrData['datePublished']);
        $this->assertGreaterThanOrEqual($arrData['datePublished'], $arrData['dateModified']);
        $this->assertNotEmpty($arrData['image'][0]);
        $this->assertNotEmpty($arrData['author'][0]['name']);
        $this->assertSame('TurboLab.it', $arrData['publisher']['name']);
    }


    public function testNewsIsMarkedAsNewsArticle()
    {
        foreach(static::getArticleCollection()->loadLatestNewsPublished() as $article) {

            $this->assertSame('NewsArticle', $article->getStructuredData()['@type']);
            return;
        }

        $this->markTestSkipped('No published news in this database');
    }


    public function testHomePageAllowsLargeImagePreviews()
    {
        $html = $this->fetchHtml('/', Request::METHOD_GET, false);
        $this->assertStringContainsString('<meta name="robots" content="index,follow,max-image-preview:large">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertNull($this->extractStructuredData($html));
    }


    public function testNewsSitemap()
    {
        $xml = $this->fetchHtml('/sitemap/news.xml', Request::METHOD_GET, false);
        $this->assertResponseHeaderSame('content-type', 'application/xml');

        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml), 'The news sitemap is not well-formed XML');

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('news', 'http://www.google.com/schemas/sitemap-news/0.9');

        $this->assertSame('urlset', $doc->documentElement->localName);

        // Google News ignores anything older than two days: the sitemap must not list it
        $oldestAllowed = new DateTime('-2 days -5 minutes');
        foreach($xpath->query('//s:url') as $urlNode) {

            $this->assertNotEmpty($xpath->evaluate('string(s:loc)', $urlNode));
            $this->assertSame('TurboLab.it', $xpath->evaluate('string(news:news/news:publication/news:name)', $urlNode));
            $this->assertSame('it', $xpath->evaluate('string(news:news/news:publication/news:language)', $urlNode));
            $this->assertNotEmpty($xpath->evaluate('string(news:news/news:title)', $urlNode));

            $publishedAt = new DateTime( $xpath->evaluate('string(news:news/news:publication_date)', $urlNode) );
            $this->assertGreaterThanOrEqual($oldestAllowed, $publishedAt);
            $this->assertLessThanOrEqual(new DateTime(), $publishedAt);
        }
    }


    public function testNewsSitemapIsDeclaredInRobotsTxt()
    {
        $robotsTxt = file_get_contents( static::getProjectFilePath('public/misc/robots.txt') );
        $this->assertStringContainsString('Sitemap: https://turbolab.it/sitemap/news.xml', $robotsTxt);
    }
}
