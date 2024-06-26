<?php
namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Result;
use Doctrine\Persistence\ManagerRegistry;


abstract class BaseRepository extends ServiceEntityRepository
{
    const string ENTITY_CLASS_NAME = '';
    protected array $cachedRs;


    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, static::ENTITY_CLASS_NAME);
    }


    protected function getTableName() : string
    {
        $tableName = $this->getEntityManager()->getClassMetadata($this->getClassName())->getTableName();
        return '`' . $tableName .'`';
    }


    protected function sqlQueryExecute(string $sqlQuery, array $arrParams = []) : Result
    {
        $stmt = $this->getEntityManager()->getConnection()->prepare($sqlQuery);
        foreach($arrParams as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        $result = $stmt->executeQuery();
        return $result;
    }


    protected function increase(string $fieldName, int $entityId, int $increaseOf = 1) : void
    {
        $sqlQuery =
            "UPDATE " . $this->getTableName() . " SET `" . $fieldName . "` = `" . $fieldName . "` + $increaseOf " .
            "WHERE id = :id";

        $this->sqlQueryExecute($sqlQuery, ["id" => $entityId]);
    }


    public function loadAll()
    {
        $this->cachedRs =
            $this->createQueryBuilder('t', 't.id')
                ->orderBy('t.id')
            ->getQuery()
            ->getResult();

        return $this->cachedRs;
    }


    public function selectOrNull(int $id)
    {
        if( empty($id) || !array_key_exists($id, $this->cachedRs) ) {
            return null;
        }

        return $this->cachedRs[$id];
    }


    public function selectOrNew(int $id)
    {
        $entity = $this->selectOrNull($id);
        if( !empty($entity) ) {
            return $entity;
        }

        $newEntity = new $this->_entityName();

        if( !empty($id) && method_exists($newEntity, 'setId') ) {
            $newEntity->setId($id);
        }

        return $newEntity;
    }
}
