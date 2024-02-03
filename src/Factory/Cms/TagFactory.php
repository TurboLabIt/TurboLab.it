<?php
namespace App\Factory\Cms;

use App\Service\Cms\Tag as TagService;
use App\Entity\Cms\Tag as TagEntity;
use App\Service\Cms\TagUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;


class TagFactory extends BaseCmsFactory
{
    public function __construct(protected TagUrlGenerator $urlGenerator, protected EntityManagerInterface $em)
    { }


    public function create(?TagEntity $entity = null) : TagService
    {
        $service = new TagService($this->urlGenerator, $this->em);
        if( !empty($entity) ) {
            $service->setEntity($entity);
        }

        return $service;
    }
}
