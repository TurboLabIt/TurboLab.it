<?php
namespace App\Repository\Cms;

use App\Repository\BaseRepository;


abstract class BaseCmsRepository extends BaseRepository
{
    public function countOneView(int $entityId) : void
    {
        $this->increase("views", $entityId);
    }
}
