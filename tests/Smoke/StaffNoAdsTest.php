<?php
namespace App\Tests\Smoke;

use App\Controller\BaseController;
use App\Security\phpBBCookiesAuthenticator;
use App\Service\User;
use App\Tests\BaseT;
use Doctrine\DBAL\Connection;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;


/**
 * Staff (editors) get no ads on the pages built for their viewer: BaseController::hasAdsForCurrentUser() on the author
 * pages, /info, /statistiche, search, the calendar, /scrivi and the two maintenance pages, the article's own !canEdit()
 * on an article. A registered user who isn't staff gets them there, as a logged-out visitor does.
 *
 * The pages the view cache shares among all visitors (the homepage, /news, tags, views) keep them for staff too: there
 * the copy would keep the choice of whoever built it, and a staff visit right after a publish would leave the page
 * ad-free for everyone. The test env never caches, so a page that started varying by viewer would only show up here.
 *
 * Logged in through forged phpBB cookies, as in ArticleNewSponsorFormatTest: a KernelBrowser login doesn't reach the
 * stateless firewall. BaseT::browse() restarts the client, which drops them: pages go through openKeepingCookies()
 */
class StaffNoAdsTest extends BaseT
{
    private const string FAKE_PHPBB_RAW_KEY_PREFIX = 'tli-test-staff-no-ads-';

    private array $arrForgedUserIds = [];


    public function testStaffGetNoAdsOnThePagesBuiltForThem() : void
    {
        // ID_DEFAULT_ADMIN is a founder (user_type 3) ➡ ROLE_ADMIN + ROLE_EDITOR
        $this->loginWithForgedPhpBBCookies(User::ID_DEFAULT_ADMIN);

        $arrUrls = $this->pagesBuiltForTheViewer();
        $arrUrls['an article, which staff can edit'] = static::getArticleWithAds()->getUrl();

        foreach($arrUrls as $pageName => $url) {
            foreach($this->countAdParts( $this->openKeepingCookies($url) ) as $part => $count) {
                $this->assertSame(0, $count, "Staff must get no ads on $pageName ($url), yet it carries $part");
            }
        }
    }


    public function testLoggedOutVisitorsGetTheAds() : void
    {
        static::ensureKernelShutdown();
        static::$client = static::createClient();
        static::$client->setServerParameter('HTTP_HOST', $_ENV["APP_SITE_DOMAIN"]);
        static::$client->setServerParameter('HTTPS', 'https');

        $this->assertAdsOn($this->pagesBuiltForTheViewer(), 'a logged-out visitor');
    }


    public function testRegisteredUsersWhoAreNotStaffGetTheAds() : void
    {
        // ID_TESTER is a plain REGISTERED phpBB user: no ADMINISTRATORS/TLI-Staff/GLOBAL_MODERATORS, user_type 0
        $this->loginWithForgedPhpBBCookies(User::ID_TESTER);
        $this->assertAdsOn($this->pagesBuiltForTheViewer(), 'a registered user who is not staff');
    }


    public function testStaffStillGetTheAdsOnThePagesCachedForEveryone() : void
    {
        $this->loginWithForgedPhpBBCookies(User::ID_DEFAULT_ADMIN);

        $this->assertAdsOn([
            'the homepage'  => $this->generateUrl('app_home'),
            '/news'         => $this->generateUrl('app_news'),
            'a tag page'    => static::getTag()->getUrl(),
            'a views page'  => $this->generateUrl('app_views_multi', ['slug' => 'tendenza']),
        ], 'staff, on a page the view cache shares among all visitors (it must not vary by viewer)');
    }


    /** the pages that pass hasAdsForCurrentUser() */
    private function pagesBuiltForTheViewer() : array
    {
        return [
            'an author page'        => static::getUser(User::ID_DEFAULT_ADMIN)->getUrl(),
            '/info'                 => $this->generateUrl('app_info'),
            '/statistiche'          => $this->generateUrl('app_stats'),
            'search'                => $this->generateUrl('app_search', ['termToSearch' => 'windows']),
            'the calendar'          => $this->generateUrl('app_calendar'),
            '/scrivi'               => $this->generateUrl('app_article_new'),
            'the files to check'    => $this->generateUrl('app_file_need-fixing'),
            'the orphan images'     => $this->generateUrl('app_image_orphans'),
        ];
    }


    private function assertAdsOn(array $arrUrls, string $viewer) : void
    {
        foreach($arrUrls as $pageName => $url) {
            foreach($this->countAdParts( $this->openKeepingCookies($url) ) as $part => $count) {
                $this->assertGreaterThan(0, $count, "$pageName ($url) must carry $part for $viewer");
            }
        }
    }


    private function openKeepingCookies(string $url) : Crawler
    {
        $crawler = static::$client->request('GET', $url);
        $this->assertResponseIsSuccessful("Failing URL: $url");
        return $crawler;
    }


    //<editor-fold defaultstate="collapsed" desc="*** 👷 Fixtures: forged phpBB login ***">
    /**
     * A fresh browser authenticated as $userId. Symfony-side cookie auth (UserRepository::findOneByUserSidKey) only
     * needs matching rows in phpbb_sessions + phpbb_sessions_keys (key_id = md5 of the raw "k" cookie)
     */
    private function loginWithForgedPhpBBCookies(int $userId) : void
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
            [$this->fakeSessionId($userId), $userId]
        );

        $conn->executeStatement(
            "REPLACE INTO " . $this->forumTable('sessions_keys') . " (key_id, user_id, last_ip, last_login)
            VALUES (MD5(?), ?, '127.0.0.1', UNIX_TIMESTAMP())",
            [self::FAKE_PHPBB_RAW_KEY_PREFIX . $userId, $userId]
        );

        $this->arrForgedUserIds[] = $userId;

        $cookieBasename = phpBBCookiesAuthenticator::COOKIE_BASENAME_PHPBB;
        $cookieJar      = static::$client->getCookieJar();
        $cookieJar->set( new Cookie($cookieBasename . 'u',   (string)$userId) );
        $cookieJar->set( new Cookie($cookieBasename . 'sid', $this->fakeSessionId($userId)) );
        $cookieJar->set( new Cookie($cookieBasename . 'k',   self::FAKE_PHPBB_RAW_KEY_PREFIX . $userId) );

        // a login that didn't take would pass the registered user's test and fail the staff ones for the wrong reason
        $this->assertCount(
            1, $this->openKeepingCookies( $this->generateUrl('app_article_new') )
                ->filter('input[name="' . BaseController::CSRF_TOKEN_PARAM_NAME . '"]'),
            "The forged phpBB cookie login of user $userId did not authenticate: /scrivi rendered the logged-out template"
        );
    }


    // any 32-char id works: the Symfony-side lookup only joins sessions + sessions_keys by exact value
    private function fakeSessionId(int $userId) : string { return md5(self::class . $userId); }


    protected function tearDown() : void
    {
        $conn = $this->getConnection();

        foreach($this->arrForgedUserIds as $userId) {

            $conn->executeStatement(
                "DELETE FROM " . $this->forumTable('sessions') . " WHERE session_id = ? AND session_user_id = ?",
                [$this->fakeSessionId($userId), $userId]
            );

            $conn->executeStatement(
                "DELETE FROM " . $this->forumTable('sessions_keys') . " WHERE key_id = MD5(?) AND user_id = ?",
                [self::FAKE_PHPBB_RAW_KEY_PREFIX . $userId, $userId]
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
