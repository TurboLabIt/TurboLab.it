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
        return $this->internalLoadBySlugDashId($slugDashId, 'getOneById');
    }


    public function loadBySlugDashIdComplete(string $slugDashId) : static
    {
        return $this->internalLoadBySlugDashId($slugDashId, 'getOneByIdComplete');
    }


    protected function internalLoadBySlugDashId(string $slugDashId, string $method) : static
    {
        $entityId = substr($slugDashId, strrpos($slugDashId, '-') + 1);

        $this->clear();
        $entity = $this->getRepository()->$method($entityId);

        if( empty($entity) ) {

            $exceptionClass = static::NOT_FOUND_EXCEPTION;
            throw new $exceptionClass($entityId);
        }

        return $this->setEntity($entity);
    }

    public function getUpdatedAt() : ?DateTime { return $this->entity->getUpdatedAt(); }
}
