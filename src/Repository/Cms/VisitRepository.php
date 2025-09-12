<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Article;
use App\Entity\Cms\Visit;
use App\Entity\Cms\File;
use App\Entity\Cms\Tag;
use App\Entity\PhpBB\User;
use App\Repository\BaseRepository;


class VisitRepository extends BaseRepository
{
    const string ENTITY_CLASS = Visit::class;

    public function getOrNewByVisitLogic(?User $userEntity, Article|Tag|File $entity, ?string $ipAddress) : Visit
    {
        $qbBase =
            $this->createQueryBuilder('t', 't.id')
                ->andWhere('t.' . $entity->getClass() . '= :entity')
                    ->setParameter('entity', $entity)
                ->orderBy('t.updatedAt', 'DESC');

        if( !empty($userEntity) ) {

            $qbWithUser = clone $qbBase;

            $visitEntity =
                $qbWithUser
                    ->andWhere('t.user = :user')
                        ->setParameter('user', $userEntity)
                    ->getQuery()->getOneOrNullResult();

            if( !empty($visitEntity) ) {
                return $visitEntity->setIpAddress($ipAddress);
            }
        }


        $qbBase
            ->andWhere('t.ipAddress = :ipAddress')
                ->setParameter('ipAddress', $ipAddress);

        $arrVisits = $qbBase->getQuery()->getResult();

        if( empty($arrVisits) ) {
            return $this->createVisit($userEntity, $entity, $ipAddress);
        }


        if( empty($userEntity) ) {

            foreach($arrVisits as $visit) {

                if( empty( $visit->getUser() ) ) {
                    return $visit;
                }
            }
        }


        return
            $this->createVisit($userEntity, $entity, $ipAddress)
                ->setUpdatedAt(new \DateTime());
    }


    public function createVisit(?User $userEntity, Article|Tag|File $entity, ?string $ipAddress) : Visit
    {
        $visitEntity =
            (new Visit())
                ->setIpAddress($ipAddress)
                ->setUser($userEntity);

        match( $entity->getClass() ) {
            Article::TLI_CLASS  => $visitEntity->setArticle($entity),
            Tag::TLI_CLASS      => $visitEntity->setTag($entity),
            File::TLI_CLASS     => $visitEntity->setFile($entity),
        };

        return $visitEntity;
    }
}
