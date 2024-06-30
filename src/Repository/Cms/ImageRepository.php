<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Image;
use App\Repository\BaseRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;


/**
 * @extends ServiceEntityRepository<Image>
 *
 * @method Image|null find($id, $lockMode = null, $lockVersion = null)
 * @method Image|null findOneBy(array $criteria, array $orderBy = null)
 * @method Image[]    findAll()
 * @method Image[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageRepository extends BaseCmsRepository
{
    const string ENTITY_CLASS_NAME = Image::class;


    public function findComplete(int $id) : ?Image
    {
        // ATM there is no need to load it with all the related data
        return $this->find($id);
    }
}
