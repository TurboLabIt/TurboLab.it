<?php
namespace App\Service\Cms;

use App\Entity\Cms\Tag as TagEntity;
use App\Repository\Cms\TagRepository;
use App\Service\Factory;
use App\ServiceCollection\Cms\ArticleCollection;
use App\Trait\ViewableServiceTrait;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Tag extends BaseCmsService
{
    const string ENTITY_CLASS           = TagEntity::class;
    const string NOT_FOUND_EXCEPTION    = 'App\Exception\TagNotFoundException';

    const int ID_DEFAULT_TAG        = 642;      // 👀 https://turbolab.it/pc-642

    const int ID_WINDOWS            = 10;       // 👀 https://turbolab.it/windows-10
    const int ID_LINUX              = 27;       // 👀 https://turbolab.it/linux-27
    const int ID_ANDROID            = 28;       // 👀 https://turbolab.it/android-28
    const int ID_CRYPTOCURRENCIES   = 4904;     // 👀 https://turbolab.it/criptovalute-bitcoin-ethereum-litecoin-4904

    const int ID_FILESHARING        = 2914;     // 👀 https://turbolab.it/filesharing-p2p-peer-to-peer-2914
    const int ID_SECURITY           = 13;       // 👀 https://turbolab.it/sicurezza-13
    const int ID_WHAT_TO_BUY        = 640;      // 👀 https://turbolab.it/guida-mercato-640
    const int ID_VPN                = 2942;     // 👀 https://turbolab.it/vpn-2942
    const int ID_VIRTUALIZATION     = 535;      // 👀 https://turbolab.it/virtualizzazione-535
    const int ID_DEV                = 232;      // 👀 https://turbolab.it/programmazione-232
    const int ID_YOUTUBE            = 42;       // 👀 https://turbolab.it/youtube-42

    const int ID_MAC                = 26;       // 👀 https://turbolab.it/apple-mac-macos-26
    const int ID_STORAGE            = 570;      // 👀 https://turbolab.it/ssd-dischi-fissi-hard-disk-570

    const int ID_NEWSLETTER_TLI     = 1349;     // 👀 https://turbolab.it/newsletter-turbolab.it-1349
    const int ID_SPONSOR            = 5443;     // 👀 https://turbolab.it/sponsor-5443

    use ViewableServiceTrait;

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
    public function getTitleFormatted() : ?string
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
        return $arrMap[$id] ?? ["fa fa-" . $this->getSlug()];
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
}
