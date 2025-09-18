<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Forum;
use App\Entity\PhpBB\Post;
use Doctrine\ORM\QueryBuilder;


class PostRepository extends BasePhpBBRepository
{
    const string ENTITY_CLASS       = Post::class;
    const string DEFAULT_INDEXED_BY = 't.id';


    protected function getQueryBuilder() : QueryBuilder
    {
        return
            parent::getQueryBuilder()
                ->andWhere('t.forumId NOT IN (' . implode(',', Forum::ID_OFFLIMIT) . ')')
                ->andWhere('t.visibility = 1')
                ->andWhere('t.deleteTime = 0')
                ->orderBy('t.postTime', 'DESC');
    }
}
