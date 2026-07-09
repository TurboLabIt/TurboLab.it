<?php
namespace App\Tests\Smoke;

use App\Service\Cms\Article;
use App\Service\Cms\Paginator;
use App\Service\Factory;
use App\Service\User;
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
        $author = static::getUser(60);
        $url    = $author->getUrl();

        $crawler = $this->fetchDomNode($url);
        $this->authorNameAsH1Checker($author, $crawler, "Articoli, guide e news a cura di crazy.cat (Marco Ricci)");
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
                    // /forum/download/file.php?avatar=
                    "avatarUrl"     => 'https://gravatar.com/avatar/c881a95deb9db1a4e71e87caa2156ed2b9bddc393c08f8da924ce1145ef3f3a7?s=128&amp;r=pg&amp;d=identicon'
                ]
            ];
    }


    #[DataProvider('authorsToTestThoroughlyProvider')]
    public function testSpecialAuthors(int $id, string $authorName, string $avatarUrl)
    {
        $author     = static::getUser($id);
        $url        = $author->getUrl();
        $authorName = $author->getFullName();

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
        $this->assertStringContainsString('<img src="' . $avatarUrl . '"', $bioBoxHtml);
        $this->assertNotEmpty( $authorBioBox->filter('.tli-author-bio-name') );
        $this->assertNotEmpty( $authorBioBox->filter('.tli-author-articles-counter') );

        $totalPageNum = static::calculateExpectedTotalPages( $author->getArticlesPublished()->countTotalBeforePagination() );

        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler)
            ->internalPaginatorChecker($url, $totalPageNum);
    }


    public function testNoArticlesAuthor()
    {
        $author = static::getUser(static::NO_ARTICLES_AUTHOR_ID);
        $url    = $author->getUrl();

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->authorNameAsH1Checker($author, $crawler, "Pagina dell&apos;utente tecniconapoletano");

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


    /**
     * Editors browsing an author page get every article, regardless of the publishing status.
     * Driven at the service layer: the phpBB-cookie authenticator can't be exercised over HTTP in tests.
     * This also guards countByAuthor() against regressing to the all-statuses count when a status is given.
     */
    public function testAuthorArticlesLoadAllIncludesUnpublished()
    {
        // any draft will do: pick the most recently updated one (with at least one author)
        $draftArticle   = null;
        $author         = null;
        foreach( static::getArticleCollection()->loadDrafts() as $candidateDraft ) {

            $arrAuthors = $candidateDraft->getAuthors();
            if( !empty($arrAuthors) ) {

                $draftArticle   = $candidateDraft;
                $author         = reset($arrAuthors);
                break;
            }
        }

        if( empty($draftArticle) ) {
            $this->markTestSkipped('No draft article (with authors) in the database');
        }

        $this->assertSame(Article::PUBLISHING_STATUS_DRAFT, $draftArticle->getPublishingStatus());

        $countAll       = $author->getArticles()->countTotalBeforePagination();
        $countPublished = $author->getArticlesPublished()->countTotalBeforePagination();

        // the author has at least one draft => the all-statuses listing must be bigger than the published-only one
        $this->assertGreaterThan(
            $countPublished, $countAll,
            "The all-statuses listing must include unpublished articles (author: " . $author->getUsernameClean() . ")"
        );

        // same invariant on the counters (countByAuthor() used to ignore the requested status)
        $this->assertSame($countAll, $author->getArticlesNum(false));
        $this->assertGreaterThan($author->getArticlesPublishedNum(false), $author->getArticlesNum(false));

        // the draft must actually be listed: drafts have no publishedAt => they sort last => search backwards
        $factory        = static::getService(Factory::class);
        $itemsPerPage   = static::getService(Paginator::class)->getItemsPerPageNum();
        $totalPages     = max(1, (int)ceil($countAll / $itemsPerPage));

        $foundOnPage = null;
        for( $pageNum = $totalPages; $pageNum >= 1 && $foundOnPage === null; $pageNum-- ) {

            $pageArticles = $factory->createArticleAuthorCollection($author)->loadAll($pageNum);
            foreach($pageArticles as $article) {

                if( $article->getId() == $draftArticle->getId() ) {

                    $foundOnPage = $pageNum;
                    break;
                }
            }
        }

        $this->assertNotNull(
            $foundOnPage,
            "Draft article " . $draftArticle->getId() . " not listed by loadAll() " .
            "(author: " . $author->getUsernameClean() . ")"
        );
    }


    public static function latestArticlesAuthorsProvider() : array
    {
        if( empty(static::$arrTestAuthors) ) {

            $latestArticles = static::getArticleCollection()->loadLatestPublished();

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

        $authorNameInUrl = rawurlencode($author->getUsernameClean());

        $this->assertStringEndsWith($authorNameInUrl, $url, $assertFailureMessage);

        $crawler = $this->fetchDomNode($url);

        //
        $authorName = $author->getFullNameForHTMLAttribute();
        $this->authorNameAsH1Checker($author, $crawler, "Articoli, guide e news a cura di $authorName");
    }


    protected function authorNameAsH1Checker(User $author, Crawler $crawler, string $expectedH1) : void
    {
        $assertFailureMessage = "Failing URL: " . $author->getUrl();

        $authorName = $author->getFullName();
        $this->assertNotEmpty($authorName, $assertFailureMessage);
        $this->assertNoLegacyEntities($authorName);

        $H1FromCrawler = $crawler->filter('body h1')->html();
        // workaround: the crawler decodes entities automatically
        $H1FromCrawlerNormalized = $this->encodeQuotes($H1FromCrawler);
        $this->assertEquals($expectedH1, $H1FromCrawlerNormalized, "Explict H1 check failure! " . $assertFailureMessage);
    }
}
