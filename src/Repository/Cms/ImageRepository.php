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
}
