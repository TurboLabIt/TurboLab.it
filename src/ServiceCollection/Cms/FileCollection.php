<?php
namespace App\ServiceCollection\Cms;

use App\Repository\Cms\FileRepository;
use App\Service\Cms\File as FileService;
use App\Entity\Cms\File as FileEntity;
use App\ServiceCollection\BaseServiceEntityCollection;


class FileCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = FileService::ENTITY_CLASS;
    protected array $arrFormats = [];


    public function loadOrphans() : static
    {
        $arrEntities = $this->getRepository()->getOrphans();
        return $this->setEntities($arrEntities);
    }


    // a Download's ID or its "/scarica/123" address finds that Download, ahead of the text matches
    public function loadSerp(string $termToSearch, ?int $authorId = null, ?string $sort = null) : static
    {
        $termToSearch   = trim($termToSearch);
        $fileId         =
            ctype_digit($termToSearch)
                ? (int)$termToSearch
                : $this->factory->getFileUrlGenerator()->extractIdFromUrl($termToSearch);

        $arrFiles = [];
        if( !empty($fileId) && ( $entity = $this->getRepository()->find($fileId) ) !== null ) {
            $arrFiles[$fileId] = $entity;
        }

        $arrFiles += $this->getRepository()->getSerpByFulltext($termToSearch, $authorId, $sort);
        return $this->setEntities($arrFiles);
    }


    public function loadByHash(string $hash) : static
    {
        return $this->setEntities( $this->getRepository()->getByHash([$hash]) );
    }


    // the external Downloads pointing to the same item as $url (see File::normalizeUrlForMatching())
    public function loadByEquivalentUrl(string $url) : static
    {
        // a Download's own address (copied from another article) is that Download, not a new external item
        $fileId = $this->factory->getFileUrlGenerator()->extractIdFromUrl( trim($url) );
        if( $fileId !== null ) {

            $entity = $this->getRepository()->find($fileId);
            return $this->setEntities( $entity === null ? [] : [$fileId => $entity] );
        }

        $normalizedUrl  = FileService::normalizeUrlForMatching($url);
        $arrMatches     = [];

        foreach( $this->getRepository()->getExternal() as $entity ) {

            if( FileService::normalizeUrlForMatching( $entity->getUrl() ) === $normalizedUrl ) {
                $arrMatches[$entity->getId()] = $entity;
            }
        }

        return $this->setEntities($arrMatches);
    }


    public function loadSimilarByTitle(string $text, int $limit = 5) : static
    {
        return $this->setEntities( $this->getRepository()->getSimilarByTitle($text, $limit) );
    }


    public function loadLatest(?int $authorId = null) : static
    {
        $arrFiles = $this->getRepository()->getLatest($authorId);
        return $this->setEntities($arrFiles);
    }


    public function getFormats() : array
    {
        if( !empty($this->arrFormats) ) {
            return $this->arrFormats;
        }

        $arrFormats     = $this->getRepository()->getFormats();
        $arrBestValues  = [];
        $arrOtherValues = [];

        foreach($arrFormats as $item) {

            $value = $item['format'];

            if( empty($value) ) {
                continue;
            }

            $arrOption = [
                'label'         => $value,
                'usage'         => $item['num'],
                'top'           => $item['num'] > 5,
                'externalOnly'  => stripos($value, 'store') !== false
            ];


            if( $arrOption['top'] ) {

                $arrBestValues[$value] = $arrOption;

            } else {

                $arrOtherValues[$value] = $arrOption;
            }
        }

        return $this->arrFormats = array_merge($arrBestValues, $arrOtherValues);
    }


    public function getRepository() : FileRepository { return $this->em->getRepository(FileEntity::class); }

    public function createService(?FileEntity $entity = null) : FileService
    {
        return $this->factory->createFile($entity);
    }
}
