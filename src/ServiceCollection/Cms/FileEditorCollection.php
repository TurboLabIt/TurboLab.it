<?php
namespace App\ServiceCollection\Cms;

use App\Entity\Cms\File as FileEntity;
use App\Service\Cms\FileEditor;
use App\Service\User;


class FileEditorCollection extends FileCollection
{
    // the uploads that were already on TurboLab.it (same content): the existing Download is used instead
    protected array $arrReusedIds = [];


    public function setFromUpload(?array $arrUpload, User $author, ?string $title = null) : static
    {
        $this->clear();
        $this->arrReusedIds = [];

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

        $fileRepository = $this->getRepository();

        $arrExistingFileEntitiesByHash = $fileRepository->getByHash( array_keys($arrData) );

        foreach($arrData as $hash => $item) {

            $existingFileEntity = $arrExistingFileEntitiesByHash[$hash] ?? null;

            if( empty($existingFileEntity) ) {

                $arrData[$hash]["FileService"] =
                    $this->createService()
                        ->createFromUploadedFile($item["UploadedFile"], $title);

                $arrData[$hash]["FileService"]->addAuthor($author);

            } else {

                $arrData[$hash]["FileService"] = $this->createService($existingFileEntity);
                $this->arrReusedIds[] = $existingFileEntity->getId();
            }
        }

        foreach($arrData as $item) {

            $fileId = (string)$item["FileService"]->getId();
            $this->arrData[$fileId] = $item["FileService"];
        }

        return $this;
    }


    public function isReused(int $fileId) : bool { return in_array($fileId, $this->arrReusedIds); }


    public function loadToDelete() : static
    {
        $arrFiles = $this->getRepository()->getToDelete();
        return $this->setEntities($arrFiles);
    }


    public function createService(?FileEntity $entity = null) : FileEditor { return $this->factory->createFileEditor($entity); }
}
