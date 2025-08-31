<?php
namespace App\Repository\PhpBB;

use App\Repository\BaseRepository;
use Doctrine\Persistence\ManagerRegistry;


abstract class BasePhpBBRepository extends BaseRepository
{
    const TABLE_PREFIX = "phpbb_";


    public function __construct(protected array $arrConfig, ManagerRegistry $registry)
        { parent::__construct($registry); }


    protected function getPhpBBTableName(?string $tableName = null) : string
    {
        if( empty($tableName) ) {

            // this is something like: "turbolab_it_forum.phpbb_users"
            $tableName = $this->getTableName('');
            $prefixToRemoveForAlias = $this->arrConfig["forumDatabaseName"] . "." . static::TABLE_PREFIX;
            $tableAlias = substr($tableName, strlen($prefixToRemoveForAlias));
            return "$tableName AS `$tableAlias`";
        }

        if( stripos($tableName, static::TABLE_PREFIX) !== 0 ) {
            $tableName = static::TABLE_PREFIX . $tableName;
        }

        $dbName     = $this->arrConfig["forumDatabaseName"];
        $tableAlias = substr($tableName, strlen(static::TABLE_PREFIX));
        return "$dbName.$tableName AS `$tableAlias`";
    }
}
