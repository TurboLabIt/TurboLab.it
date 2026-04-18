<?php
namespace App\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Meilisearch\Bundle\SearchManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;


#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class MeilisearchIndexerSubscriber
{
    public function __construct(
        protected SearchManagerInterface $searchManager,
        protected LoggerInterface $logger,
    ) {}


    public function postPersist(LifecycleEventArgs $args) : void
    {
        $this->safeIndex( $args->getObject() );
    }


    public function postUpdate(LifecycleEventArgs $args) : void
    {
        $this->safeIndex( $args->getObject() );
    }


    public function preRemove(LifecycleEventArgs $args) : void
    {
        $this->safeRemove( $args->getObject() );
    }


    protected function safeIndex(object $entity) : void
    {
        try {
            $this->searchManager->index($entity);

        } catch(Throwable $ex) {
            $this->logger->error('Meilisearch index failed: ' . $ex->getMessage(), [
                'exception' => $ex, 'entity' => $entity::class
            ]);
        }
    }


    protected function safeRemove(object $entity) : void
    {
        try {
            $this->searchManager->remove($entity);

        } catch(Throwable $ex) {
            $this->logger->error('Meilisearch remove failed: ' . $ex->getMessage(), [
                'exception' => $ex, 'entity' => $entity::class
            ]);
        }
    }
}
