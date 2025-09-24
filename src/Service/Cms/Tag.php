<?php
namespace App\Service\Cms;

use App\Entity\Cms\Tag as TagEntity;
use App\Exception\TagNotFoundException;
use App\Repository\Cms\TagRepository;
use App\Service\Factory;
use App\ServiceCollection\Cms\ArticleCollection;
use App\Trait\VisitableServiceTrait;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Tag extends BaseCmsService
{
    const string ENTITY_CLASS           = TagEntity::class;
    const string TLI_CLASS              = TagEntity::TLI_CLASS;
    const string NOT_FOUND_EXCEPTION    = TagNotFoundException::class;

    const int ID_DEFAULT_TAG        = 642;      // 👀 https://turbolab.it/tag-642
    const int ID_TEST_NO_ARTICLES   = 12600;    // 👀 https://turbolab.it/tag-12600
    const int ID_NEWSLETTER_TLI     = 1349;     // 👀 https://turbolab.it/tag-1349
    const int ID_SPONSOR            = 5443;     // 👀 https://turbolab.it/tag-5443

    const int ID_ANTIVIRUS_MALWARE  = 2;        // 👀 https://turbolab.it/tag-2
    const int ID_FAKE_NEWS          = 3;        // 👀 https://turbolab.it/tag-3
    const int ID_UNINSTALL          = 4;        // 👀 https://turbolab.it/tag-4
    const int ID_HARDWARE           = 5;        // 👀 https://turbolab.it/tag-5
    const int ID_SMARTPHONE         = 6;        // 👀 https://turbolab.it/tag-6
    const int ID_SOFTWARE_UPDATE    = 7;        // 👀 https://turbolab.it/tag-7
    const int ID_WINDOWS            = 10;       // 👀 https://turbolab.it/tag-10
    const int ID_SECURITY           = 13;       // 👀 https://turbolab.it/tag-13
    const int ID_INTERNET_PROVIDER  = 17;       // 👀 https://turbolab.it/tag-17
    const int ID_LAN                = 22;       // 👀 https://turbolab.it/tag-22
    const int ID_WEBSERVICES        = 24;       // 👀 https://turbolab.it/tag-24
    const int ID_MAC                = 26;       // 👀 https://turbolab.it/tag-26
    const int ID_LINUX              = 27;       // 👀 https://turbolab.it/tag-27
    const int ID_ANDROID            = 28;       // 👀 https://turbolab.it/tag-28
    const int ID_IOS                = 39;       // 👀 https://turbolab.it/tag-39
    const int ID_YOUTUBE            = 42;       // 👀 https://turbolab.it/tag-42
    const int ID_DEV                = 232;      // 👀 https://turbolab.it/tag-232
    const int ID_USB                = 275;      // 👀 https://turbolab.it/tag-275
    const int ID_WINDOWS_UPDATE     = 280;      // 👀 https://turbolab.it/tag-280
    const int ID_VIRTUALIZATION     = 535;      // 👀 https://turbolab.it/tag-535
    const int ID_STORAGE            = 570;      // 👀 https://turbolab.it/tag-570
    const int ID_WHAT_TO_BUY        = 640;      // 👀 https://turbolab.it/tag-640
    const int ID_LAPTOP             = 897;      // 👀 https://turbolab.it/tag-897
    const int ID_SERVER             = 1224;     // 👀 https://turbolab.it/tag-1224
    const int ID_FILESHARING        = 2914;     // 👀 https://turbolab.it/tag-2914
    const int ID_VPN                = 2942;     // 👀 https://turbolab.it/tag-2942
    const int ID_WAKE_ON_LAN        = 3177;     // 👀 https://turbolab.it/tag-3177
    const int ID_CRYPTOCURRENCIES   = 4904;     // 👀 https://turbolab.it/tag-4904
    const int ID_ADBLOCK            = 8892;     // 👀 https://turbolab.it/tag-8892

    use VisitableServiceTrait;

    protected ?TagEntity $entity;
    protected ?ArticleCollection $articlesTagged    = null;
    protected ?Article $firstArticle                = null;


    public function __construct(protected Factory $factory) { $this->clear(); }

    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getRepository() : TagRepository
    {
        /** @var TagRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(TagEntity::class);
        return $repository;
    }

    public function setEntity(?TagEntity $entity = null) : static
    {
        $this->localViewCount = $entity->getViews();
        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?TagEntity { return $this->entity ?? null; }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🛋️ Text ***">
    public function getNavTitle() : ?string
    {
        // TODO DB-driven titleFormatted for tags
        $titleFormatted =
            match( $this->getId() ) {
                static::ID_CRYPTOCURRENCIES     => 'Bitcoin e cripto',
                static::ID_FILESHARING          => 'Filesharing peer-to-peer (P2P)',
                static::ID_MAC                  => 'Mac / macOS',
                static::ID_YOUTUBE              => 'YouTube (trucchi e app)',
                static::ID_STORAGE              => 'SSD e HDD (hard disk)',
                default                         => null
            };

        if( !empty($titleFormatted) ) {
            return $titleFormatted;
        }

        $title = $this->getTitle();
        $arrSpecialCasesMap = [
            'iphone'        => 'iPhone',
            'ipad'          => 'iPad',
            'ipod'          => 'iPod',
            'youtube'       => 'YouTube',
            'turbolab'      => 'TurboLab',
            'ssd'           => 'SSD',
            'hdd'           => 'HDD',
        ];

        $title = str_replace( array_keys($arrSpecialCasesMap), $arrSpecialCasesMap, $title);

        if( mb_strlen($title) <= 3 ) {
            return mb_strtoupper($title);
        }

        return ucwords($title);
    }


    public function getTitleForHTMLAttribute() : ?string
    {
        return $this->encodeTextForHTMLAttribute( $this->getNavTitle() );
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🕸️ URL ***">
    public function getUrl(?int $page = null, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->factory->getTagUrlGenerator()->generateUrl($this, $page, $urlType); }

    public function checkRealUrl(string $tagSlugDashId, ?int $page = null) : ?string
    {
        $pageSlug       = empty($page) || $page < 2 ? null : ("/$page");
        $candidateUrl   = '/' . $tagSlugDashId . $pageSlug;
        $realUrl =
            $this->factory->getTagUrlGenerator()->generateUrl(
                $this, $page, UrlGeneratorInterface::ABSOLUTE_PATH
            );

        return $candidateUrl == $realUrl ? null : $this->getUrl();
    }

    public function getSlug() : ?string { return $this->factory->getTagUrlGenerator()->buildSlug($this); }
    //</editor-fold>


    public function loadByTitle(string $title) : static
    {
        $this->clear();
        $entity = $this->getRepository()->findByTitle($title);

        if( empty($entity) ) {

            $exceptionClass = static::NOT_FOUND_EXCEPTION;
            throw new $exceptionClass($title);
        }

        return $this->setEntity($entity);
    }


    public function getActiveMenu() : ?string
    {
        // TODO DB-driven activeMenu for tags
        return
            match( $this->getId() ) {
                static::ID_WINDOWS, static::ID_LINUX, static::ID_ANDROID,
                static::ID_CRYPTOCURRENCIES => $this->getSlug(),
                // centos, ubuntu
                1009, 584   => 'linux',
                default     => 'guide',
            };
    }


    public function getFontAwesomeIcon() : array
    {
        // TODO DB-driven Font Awesome icon for tags
        $arrMap = [
            static::ID_CRYPTOCURRENCIES => ["fa-brands fa-bitcoin", "fa-brands fa-ethereum"],
            static::ID_FILESHARING      => ["fa-solid fa-download"],
            static::ID_SECURITY         => ["fa-solid fa-shield-halved"],
            static::ID_WHAT_TO_BUY      => ["fa-solid fa-laptop"],
            static::ID_VPN              => ["fa-solid fa-user-secret"],
            static::ID_VIRTUALIZATION   => ["fa-solid fa-clone"],
            static::ID_DEV              => ["fa-brands fa-dev"]
        ];

        $id = $this->getId();
        return $arrMap[$id] ?? [];
    }


    public function getArticles(?int $page = 1) : ArticleCollection
    {
        if( $this->articlesTagged !== null ) {
            return $this->articlesTagged;
        }

        return $this->articlesTagged = $this->factory->createArticleCollection()->loadByTag($this, $page);
    }


    public function getFirstArticle() : ?Article
    {
        if( !empty($this->firstArticle) ) {
            return $this->firstArticle;
        }

        $this->firstArticle = $this->articlesTagged->first();
        return $this->firstArticle;
    }


    public function getSpotlightOrDefaultUrlFromArticles(string $size) : string
    {
        $firstArticle = $this->getFirstArticle();
        if( empty($firstArticle) ) {
            return $this->factory->createDefaultSpotlight()->getShortUrl($size);
        }

        return $firstArticle->getSpotlightOrDefaultUrl($size);
    }


    public function getAuthors() : Collection { return $this->entity->getAuthors(); }

    public function getRanking() : ?int { return $this->entity->getRanking(); }

    public function getReplacement() : ?Tag
    {
        $entity = $this->entity->getReplacement();
        return empty($entity) ? null : $this->factory->createTag($entity);
    }
}
