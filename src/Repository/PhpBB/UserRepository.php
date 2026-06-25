<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\User;
use App\Service\Cms\Article;
use DateTime;
use Doctrine\DBAL\ArrayParameterType;
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

    protected ?array $arrPhpBBSessionConfig = null;


    public function __construct(protected array $arrConfig, ManagerRegistry $registry, private readonly ManagerRegistry $managerRegistry)
    {
        parent::__construct($arrConfig, $registry);
    }


    public function findOneByUserSidKey(int $userId, string $sessionId, string $sessionKey)
    {
        // max_autologin_time is in DAYS; 0 = autologin keys never expire (phpBB default)
        $maxAutologinDays = $this->getPhpBBSessionConfig()['max_autologin_time'];

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

        // enforce the autologin-key lifetime only when the admin has configured one
        if( $maxAutologinDays > 0 ) {
            $sql .= " AND sessions_keys.last_login >= :minLastLogin";
        }

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('userId', $userId, ParameterType::INTEGER);
        $stmt->bindValue('sessionId', $sessionId);
        $stmt->bindValue('sessionKey', md5($sessionKey) );

        if( $maxAutologinDays > 0 ) {
            $stmt->bindValue('minLastLogin', time() - ($maxAutologinDays * 86400), ParameterType::INTEGER);
        }

        return $this->buildUserEntityFromSqlStatement($stmt);
    }


    public function findOneByUserSid(int $userId, string $sessionId)
    {
        // reject sessions idle longer than phpBB's session_length (deterministic equivalent of phpBB's own GC)
        $minSessionTime = time() - $this->getPhpBBSessionConfig()['session_length'];

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
                sessions.session_id		= :sessionId AND
                sessions.session_time	>= :minSessionTime
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('userId', $userId, ParameterType::INTEGER);
        $stmt->bindValue('sessionId', $sessionId);
        $stmt->bindValue('minSessionTime', $minSessionTime, ParameterType::INTEGER);

        return $this->buildUserEntityFromSqlStatement($stmt);
    }


    /**
     * phpBB's session_length (seconds) and max_autologin_time (days), read live from phpbb_config
     * and cached per request. Drives the session/autologin expiry checks in the auth finders.
     */
    protected function getPhpBBSessionConfig() : array
    {
        if( $this->arrPhpBBSessionConfig !== null ) {
            return $this->arrPhpBBSessionConfig;
        }

        $sql = "
            SELECT config_name, config_value
            FROM " . $this->getPhpBBTableName('config', false) . "
            WHERE config_name IN ('session_length', 'max_autologin_time')
        ";

        $arrRows = $this->getEntityManager()->getConnection()->prepare($sql)->executeQuery()->fetchAllKeyValue();

        return $this->arrPhpBBSessionConfig = [
            'session_length'        => (int)($arrRows['session_length'] ?? 3600),
            'max_autologin_time'    => (int)($arrRows['max_autologin_time'] ?? 0),
        ];
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
        foreach(["topics_watch", "forums_watch"] as $watchTableName) {

            $fullyQualifiedTableName = $this->arrConfig["forumDatabaseName"] . '.' . static::TABLE_PREFIX . $watchTableName;
            $this->sqlQueryExecute(
                "UPDATE $fullyQualifiedTableName SET notify_status = 1 WHERE user_id IN(:userIds)",
                ['userIds' => $arrUserIds], ['userIds' => ArrayParameterType::INTEGER]
            );
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
                LIMIT :limit',
                ['limit' => $num], ["limit" => ParameterType::INTEGER]
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
            $user->setCachedData('postNum', $postsOfTheYearNum);
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
        foreach($arrUsers as $user) {

            $userId = $user->getId();
            $articlesOfTheYearNum = $arrAuthorsOfTheYear[$userId]['num'];
            $user->setArticlesOfTheYearNum($articlesOfTheYearNum);
        }

        return $arrUsers;
    }


    public function countAllActive() : int
    {
        return (int)
            $this->createQueryBuilder('t')
                ->select('COUNT(t)')
                // forum/includes/constants.php: USER_NORMAL, USER_FOUNDER
                ->andWhere('t.user_type IN(0,3)')
                ->getQuery()->getSingleScalarResult();

    }


    /**
     * Returns ['YYYY-MM-DD' => int] — count of phpBB users (USER_NORMAL/USER_FOUNDER, i.e. activated)
     * whose `user_regdate` falls on each day in the inclusive range.
     */
    public function getNewRegistrationsByDay(\DateTimeInterface $start, \DateTimeInterface $end) : array
    {
        $startTs    = (int)$start->format('U');
        $endTs      = (int)$end->format('U') + 86399;       // include end-of-day

        $sql = "
            SELECT
                DATE(FROM_UNIXTIME(users.user_regdate)) AS day,
                COUNT(*) AS cnt
            FROM " . $this->getPhpBBTableName() . "
            WHERE
                users.user_regdate BETWEEN :startTs AND :endTs AND
                users.user_type IN(0, 3)
            GROUP BY day
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('startTs', $startTs, ParameterType::INTEGER);
        $stmt->bindValue('endTs', $endTs, ParameterType::INTEGER);

        $arrResult = [];
        foreach( $stmt->executeQuery()->fetchAllKeyValue() as $day => $cnt ) {
            $arrResult[(string)$day] = (int)$cnt;
        }

        return $arrResult;
    }


    /**
     * Returns the count of users (USER_NORMAL/USER_FOUNDER, i.e. activated) whose `user_regdate`
     * is at or before the given UNIX timestamp.
     */
    public function countActivatedAtTimestamp(int $ts) : int
    {
        return (int)
            $this->createQueryBuilder('t')
                ->select('COUNT(t)')
                // forum/includes/constants.php: USER_NORMAL, USER_FOUNDER
                ->andWhere('t.user_type IN(0, 3)')
                ->andWhere('t.regDate <= :ts')
                    ->setParameter('ts', $ts)
                ->getQuery()->getSingleScalarResult();
    }


    /**
     * Returns ['YYYY-MM-DD' => int] — count of activated users CURRENTLY subscribed to the newsletter
     * (user_allow_massemail = 1) who registered on each day in the inclusive range.
     *
     * Caveat: this is an approximation — phpBB doesn't track historical opt-in/opt-out events, so users
     * who later toggle their newsletter setting only show up under their CURRENT state.
     */
    public function getNewsletterSignupsByDay(\DateTimeInterface $start, \DateTimeInterface $end) : array
    {
        $startTs    = (int)$start->format('U');
        $endTs      = (int)$end->format('U') + 86399;

        $sql = "
            SELECT
                DATE(FROM_UNIXTIME(users.user_regdate)) AS day,
                COUNT(*) AS cnt
            FROM " . $this->getPhpBBTableName() . "
            WHERE
                users.user_regdate BETWEEN :startTs AND :endTs AND
                users.user_type IN(0, 3) AND
                users.user_allow_massemail = 1
            GROUP BY day
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('startTs', $startTs, ParameterType::INTEGER);
        $stmt->bindValue('endTs', $endTs, ParameterType::INTEGER);

        $arrResult = [];
        foreach( $stmt->executeQuery()->fetchAllKeyValue() as $day => $cnt ) {
            $arrResult[(string)$day] = (int)$cnt;
        }

        return $arrResult;
    }


    /**
     * Returns the count of activated users CURRENTLY subscribed to the newsletter (user_allow_massemail = 1)
     * who registered at or before the given UNIX timestamp.
     */
    public function countNewsletterSubscribersAtTimestamp(int $ts) : int
    {
        return (int)
            $this->createQueryBuilder('t')
                ->select('COUNT(t)')
                ->andWhere('t.user_type IN(0, 3)')
                ->andWhere('t.user_allow_massemail = 1')
                ->andWhere('t.regDate <= :ts')
                    ->setParameter('ts', $ts)
                ->getQuery()->getSingleScalarResult();
    }
}
