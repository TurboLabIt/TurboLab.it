<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\File as FileEntity;
use App\Service\Cms\FileEditor;
use App\Service\User;


class FileEditorCollection extends FileCollection
{
    public function setFromUpload(?array $arrUpload, User $author) : static
    {
        $this->clear();

        if( empty($arrUpload) ) {
            return $this;
        }

        $arrData = [];
        foreach($arrUpload as $file) {

            $fileHash = hash_file('md5', $file->getPathname() );

            $arrData[$fileHash] = [
                'UploadedFile'  => $file,
                'FileService'   => null
            ];
        }

        $fileRepository = $this->em->getRepository(static::ENTITY_CLASS);

        $arrExistingFileEntitiesByHash = $fileRepository->getByHash( array_keys($arrData) );

        foreach($arrData as $hash => $item) {

            $existingFileEntity = $arrExistingFileEntitiesByHash[$hash] ?? null;

            if( empty($existingFileEntity) ) {

                $arrData[$hash]["FileService"] =
                    $this->createService()
                        ->createFromUploadedFile($item["UploadedFile"]);

            } else {

                $arrData[$hash]["FileService"] = $this->createService($existingFileEntity);
            }

            $arrData[$hash]["FileService"]->addAuthor($author);
        }

        foreach($arrData as $item) {

            $fileId = (string)$item["FileService"]->getId();
            $this->arrData[$fileId] = $item["FileService"];
        }

        return $this;
    }


    public function loadToDelete() : static
    {
        $arrFiles = $this->getRepository()->getToDelete();
        return $this->setEntities($arrFiles);
    }


    public function createService(?FileEntity $entity = null) : FileEditor { return $this->factory->createFileEditor($entity); }
}
