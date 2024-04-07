<?php
namespace App\ServiceCollection\PhpBB;

use App\ServiceCollection\BaseServiceEntityCollection;
use App\Entity\PhpBB\Topic as TopicEntity;
use App\Service\PhpBB\Topic as TopicService;


class TopicCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = TopicService::ENTITY_CLASS;


    public function loadLatest() : static
    {
        $arrTopics = $this->em->getRepository(static::ENTITY_CLASS)->findLatest();
        return $this->setEntities($arrTopics);
    }


    public function loadLatestForNewsletter() : static
    {
        $arrTopics = $this->em->getRepository(static::ENTITY_CLASS)->findLatestForNewsletter();
        return $this->setEntities($arrTopics);
    }


    public function createService(?TopicEntity $entity = null) : TopicService { return $this->factory->createTopic($entity); }
}
