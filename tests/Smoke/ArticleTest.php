<?php
namespace Smoke;

use App\Entity\Cms\Article as ArticleEntity;
use App\Factory\Cms\ArticleFactory;
use App\Service\Cms\Article;
use App\Tests\BaseT;


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
        $domHtml = $this->fetchDomNode($url, 'article');

        // H2
        $countH2 = $domHtml->filter('h2')->count();
        $this->assertGreaterThan(3, $countH2);

        // intro paragraph
        $firstPContent = $domHtml->filter('p')->first()->text();
        $this->assertStringContainsString('Questo è un articolo di prova,', $firstPContent);

        // summary
        $summaryLi = $domHtml->filter('ul')->first()->filter('ul')->filter('li');
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
        $iframes = $domHtml->filter('iframe');
        $countYouTubeIframe = 0;
        foreach($iframes as $iframe) {

            $src = $iframe->getAttribute("src");
            if( stripos($src, 'youtube-nocookie.com/embed') !== false ) {
                $countYouTubeIframe++;
            }
        }

        $this->assertGreaterThanOrEqual(2, $countYouTubeIframe);

        // formatting styles
        $formattingStylesOl = $domHtml->filter('ol')->first()->filter('li');
        $arrExpectedNodes = [1 => 'strong', 2 => 'em', 3 => 'code', 4 => 'ins'];
        foreach($arrExpectedNodes as $index => $expectedTagName) {

            $nodeTagName = $formattingStylesOl->getNode($index - 1)->firstChild->tagName;
            $this->assertEquals($expectedTagName, $nodeTagName);
        }

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
        $this->assertStringEndsWith("/" . $entity->getId(), $shortUrl);

        $url = $article->getUrl();
        $this->assertStringEndsWith("-" . $entity->getId(), $url);

        $this->expectRedirect($shortUrl, $url);

        $domHtml = $this->fetchDomNode($url);

        $title = $article->getTitle();
        $this->assertNotEmpty($title);
        $this->assertEquals($title, $domHtml->filter('body h1')->html() );
    }
}
