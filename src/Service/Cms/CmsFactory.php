<?php
namespace App\Service\Cms;

use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\FileCollection;
use App\ServiceCollection\Cms\ImageCollection;
use App\ServiceCollection\Cms\TagCollection;
use Doctrine\ORM\EntityManagerInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use App\Entity\Cms\Article as ArticleEntity;
use App\Service\Cms\Article as ArticleService;
use App\Entity\Cms\Tag as TagEntity;
use App\Service\Cms\Tag as TagService;
use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\Image as ImageService;
use App\Entity\Cms\File as FileEntity;
use App\Service\Cms\File as FileService;


class CmsFactory
{
    protected ?ImageService $defaultSpotlight;


    public function __construct(
        protected EntityManagerInterface $em, protected ProjectDir $projectDir,
        protected ArticleUrlGenerator $articleUrlGenerator,
        protected TagUrlGenerator $tagUrlGenerator,
        protected ImageUrlGenerator $imageUrlGenerator,
        protected FileUrlGenerator $fileUrlGenerator
    )
    { }


    public function createArticle(?ArticleEntity $entity = null) : ArticleService
    {
        $service = new ArticleService($this->articleUrlGenerator, $this->em, $this);
        if( !empty($entity) ) {
            $service->setEntity($entity);
        }

        return $service;
    }


    public function createArticleCollection() : ArticleCollection
    {
        return new ArticleCollection($this->em, $this);
    }


    public function createTag(?TagEntity $entity = null) : TagService
    {
        $service = new TagService($this->tagUrlGenerator, $this->em, $this);
        if( !empty($entity) ) {
            $service->setEntity($entity);
        }

        return $service;
    }


    public function createTagCollection() : TagCollection
    {
        return new TagCollection($this->em, $this);
    }


    public function createImage(?ImageEntity $entity = null) : ImageService
    {
        $service = new ImageService($this->imageUrlGenerator, $this->em, $this->projectDir);
        if( !empty($entity) ) {
            $service->setEntity($entity);
        }

        return $service;
    }


    public function createDefaultSpotlight() : ImageService
    {
        if( !empty($this->defaultSpotlight) ) {
            return $this->defaultSpotlight;
        }

        $entity =
            (new ImageEntity())
                ->setId(1)
                ->setFormat('png')
                ->setWatermarkPosition(ImageEntity::WATERMARK_DISABLED)
                ->setTitle("TurboLab.it");

        $this->defaultSpotlight = $this->createImage($entity);
        return $this->defaultSpotlight;
    }


    public function createImageCollection() : ImageCollection
    {
        return new ImageCollection($this->em, $this);
    }


    public function createFile(?FileEntity $entity = null) : FileService
    {
        $service = new FileService($this->fileUrlGenerator, $this->em, $this, $this->projectDir);
        if( !empty($entity) ) {
            $service->setEntity($entity);
        }

        return $service;
    }


    public function createFileCollection() : FileCollection
    {
        return new FileCollection($this->em, $this);
    }
}
