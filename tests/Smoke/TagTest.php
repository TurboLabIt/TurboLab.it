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

        /** @var Tag $tag */
        $tag = static::getService("App\\Service\\Cms\\Tag");
        $tag->load(1309);
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
                    "totalPageNum"  => static::TAG_TLI_TOTAL_PAGES
                ],
                // 👀 https://turbolab.it/windows-10
                [
                    "id"            => 10,
                    "tagTitle"      => "Windows",
                    "totalPageNum"  => static::TAG_WINDOWS_TOTAL_PAGES
                ],
            ];
    }


    #[DataProvider('tagsToTestThoroughlyProvider')]
    public function testSpecialTags(int $id, string $tagTitle, int $totalPageNum)
    {
        /** @var Tag $tag */
        $tag = static::getService("App\\Service\\Cms\\Tag");
        $tag->load($id);
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

        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler)
            ->internalPaginatorChecker($url, $totalPageNum);
    }


    public function testNoArticlesTag()
    {
        /** @var Tag $tag */
        $tag = static::getService("App\\Service\\Cms\\Tag");
        $tag->load( Tag::ID_TEST_NO_ARTICLES );
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

            $latestArticles =
                static::getService("App\\ServiceCollection\\Cms\\ArticleCollection")
                    ->loadLatestPublished();

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

            $arrData =
                static::getService("App\\ServiceCollection\\Cms\\TagCollection")
                    ->loadCategories()
                    ->getAll();

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

        $H1FromCrawler = $crawler->filter('body h1')->html();
        $this->assertEquals("$tagTitle: articoli, guide e news", $H1FromCrawler, $assertFailureMessage);

        if( $expectedH1 !== null ) {
            $this->assertEquals($expectedH1, $H1FromCrawler, "Explict H1 check failure! " . $assertFailureMessage);
        }
    }
}
