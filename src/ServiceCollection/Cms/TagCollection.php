<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Tag as TagEntity;
use App\Service\Cms\Tag as TagService;
use App\ServiceCollection\BaseServiceEntityCollection;


/**
 * @link https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tags.md
 */
class TagCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS   = TagService::ENTITY_CLASS;
    const array TOP_CATEGORIES  = [
        TagService::ID_WINDOWS, TagService::ID_LINUX, TagService::ID_ANDROID, TagService::ID_CRYPTOCURRENCIES
    ];
    const array NAV_OTHER_CATEGORIES = [
        TagService::ID_FILESHARING, TagService::ID_SECURITY, TagService::ID_WHAT_TO_BUY, TagService::ID_VPN,
        TagService::ID_VIRTUALIZATION, TagService::ID_DEV, TagService::ID_YOUTUBE
    ];


    public function loadCategories() : static
        { return $this->load( array_merge_recursive(static::TOP_CATEGORIES, static::NAV_OTHER_CATEGORIES) ); }


    public function createService(?TagEntity $entity = null) : TagService { return $this->factory->createTag($entity); }
}
