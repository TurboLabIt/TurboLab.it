<?php
namespace App\Tests\Smoke;

use App\Entity\Cms\Tag as TagEntity;
use App\Service\Cms\CmsFactory;
use App\Service\Cms\Tag;
use App\Service\Cms\HtmlProcessor;
use App\Tests\BaseT;
use Symfony\Component\DomCrawler\Crawler;


class TagTest extends BaseT
{
    protected static array $arrTagEntity;


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

        $this->expectRedirect("http://localhost/tag/windows", $realTagUrl);
        $this->expectRedirect("http://localhost/tag/windows/", $realTagUrl);

        // 👀 https://turbolab.it/tag/windows/0
        // 👀 https://turbolab.it/tag/windows/1

        foreach([0, 1] as $pageNum) {
            $this->expectRedirect("http://localhost/tag/windows/$pageNum", $realTagUrl);
        }
    }


    public function testingPlayground()
    {
        /** @var Tag $tag */
        $tag = static::getService("App\\Service\\Cms\\Tag");
        $tag->load(1309);

        $url = $tag->getUrl();
        $crawler = $this->fetchDomNode($url);
        $this->tagTitleAsH1Checker($tag, $crawler);
    }






    public function testSpecialTag()
    {
        // 👀 https://turbolab.it/windows-10

        /** @var Tag $tag */
        $tag = static::getService("App\\Service\\Cms\\Tag");
        $tag->load(10);

        $url = $tag->getUrl();
        $crawler = $this->fetchDomNode($url);

        // H1
        $this->tagTitleAsH1Checker($tag, $crawler, 'Come svolgere test automatici su TurboLab.it (verifica dell&apos;impianto &amp; &quot;collaudo&quot;) | @ &amp; òàùèéì # § |!&quot;£$%&amp;/()=?^ &lt; &gt; &quot;double-quoted&quot; &apos;single quoted&apos; \ / | » fine');

        // H2
        $crawler = $this->fetchDomNode($url, 'tag');
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(3, $countH2);

        // intro paragraph
        $firstPContent = $crawler->filter('p')->first()->html();
        $this->assertStringContainsString('Questo è un articolo <em>di prova</em>, utilizzato dai <strong>test automatici</strong>', $firstPContent);

        // summary
        $summaryLi = $crawler->filter('ul')->first()->filter('ul')->filter('li');
        $arrUnmatchedUlContent = [
            'video da YouTube', 'formattazione',
            'link ad altri articoli', 'link a pagine di tag', 'link a file',
            'link alle pagine degli autori', 'caratteri "delicati"',
            'tutte le immagini'
        ];

        foreach($summaryLi as $li) {

            $liText = $li->textContent;

            foreach($arrUnmatchedUlContent as $k => $textToSearch) {

                if( stripos($liText, $textToSearch) !== false ) {

                    unset($arrUnmatchedUlContent[$k]);
                    break;
                }
            }
        }

        $this->assertEmpty($arrUnmatchedUlContent);

        // YouTube
        $iframes = $crawler->filter('iframe');
        $countYouTubeIframe = 0;
        foreach($iframes as $iframe) {

            $src = $iframe->getAttribute("src");
            if( stripos($src, 'youtube-nocookie.com/embed') !== false ) {
                $countYouTubeIframe++;
            }
        }

        $this->assertGreaterThanOrEqual(2, $countYouTubeIframe);

        // formatting styles
        $formattingStylesOl = $crawler->filter('ol')->first()->filter('li');
        $arrExpectedNodes = [1 => 'strong', 2 => 'em', 3 => 'code', 4 => 'ins'];
        foreach($arrExpectedNodes as $index => $expectedTagName) {

            $nodeTagName = $formattingStylesOl->getNode($index - 1)->firstChild->tagName;
            $this->assertEquals($expectedTagName, $nodeTagName);
        }

        //
        $this->internalLinksChecker($crawler);

        // fragile chars
        $fragileCharsH2Text = 'Caratteri "delicati"';
        $fragileCharsActualValue = null;
        foreach($H2s as $h2) {

            $h2Content = $h2->textContent;
            if( $h2Content === $fragileCharsH2Text) {
                $fragileCharsActualValue = $h2->nextSibling->nodeValue;
            }
        }

        $this->assertEquals('@ & òàùèéì # § |!"£$%&/()=?^ < > "double-quoted" \'single quoted\' \ / | » fine', $fragileCharsActualValue);

        //
        $this->internalImagesChecker($crawler);
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

        $entity  = $arrData["entity"];
        $tag = $arrData["service"];

        $shortUrl = $tag->getShortUrl();
        $assertFailureMessage = "Failing URL: $shortUrl";
        $this->assertStringEndsWith("/" . $entity->getId(), $shortUrl, $assertFailureMessage);

        $url = $tag->getUrl();
        $this->assertStringEndsWith("-" . $entity->getId(), $url, $assertFailureMessage);

        $this->expectRedirect($shortUrl, $url);

        $crawler = $this->fetchDomNode($url);

        //
        $this->tagTitleAsH1Checker($tag, $crawler);
    }


    protected function tagTitleAsH1Checker(Tag $tag, Crawler $crawler, ?string $expectedH1 = null) : void
    {
        $assertFailureMessage = "Failing URL: " . $tag->getShortUrl();

        $title = $tag->getTitle();
        $this->assertNotEmpty($title, $assertFailureMessage);

        foreach(HtmlProcessor::ACCENTED_LETTERS as $accentedLetter) {

            $accentedLetterEntity = htmlentities($accentedLetter);
            $this->assertStringNotContainsString($accentedLetterEntity, $title);
        }

        $this->assertStringNotContainsString('&nbsp;', $title);

        $H1FromCrawler = $crawler->filter('body h1')->html();
        $H1FromCrawler = $this->encodeQuotes($H1FromCrawler);
        $this->assertEquals($title, $H1FromCrawler, $assertFailureMessage);

        if( $expectedH1 !== null ) {
            $this->assertEquals($expectedH1, $H1FromCrawler, "Explict H1 check failure! " . $assertFailureMessage);
        }
    }


    protected function internalLinksChecker(Crawler $crawler) : void
    {
        $aNodes = $crawler->filter('a');
        foreach($aNodes as $a) {

            $href = $a->getAttribute("href");
            if( empty($href) || empty(trim($href)) ) {
                continue;
            }

            $checkIt = false;

            // file
            if( stripos($href, "/scarica/") !== false ) {



            // author
            } elseif( stripos($href, "/utenti/") !== false ) {



            // tag
            } elseif(
                static::getService('App\\Service\\Cms\\TagUrlGenerator')->isUrl($href) ||
                static::getService('App\\Service\\Cms\\TagUrlGenerator')->isUrl($href)
            ) {
                $checkIt = true;
            }

            if($checkIt) {
                $this->fetchHtml($href);
            }
        }
    }


    protected function internalImagesChecker(Crawler $crawler) : void
    {
        $imgNodes = $crawler->filter('img');
        foreach($imgNodes as $img) {

            $src = $img->getAttribute("src");
            if( empty($src) || empty(trim($src)) ) {
                continue;
            }

            $this->fetchImage($src);
        }
    }
}
