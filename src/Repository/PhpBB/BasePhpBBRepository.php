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
            $tableName = $this->getTableName('');
        }

        if( stripos($tableName, static::TABLE_PREFIX) !== 0 ) {
            $tableName = static::TABLE_PREFIX . $tableName;
        }

        $wrapper    = "`";
        $dbName     = $this->arrConfig["forumDatabaseName"];
        $tableAlias = substr($tableName, strlen(static::TABLE_PREFIX));
        return "{$wrapper}{$dbName}{$wrapper}.{$wrapper}{$tableName}{$wrapper} AS $tableAlias";
    }
}
