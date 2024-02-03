<?php
namespace App\ServiceCollection\Cms;

use App\Entity\BaseEntity;
use App\Factory\Cms\TagFactory;
use App\Entity\Cms\Tag as TagEntity;
use App\Service\Cms\Tag as TagService;
use Doctrine\ORM\EntityManagerInterface;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tags.md
 */
class TagCollection extends BaseCmsServiceCollection
{
    const ENTITY_CLASS = TagService::ENTITY_CLASS;


    public function __construct(protected EntityManagerInterface $em, protected TagFactory $factory)
    { }


    /**
     * @param TagEntity|null $entity
     */
    public function createService(?BaseEntity $entity = null) : TagService { return parent::createService($entity); }
}
