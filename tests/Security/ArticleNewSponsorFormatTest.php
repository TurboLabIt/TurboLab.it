<?php
namespace App\Tests\Security;

use App\Controller\BaseController;
use App\Controller\Editor\ArticleNewController;
use App\Security\phpBBCookiesAuthenticator;
use App\Service\Cms\Article;
use App\Service\Cms\Tag;
use App\Service\User;
use App\Tests\BaseT;
use Doctrine\DBAL\Connection;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;


class ArticleNewSponsorFormatTest extends BaseT
{
    private const string TITLE_PREFIX       = 'Test anti-regressione formato sponsor numero 42';
    private const string FAKE_PHPBB_SID     = '42a1c0de00000000000000000000cafe';
    private const string FAKE_PHPBB_RAW_KEY = 'sponsor-42-regression-autologin!';

    private array $arrCreatedArticleIds = [];
    private array $arrForgedUserIds     = [];


    public function testNonEditorCannotUseTheSponsorPseudoFormat() : void
    {
        // ID_TESTER is a plain REGISTERED phpBB user: no ADMINISTRATORS/TLI-Staff/GLOBAL_MODERATORS, user_type 0
        $crawler = $this->loginAndOpenTheNewArticlePage(User::ID_TESTER);

        // 1️⃣ the sponsor radio must not even be in the DOM
        $this->assertCount(
            0, $this->filterSponsorRadio($crawler),
            'SECURITY REGRESSION (finding #42): the "Sponsor" radio is rendered on /scrivi for a NON-editor.'
        );

        // 2️⃣ ...and the endpoint must refuse it anyway, even when the radio is forged back in by hand
        $title = $this->submitNewArticle($crawler, Article::FORMAT_ACTION_SPONSOR);

        $response = static::$client->getResponse();

        $this->assertFalse(
            $response->isRedirect(),
            'SECURITY REGRESSION (finding #42): POSTing new-article-format=22 as a NON-editor redirected to a ' .
            'newly created article. ArticleNewController::submit() is taking the sponsor branch without ' .
            'checking isEditor(): any registered user can publish a draft signed `System` and tagged Sponsor'
        );

        $this->assertGreaterThanOrEqual(
            400, $response->getStatusCode(),
            'The sponsor POST of a NON-editor was not rejected (HTTP ' . $response->getStatusCode() . '): 22 must ' .
            'stay an unrecognized format and be refused, as any other invalid value'
        );

        // 3️⃣ the DB is the ground truth: nothing was written, whatever the response looked like
        $this->assertSame(
            0, $this->countArticlesByTitle($title),
            'SECURITY REGRESSION (finding #42): the sponsor POST of a NON-editor created an article anyway ' .
            '(title: ' . $title . ')'
        );
    }


    public function testEditorCanStillUseTheSponsorPseudoFormat() : void
    {
        // ID_DEFAULT_ADMIN is a founder (user_type 3) ➡ ROLE_ADMIN + ROLE_EDITOR
        $crawler = $this->loginAndOpenTheNewArticlePage(User::ID_DEFAULT_ADMIN);

        // 1️⃣ for an editor the radio is in the DOM (still `d-none`: the frontend takes care of showing it)
        $this->assertCount(
            1, $this->filterSponsorRadio($crawler),
            'The "Sponsor" radio is missing from /scrivi for an EDITOR: the `currentUserIsEditor` gate added ' .
            'for finding #42 is too strict (or the template variable is no longer passed by ArticleNewController)'
        );

        // 2️⃣ ...and the pseudo-format still works end to end
        $title      = $this->submitNewArticle($crawler, Article::FORMAT_ACTION_SPONSOR);
        $response   = static::$client->getResponse();

        $this->assertTrue(
            $response->isRedirect(),
            'The sponsor POST of an EDITOR did not redirect to the new article (HTTP ' . $response->getStatusCode() .
            '): the finding #42 gate broke the feature for who is entitled to use it'
        );

        $location = rtrim( (string)$response->headers->get('Location'), '/' );
        $this->assertMatchesRegularExpression('/-(\d+)$/', $location, "Unexpected redirect target: $location");
        preg_match('/-(\d+)$/', $location, $arrMatches);

        $articleId = (int)$arrMatches[1];
        $this->arrCreatedArticleIds[] = $articleId;

        $conn = $this->getConnection();

        $this->assertSame(
            Article::FORMAT_NEWS, (int)$conn->fetchOne("SELECT format FROM article WHERE id = ?", [$articleId]),
            'The sponsor article was not stored as FORMAT_NEWS'
        );

        $this->assertSame(
            User::ID_SYSTEM,
            (int)$conn->fetchOne("SELECT user_id FROM article_author WHERE article_id = ?", [$articleId]),
            'The sponsor article is not authored by SYSTEM'
        );

        $this->assertNotFalse(
            $conn->fetchOne("SELECT 1 FROM article_tag WHERE article_id = ? AND tag_id = ?", [$articleId, Tag::ID_SPONSOR]),
            'The sponsor article did not get the Sponsor tag'
        );
    }


