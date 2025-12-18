<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\User;
use App\Service\Cms\Article;
use DateTime;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use Doctrine\Persistence\ManagerRegistry;


class UserRepository extends BasePhpBBRepository
{
    const string ENTITY_CLASS       = User::class;
    const string ID_FIELD           = 't.user_id';
    const string DEFAULT_INDEXED_BY = self::ID_FIELD;

    const string AUTHENTICATED_USER_FIELDS  = '
        users.user_id, user_type, username, username_clean,user_email,
        user_avatar_type, user_avatar,
        user_posts, user_colour, user_allow_massemail
    ';

    public function __construct(protected array $arrConfig, ManagerRegistry $registry, private readonly ManagerRegistry $managerRegistry)
    {
        parent::__construct($arrConfig, $registry);
    }


    public function findOneByUserSidKey(int $userId, string $sessionId, string $sessionKey)
    {
        $sql = "
            SELECT
                " . static::AUTHENTICATED_USER_FIELDS . "
            FROM
                " . $this->getPhpBBTableName() . "
            INNER JOIN
                " . $this->getPhpBBTableName('sessions') . "
            ON
                users.user_id = sessions.session_user_id
            INNER JOIN
                " . $this->getPhpBBTableName('sessions_keys') . "
            ON
                users.user_id = sessions_keys.user_id
            WHERE
                users.user_id			= :userId AND
                ## forum/includes/constants.php: USER_NORMAL, USER_FOUNDER
                users.user_type			IN(0, 3) AND
                sessions.session_id		= :sessionId AND
                sessions_keys.key_id	= :sessionKey
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('userId', $userId, ParameterType::INTEGER);
        $stmt->bindValue('sessionId', $sessionId);
        $stmt->bindValue('sessionKey', md5($sessionKey) );

        return $this->buildUserEntityFromSqlStatement($stmt);
    }


    public function findOneByUserSid(int $userId, string $sessionId)
    {
        $sql = "
            SELECT
                " . static::AUTHENTICATED_USER_FIELDS . "
            FROM
                " . $this->getPhpBBTableName() . "
            INNER JOIN
                " . $this->getPhpBBTableName("sessions") . "
            ON
                users.user_id = sessions.session_user_id
            WHERE
                users.user_id			= :userId AND
                ## forum/includes/constants.php: USER_NORMAL, USER_FOUNDER
                users.user_type			IN(0, 3) AND
                sessions.session_id		= :sessionId
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('userId', $userId, ParameterType::INTEGER);
        $stmt->bindValue('sessionId', $sessionId);

        return $this->buildUserEntityFromSqlStatement($stmt);
    }


    protected function buildUserEntityFromSqlStatement(Statement $stmt) : ?User
    {
        $ormResult  = $stmt->executeQuery();
        $arrUser    = $ormResult->fetchAssociative();

        if( empty($arrUser) ) {
            return null;
        }

        $sqlGroups = "
            SELECT
                `groups`.group_id AS id, `groups`.group_name AS name
            FROM
                " . $this->getPhpBBTableName('phpbb_groups') . "
            INNER JOIN
                " . $this->getPhpBBTableName('phpbb_user_group') . "
            ON
                `groups`.group_id = user_group.group_id
            WHERE
                user_group.user_id = :userId;
        ";

        $stmtGroups = $this->getEntityManager()->getConnection()->prepare($sqlGroups);
        $stmtGroups->bindValue('userId', $arrUser["user_id"], ParameterType::INTEGER);

        $ormGroupsResult    = $stmtGroups->executeQuery();
        $arrUserGroups      = $ormGroupsResult->fetchAllKeyValue();

        return
            (new User())
                ->setId( $arrUser["user_id"] )
                ->setUsername( $arrUser["username"] )
                ->setUsernameClean( $arrUser["username_clean"] )
                ->setUserType( $arrUser["user_type"] )
                ->setEmail( $arrUser["user_email"] )
                ->setAvatarType( $arrUser["user_avatar_type"] )
                ->setAvatarFile( $arrUser["user_avatar"] )
                ->setPostNum( $arrUser["user_posts"] )
                ->setColor( $arrUser["user_colour"] )
                ->setAllowMassEmail( $arrUser["user_allow_massemail"] )
                ->setGroups($arrUserGroups);
    }


