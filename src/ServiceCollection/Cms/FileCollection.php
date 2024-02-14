<?php
namespace App\ServiceCollection\Cms;

use App\Service\Cms\File as FileService;
use App\Entity\Cms\File as FileEntity;
use App\Service\Cms\CmsFactory;
use Doctrine\ORM\EntityManagerInterface;


class FileCollection extends BaseCmsServiceCollection
{
    const ENTITY_CLASS = FileService::ENTITY_CLASS;


    public function __construct(protected EntityManagerInterface $em, protected CmsFactory $factory)
    { }


    public function createService(?FileEntity $entity = null) : FileService { return $this->factory->createFile($entity); }
}
