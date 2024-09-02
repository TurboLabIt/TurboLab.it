<?php
namespace App\Doctrine;

use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use App\Repository\PhpBB\BasePhpBBRepository;


class TLINamingStrategy extends UnderscoreNamingStrategy
{
    public function __construct(protected string $forumDatabaseName)
        { parent::__construct(); }


    public function classToTableName($className) : string
    {
        // this returns "article", "article_author", "user", "topic", ...
        $tableName = parent::classToTableName($className);
        if( stripos($className, 'App\\Entity\\PhpBB\\') === false ) {
            return $tableName;
        }

        // transforms to "turbolab_it_forum.phpbb_users", "turbolab_it_forum.phpbb_topics", ...
        return $this->forumDatabaseName . '.' . BasePhpBBRepository::TABLE_PREFIX . $tableName . 's';
    }
}
