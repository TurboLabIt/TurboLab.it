<?php
namespace App\Repository\Cms;

use App\Entity\Cms\File;
use App\Repository\BaseRepository;


class FileRepository extends BaseRepository
{
    const string ENTITY_CLASS = File::class;
    const int ORPHANS_AFTER_MONTHS          = ImageRepository::ORPHANS_AFTER_MONTHS;
    const int DELETE_ORPHANS_AFTER_MONTHS   = ImageRepository::DELETE_ORPHANS_AFTER_MONTHS;


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


    public function getOrphans() : array
    {
        return
            $this->getQueryBuilder()
                ->leftJoin('t.articles', 'articlesJunction')
                ->where('articlesJunction.id IS NULL')
                ->getQuery()->getResult();
    }
}
