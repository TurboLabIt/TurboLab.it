<?php
namespace App\Service;

use App\Entity\Cms\Article;
use App\Entity\PhpBB\User as UserEntity;
use App\Exception\UserNotFoundException;
use App\Repository\PhpBB\UserRepository;
use App\ServiceCollection\Cms\BaseArticleCollection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class User extends BaseServiceEntity
{
    const string ENTITY_CLASS   = UserEntity::class;
    const NOT_FOUND_EXCEPTION   = UserNotFoundException::class;

    // 👀 https://turbolab.it/forum/memberlist.php?mode=viewprofile&u=5103
    const int SYSTEM_USER_ID    = 5103;

    // 👀 https://turbolab.it/forum/memberlist.php?mode=viewprofile&u=4015
    const int TESTER_USER_ID    = 4015;

    protected ?UserEntity $entity           = null;
    protected ?array $arrAdditionalFields   = null;
    protected ?int $articlesNum             = null;
    protected array $arrArticlesCollections  = [];


    public function __construct(protected Factory $factory) { $this->clear(); }

    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getRepository() : UserRepository
    {
        /** @var UserRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(UserEntity::class);
        return $repository;
    }

    public function setEntity(?UserEntity $entity = null) : static
    {
        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?UserEntity { return $this->entity ?? null; }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Load methods ***">
    public function loadByUsernameClean(string $usernameClean) : static
    {
        $this->clear();

        $entity = $this->getRepository()->getByUsernameClean($usernameClean);

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
        $username = $this->getUsername();
        $usernameDecoded = html_entity_decode($username, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $usernameEncoded = htmlspecialchars($usernameDecoded, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');

        $personName = $this->loadAdditionalFields()['pf_tli_fullname'] ?? null;

        if( empty($personName) ) {
            return $usernameEncoded;
        }

        $personNameDecoded = html_entity_decode($personName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $personNameEncoded = htmlspecialchars($personNameDecoded, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');

        return "$usernameEncoded ($personNameEncoded)";
    }


    public function getFullNameForHTMLAttribute() : ?string
    {
        return $this->encodeTextForHTMLAttribute( $this->getFullName() );
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🪪 User data ***">
    public function isSystem() : bool { return $this->getId() == static::SYSTEM_USER_ID; }

    public function getEmail() : string { return $this->entity->getEmail(); }

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

    protected function loadAdditionalFields() : array
    {
        if( is_array($this->arrAdditionalFields) ) {
            return $this->arrAdditionalFields;
        }

        return $this->arrAdditionalFields = $this->getRepository()->getAdditionalFields( $this->getEntity() );
    }

    public function getBio() : ?string
    {
        $bio = $this->loadAdditionalFields()['pf_tli_bio'] ?? null;
        if( empty($bio) ) {
            return null;
        }

        $bio = str_ireplace(['turbolab'], ['TurboLab.it'], $bio);
        return str_ireplace(['turbolab.it.it'], ['TurboLab.it'], $bio);
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 👮 Roles ***">
    public function isEditor() : bool { return $this->entity->isEditor(); }

    public function isAdmin() : bool { return $this->entity->isAdmin(); }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** ✏ Articles ***">
    public function getArticlesDraft() : BaseArticleCollection
    {
        if( array_key_exists(Article::PUBLISHING_STATUS_DRAFT, $this->arrArticlesCollections) ) {
            return $this->arrArticlesCollections[Article::PUBLISHING_STATUS_DRAFT];
        }

        return
            $this->arrArticlesCollections[Article::PUBLISHING_STATUS_DRAFT] =
                $this->factory->createArticleAuthorCollection($this)->loadDrafts();
    }


    public function getArticlesInReview() : BaseArticleCollection
    {
        if( array_key_exists(Article::PUBLISHING_STATUS_READY_FOR_REVIEW, $this->arrArticlesCollections) ) {
            return $this->arrArticlesCollections[Article::PUBLISHING_STATUS_READY_FOR_REVIEW];
        }

        return
            $this->arrArticlesCollections[Article::PUBLISHING_STATUS_READY_FOR_REVIEW] =
                $this->factory->createArticleAuthorCollection($this)->loadInReview();
    }


    public function getArticlesPublished(?int $page = 1) : BaseArticleCollection
    {
        if( array_key_exists(Article::PUBLISHING_STATUS_PUBLISHED, $this->arrArticlesCollections) ) {
            return $this->arrArticlesCollections[Article::PUBLISHING_STATUS_PUBLISHED];
        }

        return
            $this->arrArticlesCollections[Article::PUBLISHING_STATUS_PUBLISHED] =
                $this->factory->createArticleAuthorCollection($this)->loadPublished($page);
    }



    public function getArticlesLatestPublished() : BaseArticleCollection
    {
        if( array_key_exists('latest-published', $this->arrArticlesCollections) ) {
            return $this->arrArticlesCollections['latest-published'];
        }

        return
            $this->arrArticlesCollections['latest-published'] =
                $this->factory->createArticleAuthorCollection($this)->loadLatestPublished();
    }


    public function getArticlesNum(bool $formatted = true) : int|string
    {
        $num = $this->articlesNum = $this->articlesNum ?? $this->getArticlesPublished()->countTotalBeforePagination();

        if( !$formatted ) {
            return $num;
        }

        return number_format($num, 0, null, ".");
    }


    public function getArticlesUpcoming() : BaseArticleCollection
    {
        if( array_key_exists('upcoming', $this->arrArticlesCollections) ) {
            return $this->arrArticlesCollections['upcoming'];
        }

        return
            $this->arrArticlesCollections['upcoming'] =
                $this->factory->createArticleAuthorCollection($this)->loadUpcoming();
    }


    public function getArticlesKo() : BaseArticleCollection
    {
        if( array_key_exists('ko', $this->arrArticlesCollections) ) {
            return $this->arrArticlesCollections['ko'];
        }

        return
            $this->arrArticlesCollections['ko'] =
                $this->factory->createArticleAuthorCollection($this)->loadKo();
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
        { return $this->factory->getUserUrlGenerator()->generateNewsletterUnsubscribeUrl($this, $urlType); }

    public function getNewsletterSubscribeUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->factory->getUserUrlGenerator()->generateNewsletterSubscribeUrl($this, $urlType); }


    public function getNewsletterOpenerUrl(
        ?string $redirectToUrl = null, bool $requiresUrlEncode = true, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL
    ) : string
    {
        return $this->factory->getUserUrlGenerator()->generateNewsletterOpenerUrl($this, $redirectToUrl, $requiresUrlEncode, $urlType);
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🕸️ URL ***">
    public function getUrl(?int $page = null, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->factory->getUserUrlGenerator()->generateUrl($this, $page, $urlType); }

    public function getForumUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->factory->getUserUrlGenerator()->generateForumProfileUrl($this, $urlType); }
    //</editor-fold>
}
