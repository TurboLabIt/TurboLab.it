<?php
namespace App\Service;

use App\Entity\PhpBB\User as UserEntity;
use App\Exception\UserNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class User extends BaseServiceEntity
{
    const string ENTITY_CLASS   = UserEntity::class;
    const NOT_FOUND_EXCEPTION   = UserNotFoundException::class;

    protected ?UserEntity $entity = null;


    public function __construct(
        protected UserUrlGenerator $urlGenerator, protected EntityManagerInterface $em, protected Factory $factory
    )
    {
        $this->clear();
    }


    public function setEntity(?UserEntity $entity = null) : static
    {
        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?UserEntity { return $this->entity; }


    public function isSubscribedToNewsletter() : bool
    {
        $result = $this->entity->getAllowMassEmail();
        return (bool)$result;
    }


    public function subscribeToNewsletter() : static
    {
        $this->entity->setAllowMassEmail(true);
        return $this;
    }


    public function unsubscribeFromNewsletter() : static
    {
        $this->entity->setAllowMassEmail(false);
        return $this;
    }


    public function getUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->urlGenerator->generateUrl($this, $urlType);
    }


    public function getForumUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->urlGenerator->generateForumProfileUrl($this, $urlType);
    }


    public function getNewsletterUnsubscribeUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->urlGenerator->generateNewsletterUnsubscribeUrl($this, $urlType);
    }


    public function getNewsletterSubscribeUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->urlGenerator->generateNewsletterSubscribeUrl($this, $urlType);
    }


    public function getNewsletterOpenerUrl(?string $redirectToUrl = null, bool $requiresUrlEncode = true, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->urlGenerator->generateNewsletterOpenerUrl($this, $redirectToUrl, $requiresUrlEncode, $urlType);
    }


    public function getUsername() : string { return $this->entity->getUsername(); }
    public function getEmail() : string { return $this->entity->getEmail(); }
}
