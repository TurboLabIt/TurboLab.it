<?php
namespace App\ServiceCollection;

use App\Service\Factory;
use Doctrine\ORM\EntityManagerInterface;
use TurboLabIt\Foreachable\Foreachable;


abstract class BaseServiceEntityCollection implements \Iterator, \Countable, \ArrayAccess
{
    const ENTITY_CLASS = null;

    protected int $countTotalBeforePagination = 0;

    use Foreachable;


    public function __construct(protected EntityManagerInterface $em, protected Factory $factory)
    { }


    public function load(array $arrIds) : array
    {
        $arrEntities = $this->em->getRepository(static::ENTITY_CLASS)->findBy(["id" => $arrIds], ["updatedAt" => 'DESC']);
        $this->setEntities($arrEntities);

        return $this->arrData;
    }


    public function setEntities(?iterable $arrEntities) : static
    {
        $this->clear();
        $arrEntities = empty($arrEntities) ? [] : $arrEntities;

        foreach($arrEntities as $entity) {

            $id = (string)$entity->getId();
            $service = $this->createService($entity);
            $this->arrData[$id] = $service;
        }

        $this->countTotalBeforePagination =
            $arrEntities instanceof \Doctrine\ORM\Tools\Pagination\Paginator ? $arrEntities->count() : $this->count();

        return $this;
    }


    public function countTotalBeforePagination(): int
    {
        return $this->countTotalBeforePagination;
    }


    // 🔥 Implement these abstract method as if they were uncommented! (different types in signature make them unusable)
    // abstract public function createService(?BaseEntity $entity = null) : BaseService;
}
