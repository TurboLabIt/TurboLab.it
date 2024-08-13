<?php
namespace App\Repository\Cms;

use App\Entity\Cms\Image;
use App\Repository\BaseRepository;


class ImageRepository extends BaseRepository
{
    const string ENTITY_CLASS = Image::class;
}