    //<editor-fold defaultstate="collapsed" desc="*** 👷 Fixtures: forged phpBB login + form submit ***">
    /**
     * Boots a browser authenticated as $userId via forged phpBB cookies and returns the /scrivi crawler.
     * Symfony-side cookie auth (UserRepository::findOneByUserSidKey) only needs matching rows in phpbb_sessions +
     * phpbb_sessions_keys (key_id = md5 of the raw "k" cookie), so no real phpBB login is involved.
     */
    private function loginAndOpenTheNewArticlePage(int $userId) : Crawler
    {
        static::ensureKernelShutdown();
        static::$client = static::createClient();
        static::$client->setServerParameter('HTTP_HOST', $_ENV["APP_SITE_DOMAIN"]);
        static::$client->setServerParameter('HTTPS', 'https');

        $conn = $this->getConnection();

        $conn->executeStatement(
            "REPLACE INTO " . $this->forumTable('sessions') . "
                (session_id, session_user_id, session_last_visit, session_start, session_time,
                 session_ip, session_browser, session_forwarded_for, session_page, session_autologin)
            VALUES (?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '127.0.0.1', 'phpunit', '', 'index.php', 1)",
            [static::FAKE_PHPBB_SID, $userId]
        );

        $conn->executeStatement(
            "REPLACE INTO " . $this->forumTable('sessions_keys') . " (key_id, user_id, last_ip, last_login)
            VALUES (MD5(?), ?, '127.0.0.1', UNIX_TIMESTAMP())",
            [static::FAKE_PHPBB_RAW_KEY, $userId]
        );

        $this->arrForgedUserIds[] = $userId;

        $cookieBasename = phpBBCookiesAuthenticator::COOKIE_BASENAME_PHPBB;
        $cookieJar      = static::$client->getCookieJar();
        $cookieJar->set( new Cookie($cookieBasename . 'u',   (string)$userId) );
        $cookieJar->set( new Cookie($cookieBasename . 'sid', static::FAKE_PHPBB_SID) );
        $cookieJar->set( new Cookie($cookieBasename . 'k',   static::FAKE_PHPBB_RAW_KEY) );

        $crawler = static::$client->request('GET', '/scrivi');
        $this->assertResponseIsSuccessful("GET /scrivi failed for the forged login of user $userId");

        $this->assertCount(
            1, $crawler->filter('input[name="' . BaseController::CSRF_TOKEN_PARAM_NAME . '"]'),
            "The new-article form is not on /scrivi: the forged phpBB cookie login of user $userId did not " .
            "authenticate (the logged-out template was rendered instead)"
        );

        return $crawler;
    }


    private function filterSponsorRadio(Crawler $crawler) : Crawler
    {
        return $crawler->filter(
            'input[name="' . ArticleNewController::FORMAT_FIELD_NAME . '"]' .
            '[value="' . Article::FORMAT_ACTION_SPONSOR . '"]'
        );
    }


    /**
     * POSTs /scrivi/salva with the CSRF token rendered on $crawler's page and the requested format,
     * bypassing the browser form (the sponsor radio may not be there at all). Returns the submitted title.
     */
    private function submitNewArticle(Crawler $crawler, int $format) : string
    {
        $csrfToken = $crawler->filter('input[name="' . BaseController::CSRF_TOKEN_PARAM_NAME . '"]')->attr('value');
        $this->assertNotEmpty($csrfToken);

        $title = static::TITLE_PREFIX . ': ' . bin2hex(random_bytes(4));

        static::$client->request('POST', '/scrivi/salva', [
            ArticleNewController::TITLE_FIELD_NAME  => $title,
            ArticleNewController::FORMAT_FIELD_NAME => $format,
            BaseController::CSRF_TOKEN_PARAM_NAME   => $csrfToken,
        ]);

        return $title;
    }


    private function countArticlesByTitle(string $title) : int
    {
        return (int)$this->getConnection()->fetchOne("SELECT COUNT(*) FROM article WHERE title = ?", [$title]);
    }


    protected function tearDown() : void
    {
        $conn = $this->getConnection();

        foreach($this->arrCreatedArticleIds as $articleId) {
            // junction rows (article_author, article_tag, ...) go away via ON DELETE CASCADE
            $conn->executeStatement("DELETE FROM article WHERE id = ?", [$articleId]);
        }

        // a FAILED run can create an article before the failing assertion (e.g. a POST that should have been
        // rejected goes through instead): sweep by title so red runs don't leak rows
        $conn->executeStatement("DELETE FROM article WHERE title LIKE ?", [static::TITLE_PREFIX . '%']);

        foreach($this->arrForgedUserIds as $userId) {

            $conn->executeStatement(
                "DELETE FROM " . $this->forumTable('sessions') . " WHERE session_id = ? AND session_user_id = ?",
                [static::FAKE_PHPBB_SID, $userId]
            );

            $conn->executeStatement(
                "DELETE FROM " . $this->forumTable('sessions_keys') . " WHERE key_id = MD5(?) AND user_id = ?",
                [static::FAKE_PHPBB_RAW_KEY, $userId]
            );
        }

        parent::tearDown();
    }


    private function getConnection() : Connection { return static::getEntityManager()->getConnection(); }


    private function forumTable(string $tableName) : string
    {
        return $_ENV["APP_FORUM_DB_NAME"] . ".phpbb_" . $tableName;
    }
    //</editor-fold>
}
