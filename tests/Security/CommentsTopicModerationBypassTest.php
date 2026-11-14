<?php
namespace App\Tests\Security;

use App\Entity\PhpBB\User as UserEntity;
use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Service\PhpBB\Topic;
use App\Service\User;
use App\Tests\BaseT;
use DateTime;


/**
 * Issue #126: the comments topic is approved when it's created, and its first post shows the article under the
 * name of its author. Opened while the article was still in review, it let a new user post on the forum without
 * the approval phpBB requires for their first posts. Now an article in review gets it only if every author is
 * trusted (staff, or 2+ articles already published in the last 6 months); all the others when an editor
 * publishes them.
 *
 * The articles are never saved: the decision reads only their status and authors. The topics the test opens
 * are deleted in tearDown(). The authors are picked by the SQL below, which restates the rule on its own.
 */
class CommentsTopicModerationBypassTest extends BaseT
{
    private const string FIXTURE_TITLE_PREFIX = 'tli-test-comments-topic-';

    /** @var Topic[] */
    private array $arrTopics = [];


    //<editor-fold defaultstate="collapsed" desc="*** 📰 the article ***">
    public function testANewUsersArticleInReviewGetsNoTopic() : void
    {
        $this->assertNotTrusted(User::ID_TESTER);
        $article = $this->buildArticle([User::ID_TESTER], Article::PUBLISHING_STATUS_READY_FOR_REVIEW);

        $this->assertNull( $this->openTopic($article),
            "The topic of a new user's article must wait for an editor to publish it");
    }


    public function testANewUsersArticleGetsTheTopicWhenPublished() : void
    {
        $this->assertNotTrusted(User::ID_TESTER);
        $article = $this->buildArticle([User::ID_TESTER], Article::PUBLISHING_STATUS_PUBLISHED);

        $this->assertNotNull( $this->openTopic($article), "Publishing the article approves its topic too");
    }


    public function testATrustedAuthorsArticleInReviewGetsTheTopic() : void
    {
        $this->assertTrusted(User::ID_DEFAULT_ADMIN);
        $article = $this->buildArticle([User::ID_DEFAULT_ADMIN], Article::PUBLISHING_STATUS_READY_FOR_REVIEW);

        $this->assertNotNull( $this->openTopic($article),
            "A trusted author's article in review must keep getting its topic right away");
    }


    public function testANewCoAuthorKeepsTheTopicForPublication() : void
    {
        $this->assertTrusted(User::ID_DEFAULT_ADMIN);
        $this->assertNotTrusted(User::ID_TESTER);

        $article =
            $this->buildArticle(
                [User::ID_DEFAULT_ADMIN, User::ID_TESTER], Article::PUBLISHING_STATUS_READY_FOR_REVIEW
            );

        $this->assertNull( $this->openTopic($article),
            "A trusted co-author must not open the forum to a new user's article");
    }


    public function testADraftGetsNoTopic() : void
    {
        $article = $this->buildArticle([User::ID_DEFAULT_ADMIN], Article::PUBLISHING_STATUS_DRAFT);
        $this->assertNull( $this->openTopic($article) );
    }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 👤 the author ***">
    public function testStaffIsTrustedWithoutRecentArticles() : void
    {
        $userId = $this->findAuthor(true, 0, User::TRUSTED_AUTHOR_MIN_ARTICLES - 1);
        $this->assertTrue( $this->loadUser($userId)->isTrustedAuthor(), "Staff member $userId must be trusted" );
    }


    public function testEnoughRecentArticlesMakeATrustedAuthor() : void
    {
        $userId = $this->findAuthor(false, User::TRUSTED_AUTHOR_MIN_ARTICLES, PHP_INT_MAX);
        $this->assertTrue( $this->loadUser($userId)->isTrustedAuthor(), "Author $userId must be trusted" );
    }


    public function testOneArticleLessIsNotEnough() : void
    {
        $userId =
            $this->findAuthor(
                false, User::TRUSTED_AUTHOR_MIN_ARTICLES - 1, User::TRUSTED_AUTHOR_MIN_ARTICLES - 1
            );

        $this->assertFalse( $this->loadUser($userId)->isTrustedAuthor(), "Author $userId must not be trusted" );
    }


    public function testOlderArticlesDontCount() : void
    {
        $userId =
            $this->findAuthor(
                false, 0, User::TRUSTED_AUTHOR_MIN_ARTICLES - 1, User::TRUSTED_AUTHOR_MIN_ARTICLES
            );

        $this->assertFalse( $this->loadUser($userId)->isTrustedAuthor(),
            "Author $userId published long ago only: they must not be trusted");
    }


    public function testTheTesterIsANewUser() : void
    {
        $this->assertNotTrusted(User::ID_TESTER);
        $this->assertFalse( $this->loadUser(User::ID_TESTER)->isTrustedAuthor() );
    }
    //</editor-fold>