    public function findNewsletterSubscribers() : array
    {
        return
            $this->createQueryBuilder('t', 't.user_id')
                // forum/includes/constants.php: USER_NORMAL, USER_FOUNDER
                ->andWhere('t.user_type IN(0,3)')
                ->andWhere('t.user_allow_massemail = 1')
                ->getQuery()
                ->getResult();
    }


    public function getAdditionalFields(User $user) : array
    {
        $sql= "
            SELECT *
            FROM " . $this->getPhpBBTableName("profile_fields_data") . "
            WHERE user_id = :userId
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('userId', $user->getId(), ParameterType::INTEGER);
        return $stmt->executeQuery()->fetchAssociative() ?: [];
    }


    public function getByUsernameClean(string $usernameClean) : ?User
    {
        return
            $this->createQueryBuilder('t', 't.user_id')
                // forum/includes/constants.php: USER_NORMAL, USER_FOUNDER
                ->andWhere('t.user_type IN(0,3)')
                ->andWhere('t.username_clean = :username')
                    ->setParameter('username', mb_strtolower($usernameClean))
                ->getQuery()
                ->getOneOrNullResult();
    }


    public function updateSubscriptions(array $arrAddresses, bool $allowMessages, bool $stopTopicNotifications) : static
    {
        if( empty($arrAddresses) ) {
            return $this;
        }

        $arrUserIds =
            $this->getEntityManager()->createQueryBuilder()
                ->select('u.user_id')
                ->from(static::ENTITY_CLASS, 'u')
                ->where('u.user_email IN (:emails)')
                ->setParameter('emails', $arrAddresses)
                ->getQuery()
                ->getScalarResult();

        if( empty($arrUserIds) ) {
            return $this;
        }

        $arrUserIds = array_column($arrUserIds, 'user_id');

        $this->createQueryBuilder('u')
            ->update()
            // newsletter ON|OFF
            ->set('u.user_allow_massemail', (int)$allowMessages)
            // PM email notification ON|OFF
            ->set('u.user_notify_pm', (int)$allowMessages)
            // allow other users to send Email to the user
            ->set('u.user_allow_viewemail', (int)$allowMessages)
            ->where('u.user_id IN (:ids)')
                ->setParameter('ids', $arrUserIds)
            ->getQuery()->execute();

        if(!$stopTopicNotifications) {
            return $this;
        }

        // mark all the watches as "notified, not viewed"
        $csvIds = implode(",", $arrUserIds);
        foreach(["topics_watch", "forums_watch"] as $watchTableName) {

            $fullyQualifiedTableName = $this->arrConfig["forumDatabaseName"] . '.' . static::TABLE_PREFIX . $watchTableName;
            $this->sqlQueryExecute("UPDATE $fullyQualifiedTableName SET notify_status = 1 WHERE user_id IN($csvIds)");
        }

        return $this;
    }


    public function searchByUsername(string $username) : array
    {
        $termToSearch = trim($username);
        if( empty($termToSearch) ) {
            return [];
        }

        return
            $this->getQueryBuilderComplete()
                ->andWhere('t.username_clean LIKE :termToSearch')
                    ->setParameter('termToSearch', '%' . mb_strtolower($this->prepareParamForLikeCondition($termToSearch)) . '%')
                ->orderBy('t.user_posts', 'DESC')
                ->getQuery()
                ->getResult();
    }


