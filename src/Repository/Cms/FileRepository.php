<?php
namespace App\Repository\Cms;

use App\Entity\Cms\File;
use App\Repository\BaseRepository;


class FileRepository extends BaseRepository
{
    const string ENTITY_CLASS = File::class;
}
