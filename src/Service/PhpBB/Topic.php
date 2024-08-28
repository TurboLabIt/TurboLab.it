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


    public function __construct(
        protected ForumUrlGenerator $urlGenerator, protected EntityManagerInterface $em, protected Factory $factory
    )
    {
        $this->clear();
    }

    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getEntity() : ?TopicEntity { return $this->entity; }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🕸️ URL ***">
    public function getUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->urlGenerator->generateTopicViewUrl($this, $urlType); }

    public function getReplyUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->urlGenerator->generateTopicReplyUrl($this, $urlType); }

    public function getCommentsAjaxLoadingUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : ?string
        { return $this->urlGenerator->generateCommentsAjaxLoadingUrl($this, $urlType); }
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
