<?php
namespace App\Tests\Smoke;

use App\Entity\Cms\Tag as TagEntity;
use App\Service\Cms\CmsFactory;
use App\Service\Cms\Tag;
use App\Service\Cms\HtmlProcessor;
use App\Tests\BaseT;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;


class TagTest extends BaseT
{
    // 👀 https://turbolab.it/turbolab.it-1
    const SPECIAL_TAG_ID                = 1;
    const TOTAL_PAGE_NUM_OF_SPECIAL_TAG = 3;
    const TOTAL_PAGE_NUM_WINDOWS_TAG    = 61;

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
        $realTagUrl = "http://localhost/windows-10";

        // 👀 https://turbolab.it/windows-10/0
        // 👀 https://turbolab.it/windows-10/1

        foreach([0, 1] as $pageNum) {
            $this->expectRedirect("$realTagUrl/$pageNum", $realTagUrl);
        }
    }


    public function testAppTagLegacy()
    {
        $realTagUrl = "http://localhost/windows-10";

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


    public function testSpecialTag()
    {
        /** @var Tag $tag */
        $tag = static::getService("App\\Service\\Cms\\Tag");
        $tag->load(static::SPECIAL_TAG_ID);
        $url = $tag->getUrl();

        $wrongTagUrl = '/wrong-tag-slug-' . static::SPECIAL_TAG_ID;
        $this->expectRedirect($wrongTagUrl, $url);

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->tagTitleAsH1Checker($tag, $crawler, '#turbolab.it: articoli, guide e news');

        // H2
        $crawler = $this->fetchDomNode($url, 'article');
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(3, $countH2);

        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler)
            ->internalPaginatorChecker($url, static::TOTAL_PAGE_NUM_OF_SPECIAL_TAG);
    }



    public function testWindowsTag()
    {
        // 👀 https://turbolab.it/windows-10

        /** @var Tag $tag */
        $tag = static::getService("App\\Service\\Cms\\Tag");
        $tag->load(10);
        $url = $tag->getUrl();

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->tagTitleAsH1Checker($tag, $crawler, '#windows: articoli, guide e news');

        // H2
        $crawler = $this->fetchDomNode($url, 'article');
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(24, $countH2);

        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler)
            ->internalPaginatorChecker($url, static::TOTAL_PAGE_NUM_WINDOWS_TAG);
    }


    public function test404()
    {
        // 👀 https://turbolab.it/turbolab.it-9999
        $this->expect404('/turbolab.it-9999');
    }


    public static function tagToTestProvider()
    {
        if( empty(static::$arrTagEntity) ) {
            static::$arrTagEntity = static::getEntityManager()->getRepository(TagEntity::class)->findLatest();
        }

        /** @var CmsFactory $cmsFactory */
        $cmsFactory = static::getService("App\\Service\\Cms\\CmsFactory");

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
