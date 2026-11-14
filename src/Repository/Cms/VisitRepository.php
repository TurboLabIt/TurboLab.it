<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Article;
use App\Entity\Cms\Visit;
use App\Entity\Cms\File;
use App\Entity\Cms\Tag;
use App\Entity\PhpBB\User;
use App\Repository\BaseRepository;
use DateTime;
use SortDirection;


class VisitRepository extends BaseRepository
{
    const string ENTITY_CLASS = Visit::class;

    public function getOrNewByVisitLogic(?User $userEntity, Article|Tag|File $entity, ?string $ipAddress) : Visit
    {
        $qbBase =
            $this->createQueryBuilder('t', 't.id')
                ->andWhere('t.' . $entity->getClass() . '= :entity')
                    ->setParameter('entity', $entity)
                ->orderBy('t.updatedAt', SortDirection::Descending);

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
                ->setUpdatedAt(new DateTime());
    }


    public function createVisit(?User $userEntity, Article|Tag|File $entity, ?string $ipAddress) : Visit
    {
        $visitEntity =
            (new Visit())
                ->setIpAddress($ipAddress)
                ->setUser($userEntity);

        match(true) {
            $entity instanceof Article  => $visitEntity->setArticle($entity),
            $entity instanceof Tag      => $visitEntity->setTag($entity),
            $entity instanceof File     => $visitEntity->setFile($entity),
        };

        return $visitEntity;
    }


    public function getByContentAndUser(?User $userEntity, Article|Tag|File $entity, ?DateTime $onlyIfAfter = null) : ?Visit
    {
        $qb =
            $this->createQueryBuilder('t', 't.id')
                ->andWhere('t.' . $entity->getClass() . '= :entity')
                    ->setParameter('entity', $entity)
                ->andWhere('t.user = :user')
                    ->setParameter('user', $userEntity);

        if( !empty($onlyIfAfter) ) {

            $qb->andWhere('t.updatedAt >= :dateLimit')
                ->setParameter('dateLimit', $onlyIfAfter);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
