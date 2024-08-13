<?php
namespace App\Repository\Cms;

use App\Entity\Cms\ArticleAuthor;
use App\Repository\BaseRepository;


class ArticleAuthorRepository extends BaseRepository
{
    const string ENTITY_CLASS = ArticleAuthor::class;
}
