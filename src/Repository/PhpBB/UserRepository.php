<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\User;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;


class UserRepository extends BasePhpBBRepository
{
    const string ENTITY_CLASS       = User::class;
    const string DEFAULT_INDEXED_BY = 't.user_id';

    const string AUTHENTICATED_USER_FIELDS  = '
        users.user_id, user_type, username, user_email,
        user_avatar_type, user_avatar,
        user_posts, user_colour, user_allow_massemail
    ';


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
                ->setParameter('username', $usernameClean)
                ->getQuery()
                ->getOneOrNullResult();
    }


    public function handleBounceEmailAddress(array $arrAddresses) : static
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

        // unsubscribing from the newsletter
        $this->createQueryBuilder('u')
            ->update()
            ->set('u.user_allow_massemail', 0)
            ->set('u.user_notify_pm', 0)
            ->where('u.user_id IN (:ids)')
            ->setParameter('ids', $arrUserIds)
        ->getQuery()->execute();

        // mark all the watches as "notified, not viewed"
        $csvIds = implode(",", $arrUserIds);
        foreach(["topics_watch", "forums_watch"] as $watchTableName) {

            $fullyQualifiedTableName = $this->arrConfig["forumDatabaseName"] . '.' . static::TABLE_PREFIX . $watchTableName;
            $this->sqlQueryExecute("UPDATE $fullyQualifiedTableName SET notify_status = 1 WHERE user_id IN($csvIds)");
        }

        return $this;
    }
}
