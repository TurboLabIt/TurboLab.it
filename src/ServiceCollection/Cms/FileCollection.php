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

            if( $item['num'] > 5 ) {

                $arrBestValues[$value] = [
                    'label' => $value,
                    'usage' => $item['num'],
                    'top'   => true
                ];

            } else {

                $arrOtherValues[$value] = [
                    'label' => $value,
                    'usage' => $item['num'],
                    'top'   => false
                ];
            }
        }

        return $this->arrFormats = array_merge($arrBestValues, $arrOtherValues);
    }


    public function getRepository() : FileRepository { return $this->em->getRepository(static::ENTITY_CLASS); }

    public function createService(?FileEntity $entity = null) : FileService
    {
        return $this->factory->createFile($entity);
    }
}
