<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Image;
use App\Repository\BaseRepository;


class ImageRepository extends BaseRepository
{
    const string ENTITY_CLASS = Image::class;


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
                ->leftJoin('t.articles', 'junction')
                ->where('junction.id IS NULL')
                ->orderBy('t.updatedAt', 'ASC')
                ->getQuery()->getResult();
    }
}
