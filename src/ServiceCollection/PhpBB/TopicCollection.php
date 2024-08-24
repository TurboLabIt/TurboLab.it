<?php
namespace App\ServiceCollection\PhpBB;

use App\Entity\PhpBB\Topic as TopicEntity;
use App\Service\PhpBB\Topic as TopicService;
use App\ServiceCollection\BaseServiceEntityCollection;


class TopicCollection extends BaseServiceEntityCollection
{
    const string ENTITY_CLASS = TopicService::ENTITY_CLASS;


    public function loadLatest(?int $num = null) : static
    {
        $arrTopics = $this->em->getRepository(static::ENTITY_CLASS)->findLatest($num);
        return $this->setEntities($arrTopics);
    }


    public function loadLatestForNewsletter() : static
    {
        $arrTopics = $this->em->getRepository(static::ENTITY_CLASS)->findLatestForNewsletter();
        return $this->setEntities($arrTopics);
    }

    
    public function loadRandom(?int $num = null) : static
    {
        $arrTopics = $this->em->getRepository(static::ENTITY_CLASS)->getRandomComplete($num);
        return $this->setEntities($arrTopics);
    }
    

    public function createService(?TopicEntity $entity = null) : TopicService { return $this->factory->createTopic($entity); }
}
