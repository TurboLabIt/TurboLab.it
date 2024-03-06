<?php
namespace App\Service\PhpBB;

use App\Entity\PhpBB\Topic as TopicEntity;
use App\Service\BaseServiceEntity;
use App\Service\Factory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Topic extends BaseServiceEntity
{
    const string ENTITY_CLASS = TopicEntity::class;

    // 👀 https://turbolab.it/forum/viewtopic.php?t=12749
    const int ID_NEWSLETTER_COMMENTS = 12749;

    protected ?TopicEntity $entity = null;


    public function __construct(
        protected ForumUrlGenerator $urlGenerator, protected EntityManagerInterface $em, protected Factory $factory
    )
    {
        $this->clear();
    }


    public function setEntity(?TopicEntity $entity = null) : static
    {
        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?TopicEntity { return $this->entity; }


    public function getUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->urlGenerator->generateTopicViewUrl($this, $urlType);
    }


    public function getPostNum() : ?int { return $this->entity->getPostNum(); }
}
