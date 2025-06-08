<?php
namespace App\Tests\Smoke;

use App\Service\User;
use App\Service\Cms\HtmlProcessor;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;


class AuthorTest extends BaseT
{
    // 👀 https://turbolab.it/utenti/tecniconapoletano
    const int NO_ARTICLES_AUTHOR_ID = 91;

    protected static array $arrTestAuthors;


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
        $realAuthorUrl = $this->generateUrl() . "utenti/zane";

        // 👀 https://turbolab.it/utenti/zane/0
        // 👀 https://turbolab.it/utenti/zane/1

        foreach([0, 1] as $pageNum) {
            $this->expectRedirect("$realAuthorUrl/$pageNum", $realAuthorUrl);
        }
    }


    public static function authorsToTestThoroughlyProvider() : array
    {
        return
            [
                // 👀 https://turbolab.it/utenti/zane
                [
                    "id"            => 2,
                    "authorName"    => "Zane (Gianluigi Zanettini)",
                    "totalPageNum"  => static::USER_ZANE_TOTAL_PAGES
                ]
            ];
    }


    #[DataProvider('authorsToTestThoroughlyProvider')]
    public function testSpecialAuthors(int $id, string $authorName, int $totalPageNum)
    {
        /** @var User $author */
        $author = static::getService("App\\Service\\User");
        $author->load($id);
        $url = $author->getUrl();

        $crawler = $this->fetchDomNode($url);

        // H1
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
            ->internalPaginatorChecker($url, $totalPageNum);
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


    public static function latestArticlesAuthorsProvider() : array
    {
        if( empty(static::$arrTestAuthors) ) {

            $latestArticles =
                static::getService("App\\ServiceCollection\\Cms\\ArticleCollection")
                    ->loadLatestPublished();

            $arrAuthors = [];
            foreach($latestArticles as $article) {

                $authors = $article->getAuthors();
                foreach($authors as $author) {

                    $authorId = $author->getId();
                    $arrAuthors[$authorId] = $author;
                }
            }

            static::$arrTestAuthors = self::repackDataProviderArray($arrAuthors);
        }

        return static::$arrTestAuthors;
    }


    #[DataProvider('latestArticlesAuthorsProvider')]
    public function testOpenLatestArticlesAuthors(User $author)
    {
        static::$client = null;

        $url = $author->getUrl();
        $assertFailureMessage = "Failing URL: $url";
        $this->assertStringEndsWith($author->getUsernameClean(), $url, $assertFailureMessage);

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
