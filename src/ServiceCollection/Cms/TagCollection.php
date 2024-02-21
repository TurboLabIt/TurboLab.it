<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Tag as TagEntity;
use App\Service\Cms\Tag as TagService;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tags.md
 */
class TagCollection extends BaseCmsServiceCollection
{
    const string ENTITY_CLASS = TagService::ENTITY_CLASS;


    public function createService(?TagEntity $entity = null) : TagService { return $this->factory->createTag($entity); }
}
