<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\Image as ImageEntity;
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

        /** @var ImageCollection $existingImages */
        $existingImages = $this->factory->createImageCollection()->loadByHash( array_keys($arrData) );

        foreach($arrData as $hash => $item) {

            $existingImage = $existingImages->lookupSearchExtract($hash, function(string $hashToCheck, string $image) {
                return $hashToCheck == $image->getHash();
            });

            if( empty($existingImage) ) {

                $newImage =
                    $this->factory->createImageEditor()
                        ->createFromFilePath($item["File"], $hash)
                        ->save(false);

                $item["Image"] = $newImage;

            } else {

                $item["Image"] = $existingImage;
            }

            $item["Image"]->addAuthor($author);
        }

        $this->factory->getEntityManager()->flush();

        foreach($arrData as $item) {

            $imageId = (string)$item["Image"]->getId();
            $this->arrData[$imageId] = $item["Image"];
        }

        return $this;
    }


    public function createService(?ImageEntity $entity = null) : ImageEditor
    {
        return $this->factory->createImageEditor($entity);
    }
}
