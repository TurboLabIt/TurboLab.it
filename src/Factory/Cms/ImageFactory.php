<?php
namespace App\Factory\Cms;

use App\Service\Cms\Image as ImageService;
use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\ImageUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;


class ImageFactory extends BaseCmsFactory
{
    protected ?ImageService $defaultSpotlight;


    public function __construct(
        protected ImageUrlGenerator $urlGenerator, protected EntityManagerInterface $em, protected ProjectDir $projectDir
    )
    { }


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
                ->setTitle("TurboLab.it.png");

        $this->defaultSpotlight = $this->create($entity);
        return $this->defaultSpotlight;
    }


    public function create(?ImageEntity $entity = null) : ImageService
    {
        $service = new ImageService($this->urlGenerator, $this->em, $this->projectDir);
        if( !empty($entity) ) {
            $service->setEntity($entity);
        }

        return $service;
    }
}
