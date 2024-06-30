<?php
namespace App\Repository\Cms;

use App\Entity\Cms\TagBadge;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;


/**
 * @extends ServiceEntityRepository<TagBadge>
 *
 * @method TagBadge|null find($id, $lockMode = null, $lockVersion = null)
 * @method TagBadge|null findOneBy(array $criteria, array $orderBy = null)
 * @method TagBadge[]    findAll()
 * @method TagBadge[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagBadgeRepository extends BaseRepository
{
    const string ENTITY_CLASS_NAME = TagBadge::class;
}
