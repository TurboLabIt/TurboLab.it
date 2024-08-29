<?php
namespace App\Service\PhpBB;

use App\Entity\PhpBB\Topic as TopicEntity;
use App\Repository\PhpBB\TopicRepository;
use App\Service\BaseServiceEntity;
use App\Service\Factory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Topic extends BaseServiceEntity
{
    const string ENTITY_CLASS = TopicEntity::class;

    // 👀 https://turbolab.it/forum/viewtopic.php?t=12749
    const int ID_NEWSLETTER_COMMENTS = 12749;

    protected ?TopicEntity $entity;


    public function __construct(protected Factory $factory) { $this->clear(); }

    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getRepository() : TopicRepository
        { return $this->factory->getEntityManager()->getRepository(TopicEntity::class); }

    public function setEntity(?TopicEntity $entity = null) : static
    {
        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?TopicEntity { return $this->entity ?? null; }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🕸️ URL ***">
    public function getUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->factory->getForumUrlGenerator()->generateTopicViewUrl($this, $urlType); }

    public function getReplyUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->factory->getForumUrlGenerator()->generateTopicReplyUrl($this, $urlType); }

    public function getCommentsAjaxLoadingUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : ?string
        { return $this->factory->getForumUrlGenerator()->generateCommentsAjaxLoadingUrl($this, $urlType); }
    //</editor-fold>

    public function getLastPostDateTime() : \DateTime
    {
        $oDateTime = \DateTime::createFromFormat('U', $this->entity->getLastPostTime());
        $oDateTime->setTimezone(new \DateTimeZone('Europe/Rome'));
        return $oDateTime;
    }

    public function getFirstPostId(): ?int { return $this->entity->getFirstPostId(); }

    public function getPostNum() : ?int { return $this->entity->getPostNum(); }
}
