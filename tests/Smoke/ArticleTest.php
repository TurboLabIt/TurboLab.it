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
        $article = static::getService("App\\Service\\Cms\\Article")->load(1939);
        $url = $article->getUrl();
        $domHtml = $this->fetchDomNode($url);
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

        static::$client = null;
    }
}
