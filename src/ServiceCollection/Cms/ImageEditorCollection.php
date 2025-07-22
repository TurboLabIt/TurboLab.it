<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\ArticleEditor;
use App\Service\Cms\ImageEditor;
use App\Service\User;


class ImageEditorCollection extends ImageCollection
{
    public function setFromUpload(?array $arrUpload, User $author) : static
    {
        $this->clear();

        if( empty($arrUpload) ) {
            return $this;
        }

        $arrData = [];
        foreach($arrUpload as $file) {

            if( !str_starts_with($file->getMimeType(), 'image') ) {
                continue;
            }

            $fileHash = hash_file('md5', $file->getPathname() );

            $arrData[$fileHash] = [
                'File'  => $file,
                'Image' => null
            ];
        }


        $arrExistingImagesEntities = $this->em->getRepository(static::ENTITY_CLASS)->getByHash( array_keys($arrData) );

        foreach($arrData as $hash => $item) {

            $existingImageEntity = $arrExistingImagesEntities[$hash] ?? null;

            if( empty($existingImageEntity) ) {

                $arrData[$hash]["Image"] =
                    $this->createService()
                        ->createFromUploadedFile($item["File"]);

            } else {

                $arrData[$hash]["Image"] = $this->createService($existingImageEntity);
            }

            $arrData[$hash]["Image"]->addAuthor($author);
        }

        foreach($arrData as $item) {

            $imageId = (string)$item["Image"]->getId();
            $this->arrData[$imageId] = $item["Image"];
        }

        return $this;
    }


    public function addToArticle(ArticleEditor $articleEditor) : static
    {
        $this->iterate(function(ImageEditor $image) use($articleEditor) {
            $image->addToArticle($articleEditor);
        });

        return $this;
    }


    public function createService(?ImageEntity $entity = null) : ImageEditor
    {
        return $this->factory->createImageEditor($entity);
    }
}
