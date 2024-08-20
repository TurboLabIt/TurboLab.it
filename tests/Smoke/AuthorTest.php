<?php
namespace App\Tests\Smoke;

use App\Entity\PhpBB\User as UserEntity;
use App\Service\User;
use App\Entity\Cms\Article as ArticleEntity;
use App\Service\Cms\HtmlProcessor;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;


class AuthorTest extends BaseT
{
    // 👀 https://turbolab.it/utenti/tecniconapoletano
    const int NO_ARTICLES_AUTHOR_ID = 91;

    protected static array $arrUserEntity = [];


    public function testingPlayground()
    {
        // 👀 https://turbolab.it/utenti/crazy.cat

        /** @var User $author */
        $author = static::getService("App\\Service\\User");
        $author->load(60);
        $url = $author->getUrl();

        $crawler = $this->fetchDomNode($url);
        $this->authorNameAsH1Checker($author, $crawler);
    }


    public function testAppAuthorPage0Or1()
    {
        $realAuthorUrl = $_ENV["APP_SITE_URL"] . "utenti/zane";

        // 👀 https://turbolab.it/utenti/zane/0
        // 👀 https://turbolab.it/utenti/zane/1

        foreach([0, 1] as $pageNum) {
            $this->expectRedirect("$realAuthorUrl/$pageNum", $realAuthorUrl);
        }
    }


    public static function specialAuthorToTestProvider() : \Generator
    {
        yield [
            // 👀 https://turbolab.it/utenti/zane
            [
                "id"            => 2,
                "name"          => "Zane (Gianluigi Zanettini)",
                "totalPageNum"  => 66
            ],
        ];
    }


    #[DataProvider('specialAuthorToTestProvider')]
    public function testSpecialAuthor(array $arrSpecialAuthor)
    {
        /** @var User $author */
        $author = static::getService("App\\Service\\User");
        $author->load( $arrSpecialAuthor["id"] );
        $url = $author->getUrl();

        $crawler = $this->fetchDomNode($url);

        // H1
        $authorName = $arrSpecialAuthor["name"];
        $this->authorNameAsH1Checker($author, $crawler, "Articoli, guide e news a cura di $authorName");

        // H2
        $crawler = $this->fetchDomNode($url, 'body');
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(24, $countH2);

        // Author bio
        $authorBioBox = $crawler->filter('.tli-article-box');
        $this->assertNotEmpty($authorBioBox);
        $bioBoxHtml = $authorBioBox->html();
        $this->assertNotEmpty($bioBoxHtml);
        $this->assertStringContainsString('<img src="/forum/download/file.php?avatar=', $bioBoxHtml);
        $this->assertNotEmpty( $authorBioBox->filter('.tli-author-bio-name') );
        $this->assertNotEmpty( $authorBioBox->filter('.tli-author-articles-counter') );

        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler)
            ->internalPaginatorChecker($url, $arrSpecialAuthor["totalPageNum"]);
    }


    public function testNoArticlesAuthor()
    {
        /** @var User $author */
        $author = static::getService("App\\Service\\User");
        $author->load( static::NO_ARTICLES_AUTHOR_ID );
        $url = $author->getUrl();

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->authorNameAsH1Checker(
            $author, $crawler,
            "Articoli, guide e news a cura di tecniconapoletano"
        );

        $html = $this->fetchHtml($url);

        // Author bio
        $authorBioBox = $crawler->filter('.tli-article-box');
        $this->assertEmpty($authorBioBox);
        $this->assertStringNotContainsString('<img src="/forum/download/file.php?avatar=', $html);
        $this->assertEmpty( $crawler->filter('.tli-author-bio-name') );
        $this->assertEmpty( $crawler->filter('.tli-author-articles-counter') );
        $this->assertStringContainsString('nessun articolo trovato', $html);
    }


    public function test404()
    {
        // 👀 https://turbolab.it/utenti/mao-mao-now
        $this->expect404('/utenti/mao-mao-now');
    }


    public static function authorToTestProvider(): \Generator
    {
        if( empty(static::$arrUserEntity) ) {

            $arrLatestArticles = static::getEntityManager()->getRepository(ArticleEntity::class)->findLatestPublished(10);

            /** @var ArticleEntity $article */
            foreach($arrLatestArticles as $article) {

                $authors = $article->getAuthors();
                foreach($authors as $authorJunction) {

                    $author     = $authorJunction->getUser();
                    $authorId   = $author->getId();
                    if( !array_key_exists($authorId, static::$arrUserEntity) ) {
                        static::$arrUserEntity[$authorId] = $author;
                    }
                }
            }
        }

        /** @var UserEntity $entity */
        foreach(static::$arrUserEntity as $entity) {
            yield [[
                "entity"    => $entity,
                "service"   => static::getService("App\\Service\\Factory")->createUser($entity)
            ]];
        }
    }


    #[DataProvider('authorToTestProvider')]
    public function testOpenAllAuthors(array $arrData)
    {
        static::$client = null;

        $entity = $arrData["entity"];
        $author = $arrData["service"];

        $url = $author->getUrl();
        $assertFailureMessage = "Failing URL: $url";
        $this->assertStringEndsWith($entity->getUsernameClean(), $url, $assertFailureMessage);

        $crawler = $this->fetchDomNode($url);

        //
        $this->authorNameAsH1Checker($author, $crawler);
    }


    protected function authorNameAsH1Checker(User $author, Crawler $crawler, ?string $expectedH1 = null) : void
    {
        $assertFailureMessage = "Failing URL: " . $author->getUrl();

        $authorName = $author->getFullName();
        $this->assertNotEmpty($authorName, $assertFailureMessage);

        foreach(HtmlProcessor::ACCENTED_LETTERS as $accentedLetter) {

            $accentedLetterEntity = htmlentities($accentedLetter);
            $this->assertStringNotContainsString($accentedLetterEntity, $authorName);
        }

        $this->assertStringNotContainsString('&nbsp;', $authorName);

        $H1FromCrawler = $crawler->filter('body h1')->html();
        $H1FromCrawler = $this->encodeQuotes($H1FromCrawler);
        $this->assertEquals("Articoli, guide e news a cura di $authorName", $H1FromCrawler, $assertFailureMessage);

        if( $expectedH1 !== null ) {
            $this->assertEquals($expectedH1, $H1FromCrawler, "Explict H1 check failure! " . $assertFailureMessage);
        }
    }
}
