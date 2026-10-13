<?php
namespace App\Tests\Smoke;

use App\Service\Cms\Tag;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;


class TagTest extends BaseT
{
    use TagTitleCheckerTrait;

    protected static array $arrTestTags;


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
}
