<?php
namespace App\Factory\Cms;

use App\Service\Cms\Article as ArticleService;
use App\Entity\Cms\Article as ArticleEntity;
use App\Service\Cms\ArticleUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;


class ArticleFactory extends BaseCmsFactory
{
    public function __construct(
        protected ArticleUrlGenerator $urlGenerator, protected EntityManagerInterface $em,
        protected ImageFactory $imageFactory, protected TagFactory $tagFactory
    )
    { }


    public function create(?ArticleEntity $entity = null) : ArticleService
    {
        $service = new ArticleService($this->urlGenerator, $this->em, $this->imageFactory, $this->tagFactory);
        if( !empty($entity) ) {
            $service->setEntity($entity);
        }

        return $service;
    }
}
