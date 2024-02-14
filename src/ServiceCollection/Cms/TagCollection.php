<?php
namespace App\ServiceCollection\Cms;

use App\Service\Cms\CmsFactory;
use App\Entity\Cms\Tag as TagEntity;
use App\Service\Cms\Tag as TagService;
use Doctrine\ORM\EntityManagerInterface;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tags.md
 */
class TagCollection extends BaseCmsServiceCollection
{
    const string ENTITY_CLASS = TagService::ENTITY_CLASS;


    public function __construct(protected EntityManagerInterface $em, protected CmsFactory $factory)
    { }


    public function createService(?TagEntity $entity = null) : TagService { return $this->factory->createTag($entity); }
}
