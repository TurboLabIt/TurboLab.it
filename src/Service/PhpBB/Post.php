<?php
namespace App\Service\PhpBB;

use App\Entity\PhpBB\Post as PostEntity;
use App\Repository\PhpBB\PostRepository;
use App\Service\BaseServiceEntity;
use App\Service\Factory;
use App\Service\HtmlProcessorBase;
use App\Service\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Post extends BaseServiceEntity
{
    const string ENTITY_CLASS = PostEntity::class;

    protected ?PostEntity $entity;


    public function __construct(protected Factory $factory) { $this->clear(); }


    public function getTitle() : ?string
    {
        // this returns: Commenti a &quot;Ricevere &quot;TurboLab.it&quot; via email: Come dis/iscriversi dalla newsletter&quot;
        $text = parent::getTitle();
        return HtmlProcessorBase::decode($text);
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getRepository() : PostRepository
    {
        /** @var PostRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(PostEntity::class);
        return $repository;
    }


    public function setEntity(?PostEntity $entity = null) : static
    {
        $this->entity = $entity ?? new (static::ENTITY_CLASS)();
        return $this;
    }

    public function getEntity() : ?PostEntity { return $this->entity ?? null; }

    public function getUser() : ?User { return $this->factory->createUser($this->entity->getUser()); }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 🕸️ URL ***">
    public function getUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->factory->getForumUrlGenerator()->generatePostViewUrl($this, $urlType);
    }
    //</editor-fold>
}
