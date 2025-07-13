<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Tag as TagEntity;
use App\Repository\Cms\TagRepository;
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

    const array EXCLUDE_FROM_SUGGESTIONS = [TagService::ID_NEWSLETTER_TLI];


    public function getRepository() : TagRepository
    {
        return $this->factory->getEntityManager()->getRepository(static::ENTITY_CLASS);
    }


    public function loadCategories() : static
    {
        return $this->load( array_merge_recursive(static::TOP_CATEGORIES, static::NAV_OTHER_CATEGORIES) );
    }


    public function getCommonGrouped() : array
    {
        $arrGroupsToLoad = [
            "main" => array_merge_recursive(static::TOP_CATEGORIES, [
                TagService::ID_LAPTOP, TagService::ID_SMARTPHONE, TagService::ID_HARDWARE,
            ], static::NAV_OTHER_CATEGORIES, [
                TagService::ID_WEBSERVICES, TagService::ID_MAC, TagService::ID_IOS
            ]),
            "popular" => []
        ];

        $arrTagIdsToLoad = array_merge(...array_values($arrGroupsToLoad));

        $selectedTags = $this->getRepository()->getById($arrTagIdsToLoad);

        $arrGrouped = [];
        foreach($arrGroupsToLoad as $group => $arrIds) {

            foreach($arrIds as $id) {

                $tag = $selectedTags[$id] ?? null;
                if( !empty($tag) ) {
                    $arrGrouped[$group][(string)$id] = $this->createService($tag);
                }
            }
        }

        $popularTags = $this->getRepository()->findPopular(50);
        foreach($popularTags as $id => $tag) {
            if( !in_array($id, $arrTagIdsToLoad) && !in_array($id, static::EXCLUDE_FROM_SUGGESTIONS) )  {
                $arrGrouped["popular"][(string)$id] = $this->createService($tag);
            }
        }

        return $arrGrouped;
    }


    public function loadBySearchTagOrCreate(?string $tag) : static
    {
        $arrTags = $this->getRepository()->search($tag);
        $this->setEntities($arrTags);

        $newTag = $this->factory->createTagEditor()->setTitle($tag);

        $arrPerfectMatch =
            $this->lookupSearchExtract($newTag, function(TagService $newTagToCheck, TagService $existingTag) {
                return $newTagToCheck->getSlug() == $existingTag->getSlug();
            });

        $arrNew = empty($arrPerfectMatch) ? ["new" => $newTag] : [];

        $arrStartWith =
            $this->lookupSearchExtract($newTag, function(TagService $newTagToCheck, TagService $existingTag) {

                $slug1 = $newTagToCheck->getSlug();
                $slug2 = $existingTag->getSlug();
                return str_starts_with($slug1, $slug2)  || str_starts_with($slug2, $slug1);
            });

        $this->arrData = $arrPerfectMatch + $arrStartWith + $this->arrData + $arrNew;

        return $this;
    }


    public function createService(?TagEntity $entity = null) : TagService { return $this->factory->createTag($entity); }
}
