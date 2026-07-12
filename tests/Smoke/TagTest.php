<?php
namespace App\Tests\Smoke;

use App\Service\Cms\Tag;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;


class TagTest extends BaseT
{
    protected static array $arrTestTags;
    protected static array $arrCategories;


    public function testingPlayground()
    {
        // 👀 https://turbolab.it/universita-1309
        $tag = static::getTag(1309);
        $url = $tag->getUrl();

        $crawler = $this->fetchDomNode($url);
        $this->tagTitleAsH1Checker($tag, $crawler);
    }


    public function testAppTagPage0Or1()
    {
        $realTagUrl = $this->generateUrl() . "windows-10";

        // 👀 https://turbolab.it/windows-10/0
        // 👀 https://turbolab.it/windows-10/1

        foreach([0, 1] as $pageNum) {
            $this->expectRedirect("$realTagUrl/$pageNum", $realTagUrl);
        }
    }


    public function testAppTagLegacy()
    {
        $realTagUrl = $this->generateUrl() . "windows-10";

        // 👀 https://turbolab.it/tag/windows
        // 👀 https://turbolab.it/tag/windows/

        $this->expectRedirect("/tag/windows", $realTagUrl);
        $this->expectRedirect("/tag/windows/", $realTagUrl);

        // 👀 https://turbolab.it/tag/windows/0
        // 👀 https://turbolab.it/tag/windows/1

        foreach([0, 1] as $pageNum) {
            $this->expectRedirect("/tag/windows/$pageNum", $realTagUrl);
        }
    }


    public static function tagsToTestThoroughlyProvider() : array
    {
        return
            [
                // 👀 https://turbolab.it/turbolab.it-1
                [
                    "id"            => 1,
                    "tagTitle"      => "TurboLab.it",
                ],
                // 👀 https://turbolab.it/windows-10
                [
                    "id"            => 10,
                    "tagTitle"      => "Windows",
                ],
            ];
    }


    #[DataProvider('tagsToTestThoroughlyProvider')]
    public function testSpecialTags(int $id, string $tagTitle)
    {
        $tag = static::getTag($id);
        $url = $tag->getUrl();

        $wrongTagUrl = "/wrong-tag-slug-$id";
        $this->expectRedirect($wrongTagUrl, $url);

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->tagTitleAsH1Checker($tag, $crawler, "$tagTitle: articoli, guide e news");

        // H2
        $crawler = $this->fetchDomNode($url, 'body');
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(24, $countH2);

        $totalPageNum = static::calculateExpectedTotalPages( $tag->getArticles()->countTotalBeforePagination() );

        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler)
            ->internalPaginatorChecker($url, $totalPageNum);
    }


    public function testNoArticlesTag()
    {
        $tag = static::getTag(Tag::ID_TEST_NO_ARTICLES);
        $url = $tag->getUrl();

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->tagTitleAsH1Checker(
            $tag, $crawler,
            'Tli Test Tag | @ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" \'single Quoted\' \ / | » Fine: articoli, guide e news'
        );

        $html = $this->fetchHtml($url);
        $this->assertStringContainsString('nessun articolo trovato', $html);
    }


    public function test404()
    {
        // 👀 https://turbolab.it/turbolab.it-9999
        $this->expect404('/turbolab.it-9999');
    }


    public static function latestArticlesTagsProvider() : array
    {
        if( empty(static::$arrTestTags) ) {

            $latestArticles = static::getArticleCollection()->loadLatestPublished();

            $arrTags = [];
            foreach($latestArticles as $article) {

                $tags = $article->getTags();
                foreach($tags as $tag) {

                    $tagId = $tag->getId();
                    $arrTags[$tagId] = $tag;
                }
            }

            static::$arrTestTags = self::repackDataProviderArray($arrTags);
        }

        return static::$arrTestTags;
    }


    #[DataProvider('latestArticlesTagsProvider')]
    public function testOpenLatestArticlesTags(Tag $tag)
    {
        static::$client = null;

        $url = $tag->getUrl();
        $assertFailureMessage = "Failing URL: $url";
        $this->assertStringEndsWith("-" . $tag->getId(), $url, $assertFailureMessage);

        $crawler = $this->fetchDomNode($url);

        $this->tagTitleAsH1Checker($tag, $crawler);
    }


    public static function categoryProvider() : array
    {
        if( empty(static::$arrCategories) ) {

            $arrData = static::getTagCollection()->loadCategories()->getAll();
            static::$arrCategories = self::repackDataProviderArray($arrData);
        }

        return static::$arrCategories;
    }


    #[DataProvider('categoryProvider')]
    public function testOpenAllCategories(Tag $category)
    {
        static::$client = null;

        $url = $category->getUrl();
        $this->assertStringEndsWith("-" . $category->getId(), $url, "Failing URL: $url");

        $crawler = $this->fetchDomNode($url);
        $this->assertResponseIsSuccessful();
        $this->tagTitleAsH1Checker($category, $crawler);

        // H2
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(10, $countH2);

        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler);
    }


    protected function tagTitleAsH1Checker(Tag $tag, Crawler $crawler, ?string $expectedH1 = null) : void
    {
        $assertFailureMessage = "Failing URL: " . $tag->getUrl();

        $tagTitle = $tag->getNavTitle();
        $this->assertNotEmpty($tagTitle, $assertFailureMessage);
        $this->assertNoLegacyEntities($tagTitle);

        // getNavTitle() now returns the RAW tag title; the <h1> is auto-escaped on render. The DomCrawler
        // serializes & < > as entities (quotes stay raw), matching htmlspecialchars(ENT_NOQUOTES).
        $tagTitleAsRendered = htmlspecialchars($tagTitle, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
        $H1FromCrawler = $crawler->filter('body h1')->html();
        $this->assertEquals("$tagTitleAsRendered: articoli, guide e news", $H1FromCrawler, $assertFailureMessage);

        if( $expectedH1 !== null ) {
            $this->assertEquals($expectedH1, $H1FromCrawler, "Explict H1 check failure! " . $assertFailureMessage);
        }
    }
}
