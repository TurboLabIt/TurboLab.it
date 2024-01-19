<?php
namespace App\ServiceCollection\Cms;

use App\Factory\Cms\ImageFactory;
use App\Entity\Cms\Image as ImageEntity;
use Doctrine\ORM\EntityManagerInterface;


class ImageCollection extends BaseCmsServiceCollection
{
    const ENTITY_CLASS          = ImageEntity::class;
    const NOT_FOUND_EXCEPTION   = '\App\Exception\ImageNotFoundException';


    public function __construct(protected EntityManagerInterface $em, protected ImageFactory $factory)
    { }
}
