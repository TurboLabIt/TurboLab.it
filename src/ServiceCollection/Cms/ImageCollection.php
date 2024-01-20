<?php
namespace App\ServiceCollection\Cms;

use App\Entity\BaseEntity;
use App\Factory\Cms\ImageFactory;
use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\Image as ImageService;
use Doctrine\ORM\EntityManagerInterface;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md
 */
class ImageCollection extends BaseCmsServiceCollection
{
    const ENTITY_CLASS          = ImageEntity::class;
    const NOT_FOUND_EXCEPTION   = 'App\Exception\ImageNotFoundException';


    public function __construct(protected EntityManagerInterface $em, protected ImageFactory $factory)
    { }


    public function get404(string $size) : ImageService
    {
        // 👀 https://turbolab.it/immagini/24297/med
        return $this->loadById(24297);
    }


    public function loadById(int $entityId) : ImageService { return parent::loadById($entityId); }
    public function loadBySlugDashId(string $slugDashId) : ImageService { return parent::loadBySlugDashId($slugDashId); }

    /**
     * @param ImageEntity|null $entity
     */
    public function createService(?BaseEntity $entity = null) : ImageService { return parent::createService($entity); }
}
