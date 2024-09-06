<?php
namespace App\Repository\PhpBB;

use App\Entity\PhpBB\Forum;
use Doctrine\ORM\QueryBuilder;


class ForumRepository extends BasePhpBBRepository
{
    const string ENTITY_CLASS = Forum::class;
    const array DEFAULT_CONFIGS_TO_GET = [
        'version', 'mobiquo_version'
    ];


    protected function getQueryBuilder() : QueryBuilder
    {
        return
            parent::getQueryBuilder()
                ->andWhere('t.id NOT IN (' . implode(',', Forum::OFFLIMITS_FORUM_IDS) . ')')
                ->andWhere('t.parentId NOT IN (' . implode(',', Forum::OFFLIMITS_FORUM_IDS) . ')')
                ->andWhere('t.status = 0')
                ->andWhere('t.type = 1')
                ->andWhere('t.last_post_time > 0')
                ->orderBy('t.last_post_time', 'DESC');
    }


    public function getConfig(?array $arrConfigNames = null) : array
    {
        $arrConfigNames = empty($arrConfigNames) ? static::DEFAULT_CONFIGS_TO_GET : $arrConfigNames;
        $placeHolders   = array_fill(0, count($arrConfigNames), '?');

        $sql = "
            SELECT config_name, config_value
            FROM " . $this->getPhpBBTableName('config') . "
            WHERE config_name IN (" . implode(", ", $placeHolders) . ")
        ";

        return
            $this->getEntityManager()->getConnection()->prepare($sql)
                ->executeQuery($arrConfigNames)
                ->fetchAllKeyValue();

    }
}