    public function findLatestAuthors(int $num = 25) : array
    {
        $arrIds =
            $this->getIdsFromSqlQuery('
                SELECT user_id #, COUNT(1) AS num
                FROM
                    turbolab_it.article_author
                INNER JOIN
                  turbolab_it.article
                ON
                    turbolab_it.article_author.article_id = turbolab_it.article.id
                WHERE
                    article_author.created_at >= NOW() - INTERVAL 1 YEAR AND
                    turbolab_it.article.publishing_status = ' . Article::PUBLISHING_STATUS_PUBLISHED . '
                GROUP BY
                    user_id
                ORDER BY
                    COUNT(1) DESC
                LIMIT ' . $num
            );

        if( empty($arrIds) ) {
            return [];
        }

        return $this->getById($arrIds);
    }


    public function findNewOfTheYear() : array
    {
        $yearStart  = new DateTime('first day of January this year 00:00:00');
        $yearEnd    = new DateTime('last day of December this year 23:59:59');

        return
            $this->getQueryBuilderComplete()
                // forum/includes/constants.php: USER_NORMAL, USER_FOUNDER
                ->andWhere('t.user_type IN(0,3)')
                ->andWhere('t.regDate >= :yearStart')
                    ->setParameter('yearStart', $yearStart->getTimestamp())
                ->andWhere('t.regDate <= :yearEnd')
                    ->setParameter('yearEnd', $yearEnd->getTimestamp())
                ->getQuery()->getResult();
    }


    public function findTopPostersOfTheYear() : array
    {
        $yearStart  = new DateTime('first day of January this year 00:00:00');
        $yearEnd    = new DateTime('last day of December this year 23:59:59');

        $sqlSelectPostsOfTheYear = '
            SELECT poster_id, COUNT(1) AS num FROM ' . $this->getPhpBBTableName('phpbb_posts') . '
            WHERE post_time >= :yearStart AND post_time <= :yearEnd
            GROUP BY poster_id
            ORDER BY num DESC
        ';

        $arrPostersOfTheYear =
            $this->sqlQueryExecute($sqlSelectPostsOfTheYear, [
                'yearStart'   => $yearStart->getTimestamp(),
                'yearEnd'     => $yearEnd->getTimestamp(),
            ])->fetchAllAssociativeIndexed();

        if( empty($arrPostersOfTheYear) ) {
            return [];
        }

        $arrUsers = $this->getById( array_keys($arrPostersOfTheYear) );

        /** @var User $user */
        foreach($arrUsers as $user) {

            $userId = $user->getId();
            $postsOfTheYearNum = $arrPostersOfTheYear[$userId]['num'];
            $user->setPostNum($postsOfTheYearNum);
        }

        return $arrUsers;
    }


    public function findTopAuthorsOfTheYear() : array
    {
        $articlesOfTheYear =
            $this->managerRegistry->getRepository(\App\Entity\Cms\Article::class)->findNewOfTheYear();

        $arrArticleIds = array_map(fn(\App\Entity\Cms\Article $article) => $article->getId(), $articlesOfTheYear);

        if( empty($arrArticleIds) ) {
            return [];
        }

        $sqlSelectArticlesOfTheYear = '
            SELECT user_id, COUNT(1) AS num FROM article_author
            WHERE article_id IN (' . implode(',', $arrArticleIds) . ')
            GROUP BY user_id
            ORDER BY num DESC
        ';

        $arrAuthorsOfTheYear = $this->sqlQueryExecute($sqlSelectArticlesOfTheYear)->fetchAllAssociativeIndexed();

        $arrUsers = $this->getById( array_keys($arrAuthorsOfTheYear) );

        /** @var User $user */
        foreach($arrUsers as &$user) {

            $userId = $user->getId();
            $articlesOfTheYearNum = $arrAuthorsOfTheYear[$userId]['num'];
            $user->setArticlesOfTheYearNum($articlesOfTheYearNum);
        }

        return $arrUsers;
    }


    public function countAllActive() : int
    {
        return
            $this->createQueryBuilder('t')
                ->select('COUNT(t)')
                // forum/includes/constants.php: USER_NORMAL, USER_FOUNDER
                ->andWhere('t.user_type IN(0,3)')
                ->getQuery()->getSingleScalarResult();

    }
}
