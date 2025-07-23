<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Tag as TagEntity;
use App\Service\Cms\TagEditor;
use App\Service\User;


class TagEditorCollection extends TagCollection
{
    public function setFromIdsAndTags($arrIdsAndTags, User $author) : static
    {
        $this->clear();

        if( empty($arrIdsAndTags) ) {
            return $this;
        }

        $arrIdsToLoad = array_column($arrIdsAndTags, 'id');

        if( empty($arrIdsToLoad) ) {
            return $this;
        }

        $arrExistingTagEntities = $this->getRepository()->getById($arrIdsToLoad);

        $arrUniqueTagLabels = [];
        foreach($arrIdsAndTags as $item) {

            if( !array_key_exists('id', $item) || !array_key_exists('title', $item) ) {
                continue;
            }

            $tagId = $item["id"];

            if( !empty($tagId) && array_key_exists($tagId, $this->arrData) ) {
                continue;
            }

            $existingTagEntity = $arrExistingTagEntities[$tagId] ?? null;

            if( empty($existingTagEntity) ) {

                $newTag =
                    $this->createService()
                        ->setTitle($item["title"])
                        ->addAuthor($author);

                if( $this->isTagLabelAlreadyInSet($newTag, $arrUniqueTagLabels) ) {
                    continue;
                }

                $newTag->save();

                $newTagId = (string)$newTag->getId();
                $this->arrData[$newTagId] = $newTag;

            } else {

                $tag = $this->createService($existingTagEntity);

                if( $this->isTagLabelAlreadyInSet($tag, $arrUniqueTagLabels) ) {
                    continue;
                }

                $this->arrData[$tagId] = $tag;
            }
        }

        return $this;
    }


    protected function isTagLabelAlreadyInSet(TagEditor $tag, array &$arrUniqueTagLabels) : bool
    {
        $title = $tag->getTitleComparable();

        if( in_array($title, $arrUniqueTagLabels) ) {
            return true;
        }

        $arrUniqueTagLabels[] = $title;

        return false;
    }


    public function createService(?TagEntity $entity = null) : TagEditor
    {
        return $this->factory->createTagEditor($entity);
    }


}
