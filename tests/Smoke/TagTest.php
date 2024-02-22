<?php
namespace App\Tests\Smoke;

use App\Entity\Cms\Tag as TagEntity;
use App\Service\Cms\HtmlProcessor;
use App\Service\Cms\Tag;
use App\Service\CmsFactory;
use App\Tests\BaseT;
use Symfony\Component\DomCrawler\Crawler;


class TagTest extends BaseT
{
    // 👀 https://turbolab.it/something-12600
    const int NO_ARTICLES_TAG_ID = 12600;

    protected static array $arrTagEntity;


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
        $realTagUrl = $_ENV["APP_SITE_URL"] . "windows-10";

        // 👀 https://turbolab.it/windows-10/0
        // 👀 https://turbolab.it/windows-10/1

        foreach([0, 1] as $pageNum) {
            $this->expectRedirect("$realTagUrl/$pageNum", $realTagUrl);
        }
    }


    public function testAppTagLegacy()
    {
        $realTagUrl = $_ENV["APP_SITE_URL"] . "windows-10";

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



    public static function specialTagToTestProvider() : \Generator
    {
        yield [
            // 👀 https://turbolab.it/turbolab.it-1
            [
                "id"            => 1,
                "title"         => "turbolab.it",
                "totalPageNum"  => 3
            ],
            // 👀 https://turbolab.it/windows-10
            [
                "id"            => 10,
                "title"         => "windows",
                "totalPageNum"  => 61
            ],
        ];
    }


    /**
     * @dataProvider specialTagToTestProvider
     */
    public function testSpecialTag(array $arrSpecialTag)
    {
        /** @var Tag $tag */
        $tag = static::getService("App\\Service\\Cms\\Tag");
        $tag->load( $arrSpecialTag["id"] );
        $url = $tag->getUrl();

        $wrongTagUrl = '/wrong-tag-slug-' . $arrSpecialTag["id"];
        $this->expectRedirect($wrongTagUrl, $url);

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->tagTitleAsH1Checker($tag, $crawler, "#" . $arrSpecialTag["title"] . ": articoli, guide e news");

        // H2
        $crawler = $this->fetchDomNode($url, 'article');
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(24, $countH2);

        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler)
            ->internalPaginatorChecker($url, $arrSpecialTag["totalPageNum"]);
    }


    public function testNoArticlesTag()
    {
        /** @var Tag $tag */
        $tag = static::getService("App\\Service\\Cms\\Tag");
        $tag->load( static::NO_ARTICLES_TAG_ID );
        $url = $tag->getUrl();

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->tagTitleAsH1Checker($tag, $crawler, "#tli test tag | @ &amp; òàùèéì # § |!&quot;£$%&amp;/()=?^ &lt; &gt; &quot;double-quoted&quot; &apos;single quoted&apos; \ / | » fine: articoli, guide e news");

        $html = $this->fetchHtml($url);
        $this->assertStringContainsString('nessun contenuto trovato', $html);
    }


    public function test404()
    {
        // 👀 https://turbolab.it/turbolab.it-9999
        $this->expect404('/turbolab.it-9999');
    }


    public static function tagToTestProvider(): \Generator
    {
        if( empty(static::$arrTagEntity) ) {
            static::$arrTagEntity = static::getEntityManager()->getRepository(TagEntity::class)->findLatest();
        }

        /** @var CmsFactory $cmsFactory */
        $cmsFactory = static::getService("App\\Service\\Factory");

        /** @var TagEntity $entity */
        foreach(static::$arrTagEntity as $entity) {
            yield [[
                "entity"    => $entity,
                "service"   => $cmsFactory->createTag($entity)
            ]];
        }
    }


    /**
     * @dataProvider tagToTestProvider
     */
    public function testOpenAllTags(array $arrData)
    {
        static::$client = null;

        $entity = $arrData["entity"];
        $tag    = $arrData["service"];

        $url = $tag->getUrl();
        $assertFailureMessage = "Failing URL: $url";
        $this->assertStringEndsWith("-" . $entity->getId(), $url, $assertFailureMessage);

        $crawler = $this->fetchDomNode($url);

        //
        $this->tagTitleAsH1Checker($tag, $crawler);
    }


    protected function tagTitleAsH1Checker(Tag $tag, Crawler $crawler, ?string $expectedH1 = null) : void
    {
        $assertFailureMessage = "Failing URL: " . $tag->getUrl();

        $title = $tag->getTitle();
        $this->assertNotEmpty($title, $assertFailureMessage);

        foreach(HtmlProcessor::ACCENTED_LETTERS as $accentedLetter) {

            $accentedLetterEntity = htmlentities($accentedLetter);
            $this->assertStringNotContainsString($accentedLetterEntity, $title);
        }

        $this->assertStringNotContainsString('&nbsp;', $title);

        $H1FromCrawler = $crawler->filter('body h1')->html();
        $H1FromCrawler = $this->encodeQuotes($H1FromCrawler);
        $this->assertEquals('#' . $title . ': articoli, guide e news', $H1FromCrawler, $assertFailureMessage);

        if( $expectedH1 !== null ) {
            $this->assertEquals($expectedH1, $H1FromCrawler, "Explict H1 check failure! " . $assertFailureMessage);
        }
    }
}
