<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Image;
use App\Repository\BaseRepository;
use DateTime;
use Doctrine\ORM\QueryBuilder;


class ImageRepository extends BaseRepository
{
    const string ENTITY_CLASS               = Image::class;
    const int ORPHANS_AFTER_MONTHS          = 9;
    const int DELETE_ORPHANS_AFTER_MONTHS   = 12;

    public function getByHash(array $arrHashes) : array
    {
        if( empty($arrHashes) ) {
            return [];
        }

        return
            $this->createQueryBuilder('t', 't.hash')
                ->andWhere( 't.hash IN(:hashes)')
                    ->setParameter('hashes', $arrHashes)
                ->getQuery()->getResult();
    }


    public function getOrphans() : array { return $this->getQueryBuilderOrphans()->getQuery()->getResult(); }


    public function getToDelete() : array
    {
        return
            $this->getQueryBuilderOrphans()
                ->andWhere('t.updatedAt < :limitDate')
                ->setParameter('limitDate', new DateTime('-' . static::DELETE_ORPHANS_AFTER_MONTHS . ' months') )
                ->getQuery()->getResult();
    }


    protected function getQueryBuilderOrphans() : QueryBuilder
    {
        return
            $this->getQueryBuilder()
                ->leftJoin('t.articles', 'junction')
                ->where('junction.id IS NULL')
                ->orderBy('t.updatedAt', 'ASC');
    }
}
