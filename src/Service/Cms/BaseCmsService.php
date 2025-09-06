<?php
namespace App\Service\Cms;

use App\Service\BaseServiceEntity;
use DateTime;


abstract class BaseCmsService extends BaseServiceEntity
{
    const string UPLOADED_ASSET_FOLDER_NAME = 'uploaded-assets';
    const string UPLOADED_ASSET_XSEND_PATH  = 'xsend-uploaded-assets';


    public function loadBySlugDashId(string $slugDashId) : static
    {
        $entityId = substr($slugDashId, strrpos($slugDashId, '-') + 1);
        return $this->internalLoadBy($entityId, 'getOneById');
    }


    public function loadBySlugDashIdComplete(string $slugDashId) : static
    {
        $entityId = substr($slugDashId, strrpos($slugDashId, '-') + 1);
        return $this->internalLoadBy($entityId, 'getOneByIdComplete');
    }


    public function loadByTitle(string $title) : static
    {
        return $this->internalLoadBy($title, 'getOneByTitle');
    }


    protected function internalLoadBy(string $valueToSearch, string $repositoryMethodName) : static
    {
        $this->clear();
        $entity = $this->getRepository()->$repositoryMethodName($valueToSearch);

        if( empty($entity) ) {

            $exceptionClass = static::NOT_FOUND_EXCEPTION;
            throw new $exceptionClass($valueToSearch);
        }

        return $this->setEntity($entity);
    }


    public function getUpdatedAt() : ?DateTime { return $this->entity->getUpdatedAt(); }
}
