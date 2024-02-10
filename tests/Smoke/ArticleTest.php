<?php
namespace Smoke;

use App\Entity\Cms\Article as ArticleEntity;
use App\Factory\Cms\ArticleFactory;
use App\Service\Cms\Article;
use App\Tests\BaseT;
use Symfony\Component\DomCrawler\Crawler;


class ArticleTest extends BaseT
{
    protected static array $arrEntities;


    public function testSpecialArticle()
    {
        // 👀 https://turbolab.it/1939

        /** @var Article $article */
        $article = static::getService("App\\Service\\Cms\\Article");
        $article->load(1939);

        $url = $article->getUrl();
        $crawler = $this->fetchDomNode($url, 'article');

        // H2
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(3, $countH2);

        // intro paragraph
        $firstPContent = $crawler->filter('p')->first()->text();
        $this->assertStringContainsString('Questo è un articolo di prova,', $firstPContent);

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
        if( empty(static::$arrEntities) ) {
            static::$arrEntities = static::getEntityManager()->getRepository(ArticleEntity::class)->findLatestPublished();
        }

        /** @var ArticleFactory $articleFactory */
        $articleFactory = static::getService("App\\Factory\\Cms\\ArticleFactory");

        /** @var ArticleEntity $entity */
        foreach(static::$arrEntities as $entity) {
            yield [[
                "entity"    => $entity,
                "service"   => $articleFactory->create($entity)
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

        $title = $article->getTitle();
        $this->assertNotEmpty($title, $assertFailureMessage);

        $htmlTitle = $crawler->filter('body h1')->html();
        $this->assertEquals($title, $htmlTitle, $assertFailureMessage);
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



            // article
            } elseif(
                static::getService('App\\Service\\Cms\\ArticleUrlGenerator')->isUrl($href) ||
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
