<?php

namespace App\Repository\Cms;

use App\Entity\Cms\ImageAuthor;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImageAuthor>
 *
 * @method ImageAuthor|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageAuthor|null findOneBy(array $criteria, array $orderBy = null)
 * @method ImageAuthor[]    findAll()
 * @method ImageAuthor[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageAuthorRepository extends BaseRepository
{
    const string ENTITY_CLASS_NAME = ImageAuthor::class;
}