    protected function tearDown() : void
    {
        foreach($this->arrTopics as $topic) {
            $topic->delete();
        }

        $this->arrTopics = [];
        parent::tearDown();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 fixtures ***">
    private function buildArticle(array $arrAuthorIds, int $publishingStatus) : ArticleEditor
    {
        $factory = static::getService(Factory::class);

        $article =
            $factory->createArticleEditor()
                ->setTitle( uniqid(self::FIXTURE_TITLE_PREFIX) )
                ->setPublishingStatus($publishingStatus);

        if( $publishingStatus == Article::PUBLISHING_STATUS_PUBLISHED ) {
            $article->setPublishedAt( new DateTime() );
        }

        foreach($arrAuthorIds as $authorId) {
            $article->addAuthor( $this->loadUser($authorId) );
        }

        return $article;
    }


    private function openTopic(ArticleEditor $article) : ?Topic
    {
        $topic = $article->createCommentsTopicPlaceholder()->getCommentsTopic();

        if( $topic !== null ) {
            $this->arrTopics[] = $topic;
        }

        return $topic;
    }


    private function loadUser(int $userId) : User
    {
        return static::getService(Factory::class)->createUser()->load($userId);
    }


    /** [staff (bool), recent (articles published in the window), ever (articles published so far)] */
    private function getAuthorStanding(int $userId) : array
    {
        $arrRows = $this->selectAuthors('user_id = :userId', ['userId' => $userId]);
        $this->assertNotEmpty($arrRows, "User $userId is not an active user");

        return reset($arrRows);
    }


    private function assertTrusted(int $userId) : void
    {
        $arrStanding = $this->getAuthorStanding($userId);

        $this->assertTrue(
            $arrStanding['staff'] || $arrStanding['recent'] >= User::TRUSTED_AUTHOR_MIN_ARTICLES,
            "User $userId must be trusted for this test to mean anything"
        );
    }


    private function assertNotTrusted(int $userId) : void
    {
        $arrStanding = $this->getAuthorStanding($userId);

        $this->assertFalse(
            $arrStanding['staff'] || $arrStanding['recent'] >= User::TRUSTED_AUTHOR_MIN_ARTICLES,
            "User $userId must be a new user for this test to mean anything"
        );
    }


    private function findAuthor(bool $staff, int $minRecent, int $maxRecent, int $minEver = 0) : int
    {
        $arrRows =
            $this->selectAuthors(
                'staff = :staff AND recent BETWEEN :minRecent AND :maxRecent AND ever >= :minEver', [
                    'staff'     => (int)$staff,
                    'minRecent' => $minRecent,
                    'maxRecent' => $maxRecent,
                    'minEver'   => $minEver
                ]
            );

        if( empty($arrRows) ) {

            $this->markTestSkipped(
                "No author " . ($staff ? "in" : "outside") . " the staff with $minRecent-$maxRecent article(s) " .
                "published in the last " . User::TRUSTED_AUTHOR_MONTHS . " months and $minEver+ overall"
            );
        }

        return (int)reset($arrRows)['user_id'];
    }


    private function selectAuthors(string $sqlWhere, array $arrParams) : array
    {
        $usersTable     = static::getEntityManager()->getClassMetadata(UserEntity::class)->getTableName();
        $forumDatabase  = strstr($usersTable, '.', true);
        $published      = Article::PUBLISHING_STATUS_PUBLISHED;
        $months         = User::TRUSTED_AUTHOR_MONTHS;

        $sql = "
            SELECT * FROM (
                SELECT
                    users.user_id,
                    (
                        users.user_type = 3 OR EXISTS(
                            SELECT 1 FROM $forumDatabase.phpbb_user_group AS user_group
                            INNER JOIN $forumDatabase.phpbb_groups AS `groups`
                            ON `groups`.group_id = user_group.group_id
                            WHERE
                                user_group.user_id = users.user_id AND user_group.user_pending = 0 AND
                                `groups`.group_name IN('ADMINISTRATORS', 'TLI-Staff', 'GLOBAL_MODERATORS')
                        )
                    ) AS staff,
                    (
                        SELECT COUNT(DISTINCT article.id) FROM article_author
                        INNER JOIN article ON article.id = article_author.article_id
                        WHERE
                            article_author.user_id = users.user_id AND article.publishing_status = $published AND
                            article.published_at BETWEEN NOW() - INTERVAL $months MONTH AND NOW()
                    ) AS recent,
                    (
                        SELECT COUNT(DISTINCT article.id) FROM article_author
                        INNER JOIN article ON article.id = article_author.article_id
                        WHERE
                            article_author.user_id = users.user_id AND article.publishing_status = $published AND
                            article.published_at <= NOW()
                    ) AS ever
                FROM
                    $usersTable AS users
                WHERE
                    users.user_type IN(0, 3)
            ) AS authors
            WHERE $sqlWhere
            ORDER BY user_id
        ";

        return static::getEntityManager()->getConnection()->executeQuery($sql, $arrParams)->fetchAllAssociative();
    }
    //</editor-fold>
}
