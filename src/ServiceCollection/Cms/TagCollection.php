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


    public function loadCategories() : static
    {
        $this->load([
            TagService::ID_WINDOWS, TagService::ID_LINUX, TagService::ID_ANDROID, TagService::ID_CRYPTOCURRENCIES,
            TagService::ID_FILESHARING,TagService::ID_SECURITY, TagService::ID_WHAT_TO_BUY, TagService::ID_VPN
        ]);

        return $this;
    }


    public function createService(?TagEntity $entity = null) : TagService { return $this->factory->createTag($entity); }
}
