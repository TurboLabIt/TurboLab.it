<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\User;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/tree/main/docs/users.md
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }


    public function loadAll()
    {
        $this->cachedRs =
            $this->createQueryBuilder('t', 't.user_id')
                ->orderBy('t.user_id')
                ->getQuery()
                ->getResult();

        return $this->cachedRs;
    }


    public function findOneByPhpBBCookiesValues(int $userId, string $sessionId, string $sessionKey)
    {
        $db  = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT
                users.user_id, username, user_email,
                user_avatar_type, user_avatar,
                user_posts, user_colour, user_allow_massemail
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
                users.user_type			IN(1, 3) AND
                sessions.session_id		= :sessionId AND
                sessions_keys.key_id	= :sessionKey
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue('userId', $userId, ParameterType::INTEGER);
        $stmt->bindValue('sessionId', $sessionId);
        $stmt->bindValue('sessionKey', md5($sessionKey) );

        $ormResult  = $stmt->executeQuery();
        $arrUser    = $ormResult->fetchAssociative();

        if( empty($arrUser) ) {
            return null;
        }

        $user =
            (new User())
                ->setId( $arrUser["user_id"] )
                ->setUsername( $arrUser["username"] )
                ->setEmail( $arrUser["user_email"] )
                ->setAvatarType( $arrUser["user_avatar_type"] )
                ->setAvatarFile( $arrUser["user_avatar"] )
                ->setPostNum( $arrUser["user_posts"] )
                ->setColor( $arrUser["user_colour"] )
                ->setAllowMassEmail( $arrUser["user_allow_massemail"] );

        return $user;
    }
}
