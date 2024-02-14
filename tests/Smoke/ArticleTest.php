<?php
namespace App\Tests\Smoke;

use App\Entity\Cms\Article as ArticleEntity;
use App\Service\Cms\Article;
use App\Service\Cms\CmsFactory;
use App\Service\Cms\HtmlProcessor;
use App\Tests\BaseT;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;


class ArticleTest extends BaseT
{
    protected static array $arrArticleEntity;

    public function testingPlayground()
    {
        /** @var Article $article */
        $article = static::getService("App\\Service\\Cms\\Article");
        $article->load(488);

        $url = $article->getUrl();
        $crawler = $this->fetchDomNode($url);
        $this->articleTitleAsH1Checker($article, $crawler);
    }


    public function testSpecialArticle()
    {
        // 👀 https://turbolab.it/1939

        /** @var Article $article */
        $article = static::getService("App\\Service\\Cms\\Article");
        $article->load(1939);

        $url = $article->getUrl();
        $crawler = $this->fetchDomNode($url);

        // H1
        $this->articleTitleAsH1Checker($article, $crawler, 'Come svolgere test automatici su TurboLab.it (verifica dell&apos;impianto &amp; &quot;collaudo&quot;) | @ &amp; òàùèéì # § |!&quot;£$%&amp;/()=?^ &lt; &gt; &quot;double-quoted&quot; &apos;single quoted&apos; \ / | » fine');

        // H2
        $crawler = $this->fetchDomNode($url, 'article');
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


    public static function articleToTestProvider()
    {
        if( empty(static::$arrArticleEntity) ) {
            static::$arrArticleEntity = static::getEntityManager()->getRepository(ArticleEntity::class)->findLatestPublished();
        }

        /** @var CmsFactory $cmsFactory */
        $cmsFactory = static::getService("App\\Service\\Cms\\CmsFactory");

        /** @var ArticleEntity $entity */
        foreach(static::$arrArticleEntity as $entity) {
            yield [[
                "entity"    => $entity,
                "service"   => $cmsFactory->createArticle($entity)
            ]];
        }
    }


    /**
     * @dataProvider articleToTestProvider
     */
    public function testOpenAllArticles(array $arrData)
    {
        static::$client = null;

        $entity  = $arrData["entity"];
        $article = $arrData["service"];

        $shortUrl = $article->getShortUrl();
        $assertFailureMessage = "Failing URL: $shortUrl";
        $this->assertStringEndsWith("/" . $entity->getId(), $shortUrl, $assertFailureMessage);

        $url = $article->getUrl();
        $this->assertStringEndsWith("-" . $entity->getId(), $url, $assertFailureMessage);

        $this->expectRedirect($shortUrl, $url);

        $crawler = $this->fetchDomNode($url);

        //
        $this->articleTitleAsH1Checker($article, $crawler);
    }


    protected function articleTitleAsH1Checker(Article $article, Crawler $crawler, ?string $expectedH1 = null) : void
    {
        $assertFailureMessage = "Failing URL: " . $article->getShortUrl();

        $title = $article->getTitle();
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

            // local file: Windows Bootable DVD Generator || Estensioni video HEVC (appx 64 bit)
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
