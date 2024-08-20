<?php
namespace App\Service;

use App\Entity\PhpBB\User as UserEntity;
use App\Exception\UserNotFoundException;
use App\ServiceCollection\Cms\ArticleCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class User extends BaseServiceEntity
{
    const string ENTITY_CLASS   = UserEntity::class;
    const NOT_FOUND_EXCEPTION   = UserNotFoundException::class;

    // 👀 https://turbolab.it/forum/memberlist.php?mode=viewprofile&u=5103
    const int SYSTEM_USER_ID    = 5103;

    // 👀 https://turbolab.it/forum/memberlist.php?mode=viewprofile&u=4015
    const int TESTER_USER_ID    = 4015;

    protected ?UserEntity $entity                   = null;
    protected ?array $arrAdditionalFields           = null;
    protected ?ArticleCollection $articlesAuthored  = null;
    protected ?int $articlesNum                     = null;


    public function __construct(
        protected UserUrlGenerator $urlGenerator, protected EntityManagerInterface $em, protected Factory $factory
    )
    {
        $this->clear();
    }

    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function setEntity(?UserEntity $entity = null) : static
    {
        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?UserEntity { return $this->entity; }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Load methods ***">
    public function loadByUsernameClean(string $usernameClean) : static
    {
        $this->clear();

        $entity = $this->em->getRepository(static::ENTITY_CLASS)->getByUsernameClean($usernameClean);

        if( empty($entity) ) {

            $exceptionClass = static::NOT_FOUND_EXCEPTION;
            throw new $exceptionClass($usernameClean);
        }

        return $this->setEntity($entity);
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 👔 User name ***">
    public function getUsername() : string { return $this->entity->getUsername(); }

    public function getUsernameClean() : string { return $this->entity->getUsernameClean(); }

    public function getFullName() : string
    {
        $username   = $this->getUsername();
        $personName = $this->loadAdditionalFields()['pf_tli_fullname'] ?? null;

        if( empty($personName) ) {
            return $username;
        }

        return "$username ($personName)";
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🪪 User data ***">
    public function isSystem() : bool { return $this->getId() == static::SYSTEM_USER_ID; }

    public function getEmail() : string { return $this->entity->getEmail(); }

    public function getBio() : ?string
    {
        $bio = $this->loadAdditionalFields()['pf_tli_bio'] ?? null;
        if( empty($bio) ) {
            return null;
        }

        $bio = str_ireplace(['turbolab'], ['TurboLab.it'], $bio);
        $bio = str_ireplace(['turbolab.it.it'], ['TurboLab.it'], $bio);

        return $bio;
    }


    public function getAvatarUrl() : ?string
    {
        if( $this->entity->getAvatarType() == 'avatar.driver.gravatar' ) {
            return 'https://secure.gravatar.com/avatar/' . md5( $this->getEmail() ) . '?s=128';
        }

        $avatarFile = $this->entity->getAvatarFile();
        if( !empty($avatarFile) ) {
            return "/forum/download/file.php?avatar=$avatarFile";
        }

        return null;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🪪 Additional, custom fields from phpBB ***">
    protected function loadAdditionalFields() : array
    {
        if( is_array($this->arrAdditionalFields) ) {
            return $this->arrAdditionalFields;
        }

        return
            $this->arrAdditionalFields =
                $this->em->getRepository(UserEntity::class)->getAdditionalFields( $this->getEntity() );
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** ✏ Articles ***">
    public function getArticles(?int $page = 1) : ArticleCollection
    {
        if( $this->articlesAuthored !== null ) {
            return $this->articlesAuthored;
        }

        return $this->articlesAuthored = $this->factory->createArticleCollection()->loadByAuthor($this, $page);
    }


    public function getArticlesNum(bool $formatted = true) : int|string
    {
        $num = $this->articlesNum = $this->articlesNum ??  $this->getArticles()->countTotalBeforePagination();

        if( !$formatted ) {
            return $num;
        }

        return number_format($num, 0, null, ".");
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 📩 Newsletter ***">
    public function isSubscribedToNewsletter() : bool { return $this->entity->getAllowMassEmail(); }

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


    public function getNewsletterUnsubscribeUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->urlGenerator->generateNewsletterUnsubscribeUrl($this, $urlType); }

    public function getNewsletterSubscribeUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->urlGenerator->generateNewsletterSubscribeUrl($this, $urlType); }


    public function getNewsletterOpenerUrl(
        ?string $redirectToUrl = null, bool $requiresUrlEncode = true, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL
    ) : string
    {
        return $this->urlGenerator->generateNewsletterOpenerUrl($this, $redirectToUrl, $requiresUrlEncode, $urlType);
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🕸️ URL ***">
    public function getUrl(?int $page = null, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->urlGenerator->generateUrl($this, $page, $urlType); }

    public function getForumUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->urlGenerator->generateForumProfileUrl($this, $urlType); }
    //</editor-fold>
}
