<?php
namespace App\Tests\Security;

use App\Controller\BaseController;
use App\Controller\Editor\ArticleNewController;
use App\Security\phpBBCookiesAuthenticator;
use App\Service\Cms\Article;
use App\Service\User;
use App\Tests\BaseT;
use Doctrine\DBAL\Connection;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;


/**
 * 🔒 REGRESSION GUARD — docs/security-audit.md finding #17 + issue #56
 * ("Nuovo editor va in errore dopo il salva", 2025-09-05).
 *
 * The CSRF check on /scrivi/salva was disabled for months because it failed deterministically — but ONLY for
 * NON-FOUNDER staff (phpBB user_type ≠ 3 in ADMINISTRATORS / TLI-Staff / GLOBAL_MODERATORS). Root cause: with a
 * stateful firewall, ContextListener::refreshUser() re-loaded the user via the entity provider WITHOUT phpBB
 * groups, so getRoles() shrank ([USER,ADMIN,EDITOR] ➡ [USER]), hasUserChanged() deauthenticated the token on
 * EVERY request, and the session-fixation MIGRATE strategy then wiped the whole CSRF token storage each time:
 * the POST erased the very token it was about to validate ➡ 403. Founders (user_type = 3, i.e. Zane) derive the
 * same roles on both paths, so the bug was invisible to them. Fix: `stateless: true` on the firewall.
 *
 * This test drives the real HTTP flow (forged phpBB session cookies, like a real browser) as a GROUP-privileged
 * non-founder — the exact class of user that was broken — and pins both properties:
 *
 *   - a POST with a tampered token ➡ 403 ........ the CSRF check is ON (finding #17 stays fixed);
 *   - a POST with the token from a PREVIOUSLY rendered page, after ANOTHER request has run in
 *     between ➡ redirect (article created) ....... the token storage survives across requests
 *                                                  (issue #56 stays fixed: under the old bug ANY
 *                                                  intervening request wiped it, making every
 *                                                  legitimate submit fail).
 *
 * A 403 on the valid-token POST means the deauth/migrate/wipe loop is back (stateful firewall restored, or
 * roles diverging again between login-time and refresh-time hydration). A non-403 on the tampered-token POST
 * means someone disabled validateCsrfToken() again.
 *
 * The group membership for SYSTEM, the phpBB session rows and the created draft are temporary and removed in
 * tearDown().
 */
class ArticleNewCsrfTest extends BaseT
{
    // any 32-char id works: the Symfony-side lookup only joins sessions + sessions_keys by exact value
    private const string FAKE_PHPBB_SID     = 'c1aade5600000000000000000000cafe';
    private const string FAKE_PHPBB_RAW_KEY = 'csrf-56-regression-autologin-key';
    private const string STAFF_GROUP_NAME   = 'ADMINISTRATORS';

    private bool $groupMembershipAdded  = false;
    private ?int $createdArticleId      = null;


    public function testGroupStaffCanPassCsrfOnNewArticleSubmit() : void
    {
        $this->createBrowserWithForgedPhpBBLogin();

        // 1️⃣ GET /scrivi as the staff member: must render the new-article form with the CSRF token
        $crawler = static::$client->request('GET', '/scrivi');
        $this->assertResponseIsSuccessful('GET /scrivi failed for the forged staff login');

        $tokenInput = $crawler->filter('input[name="' . BaseController::CSRF_TOKEN_PARAM_NAME . '"]');
        $this->assertCount(
            1, $tokenInput,
            'The new-article form (with its CSRF hidden input) is not on /scrivi — the forged phpBB ' .
            'cookie login did not authenticate: the logged-out template was rendered instead'
        );
        $csrfToken = $tokenInput->attr('value');
        $this->assertNotEmpty($csrfToken);

        // 2️⃣ tampered token ➡ must be rejected: proves validateCsrfToken() is enabled (finding #17)
        static::$client->request('POST', '/scrivi/salva', $this->buildSubmitFields('tampered-token'));
        $this->assertResponseStatusCodeSame(
            Response::HTTP_FORBIDDEN,
            'SECURITY REGRESSION (finding #17): a POST to /scrivi/salva with a TAMPERED CSRF token was not ' .
            'rejected with 403 — validateCsrfToken() has been disabled or weakened on ArticleNewController::submit()'
        );
        $this->assertStringContainsStringIgnoringCase(
            'csrf', (string)static::$client->getResponse()->getContent(),
            'The tampered-token POST was rejected, but not by the CSRF check — verify what produced this 403'
        );

        // 3️⃣ navigate again: under the old bug (issue #56) ANY authenticated request wiped the CSRF storage,
        //    killing every token already rendered into an open form
        static::$client->request('GET', '/scrivi');
        $this->assertResponseIsSuccessful();

        // 4️⃣ submit with the token extracted in step 1 ➡ must create the draft and redirect
        static::$client->request('POST', '/scrivi/salva', $this->buildSubmitFields($csrfToken));
        $response = static::$client->getResponse();

        $this->assertNotSame(
            Response::HTTP_FORBIDDEN, $response->getStatusCode(),
            'REGRESSION (issue #56): a POST to /scrivi/salva with the VALID token of a previously rendered ' .
            'page was rejected with 403 for a group-privileged non-founder staff user. The per-request ' .
            'deauthenticate ➡ session-migrate ➡ CSRF-wipe loop is back: check that the tli_phpbb_cookies ' .
            'firewall is still stateless and that login-time and refresh-time roles still match'
        );

        $this->assertTrue(
            $response->isRedirect(),
            'The valid-token POST did not redirect to the new article (got HTTP ' . $response->getStatusCode() . ')'
        );

        // capture the created draft id (URL ends with -{articleId}) for cleanup
        $location = rtrim( (string)$response->headers->get('Location'), '/' );
        $this->assertMatchesRegularExpression('/-(\d+)$/', $location, "Unexpected redirect target: $location");
        preg_match('/-(\d+)$/', $location, $arrMatches);
        $this->createdArticleId = (int)$arrMatches[1];
    }


