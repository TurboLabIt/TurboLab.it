<?php
namespace App\Service;

use App\Entity\Cms\Article as ArticleEntity;
use App\Entity\Cms\File as FileEntity;
use App\Entity\Cms\Image as ImageEntity;
use App\Entity\Cms\Tag as TagEntity;
use App\Entity\PhpBB\Topic as TopicEntity;
use App\Service\Cms\Article as ArticleService;
use App\Service\Cms\ArticleUrlGenerator;
use App\Service\Cms\File as FileService;
use App\Service\Cms\FileUrlGenerator;
use App\Service\Cms\Image as ImageService;
use App\Service\Cms\ImageUrlGenerator;
use App\Service\Cms\Tag as TagService;
use App\Service\Cms\TagUrlGenerator;
use App\ServiceCollection\PhpBB\TopicCollection;
use App\Service\PhpBB\ForumUrlGenerator;
use App\Service\PhpBB\Topic as TopicService;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\FileCollection;
use App\ServiceCollection\Cms\ImageCollection;
use App\ServiceCollection\Cms\TagCollection;
use Doctrine\ORM\EntityManagerInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use App\Entity\PhpBB\User as UserEntity;
use App\Service\User as UserService;
use App\ServiceCollection\UserCollection;


class Factory
{
    protected ?ImageService $defaultSpotlight;


    //<editor-fold defaultstate="collapsed" desc="*** __construct ***">
    public function __construct(
        protected EntityManagerInterface $em, protected ProjectDir $projectDir,
        protected ArticleUrlGenerator $articleUrlGenerator,
        protected TagUrlGenerator $tagUrlGenerator,
        protected ImageUrlGenerator $imageUrlGenerator,
        protected FileUrlGenerator $fileUrlGenerator,
        protected ForumUrlGenerator $forumUrlGenerator,
        protected UserUrlGenerator $userUrlGenerator
    )
    { }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** Article ***">
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
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** Tag ***">
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
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** Image ***">
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


    public function createImage(?ImageEntity $entity = null) : ImageService
    {
        $service = new ImageService($this->imageUrlGenerator, $this->em, $this->projectDir);
        if( !empty($entity) ) {
            $service->setEntity($entity);
        }

        return $service;
    }


    public function createImageCollection() : ImageCollection
    {
        return new ImageCollection($this->em, $this);
    }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** File ***">
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
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** Topic ***">
    public function createTopic(?TopicEntity $entity = null) : TopicService
    {
        $service = new TopicService($this->forumUrlGenerator, $this->em, $this);
        if( !empty($entity) ) {
            $service->setEntity($entity);
        }

        return $service;
    }


    public function createTopicCollection() : TopicCollection
    {
        return new TopicCollection($this->em, $this);
    }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** User ***">
    public function createUser(?UserEntity $entity = null) : UserService
    {
        $service = new UserService($this->userUrlGenerator, $this->em, $this);
        if( !empty($entity) ) {
            $service->setEntity($entity);
        }

        return $service;
    }


    public function createUserCollection() : UserCollection
    {
        return new UserCollection($this->em, $this);
    }
    //</editor-fold>
}