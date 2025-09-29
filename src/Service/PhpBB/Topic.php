<?php
namespace App\Service\PhpBB;

use App\Entity\PhpBB\Topic as TopicEntity;
use App\Repository\PhpBB\TopicRepository;
use App\Service\BaseServiceEntity;
use App\Service\Factory;
use App\Service\HtmlProcessorBase;
use DateTime;
use DateTimeZone;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Topic extends BaseServiceEntity
{
    const string ENTITY_CLASS = TopicEntity::class;

    // 👀 https://turbolab.it/forum/viewtopic.php?t=2676
    const int ID_NEWSLETTER_COMMENTS    = 2676;
    const int TITLE_MAX_LENGTH_FRONTEND = 120;

    protected ?TopicEntity $entity;


    public function __construct(protected Factory $factory) { $this->clear(); }


    public function getTitle() : ?string
    {
        // this will return: Commenti a &quot;Ricevere &quot;TurboLab.it&quot; via email: Come dis/iscriversi dalla newsletter&quot;
        return $this->getEntity()->getTitle();
    }


    public static function buildCommentsTitle(string $title, ?int $id = null) : string
    {
        $title = HtmlProcessorBase::decode($title);
        $titleLength = mb_strlen($title);

        $maxTitleLength = static::TITLE_MAX_LENGTH_FRONTEND - 20;
        $maxTitleLength -= empty($id) ? 0 : ( mb_strlen(" (#$id)") );

        $processedTitle =
            $titleLength <= ($maxTitleLength+3) ? $title
                : (mb_substr($title, 0, $maxTitleLength - 3) . '...');

        $postTitle = "Commenti a \"$processedTitle\"";

        if( !empty($id) ) {
            $postTitle .= " (#$id)";
        }

        // phpBB come salva l'HTML a database? https://turbolab.it/forum/viewtopic.php?t=13553
        $postTitle = htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8');
        return trim($postTitle);
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getRepository() : TopicRepository
    {
        /** @var TopicRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(TopicEntity::class);
        return $repository;
    }

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

    public function getLastPostDateTime() : DateTime
    {
        $oDateTime = DateTime::createFromFormat('U', $this->entity->getLastPostTime());
        $oDateTime->setTimezone(new DateTimeZone('Europe/Rome'));
        return $oDateTime;
    }


    public function getFirstPostId() : ?int { return $this->entity->getFirstPostId(); }

    public function getPostNum() : ?int { return $this->entity->getPostNum(); }
}
