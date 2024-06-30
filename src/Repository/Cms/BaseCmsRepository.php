<?php
namespace App\Repository\Cms;

use App\Entity\Cms\BaseCmsEntity;
use App\Repository\BaseRepository;
use Doctrine\ORM\QueryBuilder;


abstract class BaseCmsRepository extends BaseRepository
{
    //<editor-fold defaultstate="collapsed" desc="** QUERY BUILDERS **">
    protected function getQueryBuilder() : QueryBuilder
    {
        return
            $this->createQueryBuilder('t', 't.id')
                ->orderBy('t.updatedAt', 'DESC');
    }


    protected function getQueryBuilderComplete() : QueryBuilder
    {
        return $this->createQueryBuilder('t', 't.id');
    }


    public function getQueryBuilderCompleteFromSqlQuery(string $sqlToSelectIds, array $arrSqlSelectParams = []) : ?QueryBuilder
    {
        $arrIds = $this->sqlQueryExecute($sqlToSelectIds, $arrSqlSelectParams)->fetchFirstColumn();
        if( empty($arrIds) ) {
            return null;
        }

        return
            $this->getQueryBuilderComplete()
                ->andWhere('t.id IN (:articleIds)')
                ->setParameter("articleIds", $arrIds);
    }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="** SELECT BY IDs **">
    public function findComplete(int $id) : ?BaseCmsEntity
    {
        return
            $this->getQueryBuilderComplete()
                ->andWhere('t.id = :id')
                    ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();
    }


    public function findMultiple(array $arrIds) : array
    {
        return $this->findMutipleOrdered($this->getQueryBuilder(), $arrIds);
    }


    public function findMultipleComplete(array $arrIds) : array
    {
        return $this->findMutipleOrdered($this->getQueryBuilderComplete(), $arrIds);
    }


    protected function findMutipleOrdered(QueryBuilder $qb, array $arrIds) : array
    {
        $arrEntitiesUnorderd =
            $qb
                ->andWhere('t.id IN(:ids)')
                    ->setParameter('ids', $arrIds)
                ->getQuery()
                ->getResult();

        $arrEntities = [];
        foreach($arrIds as $id) {

            $id = (string)$id;
            $arrEntities[$id] = $arrEntitiesUnorderd[$id];
        }

        return $arrEntities;
    }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="** UPDATERS **">
    public function countOneView(int $entityId) : void
    {
        $this->increase("views", $entityId);
    }
    //</editor-fold>
}
