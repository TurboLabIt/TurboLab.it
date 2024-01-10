<?php
namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }


    public function findOneByPhpBBCookiesValues(int $userId, string $sessionId, string $sessionKey)
    {
        $db  = $this->getEntityManager()->getConnection();
        $sql = "
                SELECT
                    userView.id, userView.username
                FROM
                    user AS userView
                INNER JOIN
                    turbolab_it_forum.phpbb_sessions AS sessions
                ON
                    userView.id = sessions.session_user_id
                INNER JOIN
                    turbolab_it_forum.phpbb_sessions_keys AS sessKeys
                ON
                    userView.id = sessKeys.user_id
                WHERE
                    userView.id         = :userId AND
                    sessions.session_id = :sessionId AND
                    sessKeys.key_id     = :sessionKey
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
                ->setId( $arrUser["id"] )
                ->setUsername( $arrUser["username"] );

        return $user;
    }
}