    //<editor-fold defaultstate="collapsed" desc="*** 👷 Fixtures: forged phpBB login for a non-founder staff member ***">
    /**
     * Boots a browser authenticated as SYSTEM via forged phpBB cookies, with SYSTEM temporarily promoted to
     * ADMINISTRATORS: a group-privileged NON-founder — the user class broken by issue #56. Symfony-side cookie
     * auth (UserRepository::findOneByUserSidKey) only needs matching rows in phpbb_sessions + phpbb_sessions_keys
     * (key_id = md5 of the raw "k" cookie), so no real phpBB login is involved.
     */
    private function createBrowserWithForgedPhpBBLogin() : void
    {
        static::ensureKernelShutdown();
        static::$client = static::createClient();
        static::$client->setServerParameter('HTTP_HOST', $_ENV["APP_SITE_DOMAIN"]);
        static::$client->setServerParameter('HTTPS', 'https');

        $conn = $this->getConnection();

        $groupId = $conn->fetchOne(
            "SELECT group_id FROM " . $this->forumTable('groups') . " WHERE group_name = ?", [static::STAFF_GROUP_NAME]
        );
        $this->assertNotFalse($groupId, static::STAFF_GROUP_NAME . " group not found in the forum DB");

        $alreadyMember = $conn->fetchOne(
            "SELECT 1 FROM " . $this->forumTable('user_group') . " WHERE group_id = ? AND user_id = ?",
            [$groupId, User::ID_SYSTEM]
        );

        if( !$alreadyMember ) {

            $conn->executeStatement(
                "INSERT INTO " . $this->forumTable('user_group') . " (group_id, user_id, group_leader, user_pending)
                VALUES (?, ?, 0, 0)", [$groupId, User::ID_SYSTEM]
            );

            $this->groupMembershipAdded = true;
        }

        $conn->executeStatement(
            "REPLACE INTO " . $this->forumTable('sessions') . "
                (session_id, session_user_id, session_last_visit, session_start, session_time,
                 session_ip, session_browser, session_forwarded_for, session_page, session_autologin)
            VALUES (?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '127.0.0.1', 'phpunit', '', 'index.php', 1)",
            [static::FAKE_PHPBB_SID, User::ID_SYSTEM]
        );

        $conn->executeStatement(
            "REPLACE INTO " . $this->forumTable('sessions_keys') . " (key_id, user_id, last_ip, last_login)
            VALUES (MD5(?), ?, '127.0.0.1', UNIX_TIMESTAMP())",
            [static::FAKE_PHPBB_RAW_KEY, User::ID_SYSTEM]
        );

        $cookieBasename = phpBBCookiesAuthenticator::COOKIE_BASENAME_PHPBB;
        $cookieJar      = static::$client->getCookieJar();
        $cookieJar->set( new Cookie($cookieBasename . 'u',   (string)User::ID_SYSTEM) );
        $cookieJar->set( new Cookie($cookieBasename . 'sid', static::FAKE_PHPBB_SID) );
        $cookieJar->set( new Cookie($cookieBasename . 'k',   static::FAKE_PHPBB_RAW_KEY) );
    }


    private function buildSubmitFields(string $csrfToken) : array
    {
        return [
            ArticleNewController::TITLE_FIELD_NAME  =>
                'Test anti-regressione CSRF numero 56: i redattori riescono a salvare ' . bin2hex(random_bytes(4)),
            ArticleNewController::FORMAT_FIELD_NAME => Article::FORMAT_ARTICLE,
            BaseController::CSRF_TOKEN_PARAM_NAME   => $csrfToken,
        ];
    }


    protected function tearDown() : void
    {
        $conn = $this->getConnection();

        if( !empty($this->createdArticleId) ) {
            // junction rows (article_author, article_tag, ...) go away via ON DELETE CASCADE
            $conn->executeStatement("DELETE FROM article WHERE id = ?", [$this->createdArticleId]);
        }

        // a FAILED run can create a draft before the failing assertion (e.g. a POST that should have been
        // rejected goes through instead): sweep by title so red runs don't leak rows
        $conn->executeStatement(
            "DELETE FROM article WHERE title LIKE 'Test anti-regressione CSRF numero 56%'"
        );

        $conn->executeStatement(
            "DELETE FROM " . $this->forumTable('sessions') . " WHERE session_id = ?", [static::FAKE_PHPBB_SID]
        );

        $conn->executeStatement(
            "DELETE FROM " . $this->forumTable('sessions_keys') . " WHERE key_id = MD5(?)", [static::FAKE_PHPBB_RAW_KEY]
        );

        if( $this->groupMembershipAdded ) {

            $conn->executeStatement(
                "DELETE ug FROM " . $this->forumTable('user_group') . " ug
                INNER JOIN " . $this->forumTable('groups') . " g ON g.group_id = ug.group_id
                WHERE g.group_name = ? AND ug.user_id = ?", [static::STAFF_GROUP_NAME, User::ID_SYSTEM]
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
