<?php
namespace App\Service\Cms;

use App\Entity\Cms\Tag as TagEntity;
use App\Service\Factory;
use App\ServiceCollection\Cms\ArticleCollection;
use App\Trait\ViewableServiceTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
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
    const int ID_YOUTUBE            = 42;       // 👀 https://turbolab.it/youtube-42

    const int ID_MAC                = 26;       // 👀 https://turbolab.it/apple-mac-macos-26

    const int ID_NEWSLETTER_TLI     = 1349;     // 👀 https://turbolab.it/newsletter-turbolab.it-1349
    const int ID_SPONSOR            = 5443;     // 👀 https://turbolab.it/sponsor-5443

    use ViewableServiceTrait;

    protected ?TagEntity $entity                    = null;
    protected ?ArticleCollection $articlesTagged    = null;
    protected ?Article $firstArticle                = null;


    public function __construct(protected TagUrlGenerator $urlGenerator, protected EntityManagerInterface $em, protected Factory $factory)
    {
        $this->clear();
    }


    public function setEntity(?TagEntity $entity = null) : static
    {
        $this->localViewCount = $entity->getViews();
        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?TagEntity { return $this->entity; }


    public function getTitleFormatted() : ?string
    {
        // TODO DB-driven titleFormatted for tags
        $titleFormatted =
            match( $this->getId() ) {
                static::ID_CRYPTOCURRENCIES     => 'Bitcoin e cripto',
                static::ID_FILESHARING          => 'Filesharing peer-to-peer (P2P)',
                static::ID_MAC                  => 'Mac / macOS',
                static::ID_YOUTUBE              => 'YouTube (trucchi e app)',
                default                         => null
            };

        if( !empty($titleFormatted) ) {
            return $titleFormatted;
        }

        $title = $this->getTitle();
        $arrSpecialCasesMap = [
            'iphone'    => 'iPhone',
            'ipad'      => 'iPad',
            'ipod'      => 'iPod',
            'youtube'   => 'YouTube',
        ];

        $title = str_replace( array_keys($arrSpecialCasesMap), $arrSpecialCasesMap, $title);

        if( mb_strlen($title) <= 3 ) {
            return mb_strtoupper($title);
        }

        return ucwords($title);
    }


    public function checkRealUrl(string $tagSlugDashId, ?int $page = null) : ?string
    {
        $pageSlug       = empty($page) || $page < 2 ? null : ("/$page");
        $candidateUrl   = '/' . $tagSlugDashId . $pageSlug;
        $realUrl        = $this->urlGenerator->generateUrl($this, $page, UrlGeneratorInterface::ABSOLUTE_PATH);
        $result         = $candidateUrl == $realUrl ? null : $this->getUrl();
        return $result;
    }


    public function loadByTitle(string $title) : static
    {
        $this->clear();
        $entity = $this->em->getRepository(static::ENTITY_CLASS)->findByTitle($title);

        if( empty($this->entity) ) {

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
            static::ID_CRYPTOCURRENCIES         => ["fa-brands fa-bitcoin", "fa-brands fa-ethereum"],
            static::ID_FILESHARING              => ["fa-solid fa-download"],
            static::ID_SECURITY                 => ["fa-solid fa-shield-halved"],
            static::ID_WHAT_TO_BUY              => ["fa-solid fa-laptop"],
            static::ID_VPN                      => ["fa-solid fa-user-secret"],
            static::ID_VIRTUALIZATION           => ["fa-solid fa-clone"]
        ];

        $id = $this->getId();
        return $arrMap[$id] ?? [];
    }


    public function getArticles(?int $page = 1) : ArticleCollection
    {
        if( $this->articlesTagged !== null ) {
            return $this->articlesTagged;
        }

        $this->articlesTagged = $this->factory->createArticleCollection()->loadByTag($this, $page);
        return $this->articlesTagged;
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


    public function getUrl(?int $page = null, int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->urlGenerator->generateUrl($this, $page, $urlType);
    }


    public function getAuthors() : Collection { return $this->entity->getAuthors(); }
    public function getRanking() : ?int { return $this->entity->getRanking(); }
}
