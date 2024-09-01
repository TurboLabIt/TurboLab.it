<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\User;
use App\Repository\BaseRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;


class UserRepository extends BaseRepository
{
    const string ENTITY_CLASS       = User::class;
    const string DEFAULT_INDEXED_BY = 't.user_id';

    const string AUTHENTICATED_USER_FIELDS  = '
        users.user_id, username, user_email,
        user_avatar_type, user_avatar,
        user_posts, user_colour, user_allow_massemail
    ';


    public function findOneByUserSidKey(int $userId, string $sessionId, string $sessionKey)
    {
        $db  = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT
                " . static::AUTHENTICATED_USER_FIELDS . "
            FROM
                turbolab_it_forum.phpbb_users AS users
            INNER JOIN
                turbolab_it_forum.phpbb_sessions AS sessions
            ON
                users.user_id = sessions.session_user_id
            INNER JOIN
                turbolab_it_forum.phpbb_sessions_keys AS sessions_keys
            ON
                users.user_id = sessions_keys.user_id
            WHERE
                users.user_id			= :userId AND
                ## forum/includes/constants.php: USER_NORMAL, USER_FOUNDER
                users.user_type			IN(0, 3) AND
                sessions.session_id		= :sessionId AND
                sessions_keys.key_id	= :sessionKey
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue('userId', $userId, ParameterType::INTEGER);
        $stmt->bindValue('sessionId', $sessionId);
        $stmt->bindValue('sessionKey', md5($sessionKey) );

        return $this->buildUserEntityFromSqlStatement($stmt);
    }


    public function findOneByUserSid(int $userId, string $sessionId)
    {
        $db  = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT
                " . static::AUTHENTICATED_USER_FIELDS . "
            FROM
                turbolab_it_forum.phpbb_users AS users
            INNER JOIN
                turbolab_it_forum.phpbb_sessions AS sessions
            ON
                users.user_id = sessions.session_user_id
            WHERE
                users.user_id			= :userId AND
                ## forum/includes/constants.php: USER_NORMAL, USER_FOUNDER
                users.user_type			IN(0, 3) AND
                sessions.session_id		= :sessionId
        ";

        $stmt = $db->prepare($sql);
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

        return
            (new User())
                ->setId( $arrUser["user_id"] )
                ->setUsername( $arrUser["username"] )
                ->setEmail( $arrUser["user_email"] )
                ->setAvatarType( $arrUser["user_avatar_type"] )
                ->setAvatarFile( $arrUser["user_avatar"] )
                ->setPostNum( $arrUser["user_posts"] )
                ->setColor( $arrUser["user_colour"] )
                ->setAllowMassEmail( $arrUser["user_allow_massemail"] );
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
        $db     = $this->getEntityManager()->getConnection();
        $sql    = "SELECT * FROM turbolab_it_forum.phpbb_profile_fields_data WHERE user_id = :userId";
        $stmt   = $db->prepare($sql);
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
}
