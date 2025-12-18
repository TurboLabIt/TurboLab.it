<?php
namespace App\Service\Cms;

use App\Entity\Cms\Article as ArticleEntity;
use App\Entity\Cms\ArticleTag;
use App\Exception\ArticleNotFoundException;
use App\Repository\Cms\ArticleRepository;
use App\Service\Cms\Image as ImageService;
use App\Service\Cms\Tag as TagService;
use App\Service\Factory;
use App\Service\HtmlProcessorBase;
use App\Service\HtmlProcessorForDisplay;
use App\Service\PhpBB\Topic;
use App\Trait\ArticleFormatsTrait;
use App\Trait\AuthorableTrait;
use App\Trait\CommentsTopicStatusesTrait;
use App\Trait\PublishingStatusesTrait;
use App\Trait\VisitableServiceTrait;
use DateTime;
use DateTimeInterface;
use IntlDateFormatter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Article extends BaseCmsService
{
    const string ENTITY_CLASS           = ArticleEntity::class;
    const string TLI_CLASS              = ArticleEntity::TLI_CLASS;
    const string NOT_FOUND_EXCEPTION    = ArticleNotFoundException::class;

    const int ID_FORUM_IMAGES       = 24;       // 👀 https://turbolab.it/24
    const int ID_HOW_TO_JOIN        = 28;       // 👀 https://turbolab.it/28
    const int ID_ABOUT_US           = 40;       // 👀 https://turbolab.it/40
    const int ID_HOW_TO_WRITE       = 46;       // 👀 https://turbolab.it/46
    const int ID_ISSUE_REPORT       = 49;       // 👀 https://turbolab.it/49
    const int ID_FORUM_RULES        = 161;      // 👀 https://turbolab.it/161
    const int ID_PUBLISH_NEWS       = 222;      // 👀 https://turbolab.it/222
    const int ID_NEWSLETTER         = 402;      // 👀 https://turbolab.it/402
    const int ID_PRIVACY_POLICY     = 617;      // 👀 https://turbolab.it/617
    const int ID_COOKIE_POLICY      = 681;      // 👀 https://turbolab.it/681
    const int ID_DONATIONS          = 1126;     // 👀 https://turbolab.it/1126
    const int ID_PUBLISH_ARTICLE    = 3990;     // 👀 https://turbolab.it/3990
    const int ID_SIGN_ARTICLE       = 2329;     // 👀 https://turbolab.it/2329
    const int ID_BITTORRENT_GUIDE   = 669;      // 👀 https://turbolab.it/669
    const int ID_QUALITY_TEST       = 1939;     // 👀 https://turbolab.it/1939
    const int ID_EMULE_GUIDE        = 3020;     // 👀 https://turbolab.it/3020

    use AuthorableTrait, PublishingStatusesTrait, ArticleFormatsTrait, CommentsTopicStatusesTrait, VisitableServiceTrait;

    //<editor-fold defaultstate="collapsed" desc="*** 🍹 Class properties ***">
    protected ArticleEntity $entity;
    protected ?array $arrImages             = null;
    protected ?array $arrTags               = null;
    protected ?array $arrFiles              = null;
    protected ?ImageService $spotlight      = null;
    protected HtmlProcessorForDisplay $htmlProcessor;
    protected ?Topic $commentsTopic         = null;
    protected ?string $articleBodyForDisplay = null;
    protected array $arrPrevNextArticles    = [];
    protected ?array $arrBadges             = null;
    //</editor-fold>

    public function __construct(protected Factory $factory) { $this->clear(); }


    public function clear() : static
    {
        parent::clear();

        $this->htmlProcessor = new HtmlProcessorForDisplay($this->factory);

        foreach([
                'arrTags', 'arrAuthors', 'arrFiles', 'arrAuthors',
                'spotlight', 'commentsTopic', 'articleBodyForDisplay', 'arrImages'
            ] as $property) {
            $this->$property = null;
        }

        $this->arrPrevNextArticles = [];

        return $this;
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getRepository() : ArticleRepository
    {
        /** @var ArticleRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(static::ENTITY_CLASS);
        return $repository;
    }

    public function setEntity(?ArticleEntity $entity = null) : static
    {
        $this->localViewCount   = $entity->getViews();
        $this->entity           = $entity;

        return $this;
    }

    public function getEntity() : ?ArticleEntity { return $this->entity ?? null; }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 👮🏻 Special access ***">
    public function isListable() : bool { return $this->factory->createArticleSentinel($this)->canList(); }

    public function isReadable() : bool { return $this->factory->createArticleSentinel($this)->canView(); }

    public function isEditable() : bool { return $this->factory->createArticleSentinel($this)->canEdit(); }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 🗞️ Publishing ***">
    public function isVisitable() : bool { return in_array($this->getPublishingStatus(), static::PUBLISHING_STATUSES_VISIBLE); }

    public function getPublishingStatus() : int { return $this->entity->getPublishingStatus(); }

    public function isDraft() : bool { return $this->entity->getPublishingStatus() == static::PUBLISHING_STATUS_DRAFT; }

    public function isInReview() : bool { return $this->entity->getPublishingStatus() == static::PUBLISHING_STATUS_READY_FOR_REVIEW; }

    public function isPublished() : bool
    {
        return $this->entity->getPublishingStatus() == static::PUBLISHING_STATUS_PUBLISHED && !empty( $this->getPublishedAt() );
    }

    public function isKo() : bool { return $this->entity->getPublishingStatus() == static::PUBLISHING_STATUS_KO; }

    public function getPublishedAt() : ?DateTimeInterface { return $this->entity->getPublishedAt(); }

    public function getPublishedAtRecencyLabel() : ?string
    {
        if( !$this->isPublished() ) {
            return null;
        }

        $diff = $this->getPublishedAt()->getTimestamp() - (new DateTime())->getTimestamp();

        // upcoming, less than 24 ours)
        if( $diff > 0 && $diff <= 24 * 60 * 60 ) {
            return 'Uscirà FRA POCO!';
        }

        // upcoming, 3 days
        if( $diff > 0 && $diff <= 72 * 60 * 60 ) {
            return 'Uscirà fra pochi giorni';
        }

        // upcoming
        if( $diff > 0 ) {
            return 'Uscirà prossimamente';
        }

        // past, earlier today
        if( $diff > -24 * 60 * 60 ) {
            return 'Uscito OGGI!';
        }

        // past, recent
        if( abs($diff) <= 72 * 60 * 60 ) {
            return 'Uscito di recente';
        }

        return null;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🗞️ Format ***">
    public function isNews() : bool { return $this->entity->getFormat() == static::FORMAT_NEWS; }

    public function getFormat() : string { return $this->entity->getFormat(); }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🖼️ Images ***">
    public function getImages() : array
    {
        if( !is_array($this->arrImages) ) {

            $this->arrImages = [];

            foreach($this->entity->getImages() as $junction) {

                $junctionRanking    = $junction->getRanking();
                $imageEntity        = $junction->getImage();
                $imageId            = $imageEntity->getId();

                $this->arrImages[$imageId] =
                    $this->factory->createImage($imageEntity)
                        ->setTempOrder($junctionRanking);
            }

            usort($this->arrImages, function(Image $img1, Image $img2) {
                return $img1->getTempOrder() <=> $img2->getTempOrder();
            });
        }

        return $this->arrImages;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** ☀️ Spotlight ***">
    public function getSpotlightOrDefaultUrl(string $size) : string
    {
        return $this->getSpotlightOrDefault()->getUrl($size);
    }

    public function getSpotlightUrl(string $size) : ?string { return $this->getSpotlight()?->getUrl($size); }

    public function getSpotlight() : ?ImageService
    {
        if( !$this->isListable() ) {
            return null;
        }

        if( !empty($this->spotlight) ) {
            return $this->spotlight;
        }

        $spotlightEntity = $this->entity->getSpotlight();
        if( empty($spotlightEntity) ) {
            return null;
        }

        $this->spotlight = $this->factory->createImage($spotlightEntity);
        return $this->spotlight;
    }


    public function getSpotlightOrDefault() : ImageService
    {
        $spotlight = $this->getSpotlight();
        if( !empty($spotlight) ) {
            return $spotlight;
        }

        return $this->spotlight = $this->factory->createDefaultSpotlight();
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🏷️ Tags ***">
    public function getTags() : array
    {
        if( is_array($this->arrTags) ) {
            return $this->arrTags;
        }

        $this->arrTags = [];
        $tagJunctionEntities = $this->entity->getTags()->getValues();

        usort($tagJunctionEntities, function(ArticleTag $junction1, ArticleTag $junction2) {
            return
                $junction2->getTag()->getRanking() <=> $junction1->getTag()->getRanking() ?:
                    $junction2->getRanking() <=> $junction1->getRanking();
        });

        foreach($tagJunctionEntities as $junctionEntity) {

            $tagEntity              = $junctionEntity->getTag();
            $tagId                  = (string)$tagEntity->getId();
            $this->arrTags[$tagId]  = $this->factory->createTag($tagEntity);
        }

        return $this->arrTags;
    }


    public function getTopTag() : ?TagService
    {
        $arrTags = $this->getTags();
        return reset($arrTags) ?: null;
    }


    public function getTopTagOrDefault() : TagService
    {
        return $this->getTopTag() ?? $this->factory->createDefaultTag();
    }


    public function hasTag(Tag $tag) : bool { return array_key_exists($tag->getId(), $this->getTags()); }

    public function isSponsored() : bool { return array_key_exists(Tag::ID_SPONSOR, $this->getTags()); }

    public function isNewsletter() : bool { return array_key_exists(Tag::ID_NEWSLETTER_TLI, $this->getTags()); }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 📂 Files ***">
    public function getFiles() : array
    {
        if( is_array($this->arrFiles) ) {
            return $this->arrFiles;
        }

        $this->arrFiles = [];

        $fileJunctionEntities = $this->entity->getFiles();
        foreach($fileJunctionEntities as $junctionEntity) {

            $fileEntity = $junctionEntity->getFile();
            $fileId     = $fileEntity->getId();
            $this->arrFiles[$fileId] = $this->factory->createFile($fileEntity);
        }

        return $this->arrFiles;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 💬 Comments ***">
    public function getCommentsTopic() : ?Topic
    {
        if( !empty($this->commentsTopic) ) {
            return $this->commentsTopic;
        }

        $entity = $this->entity->getCommentsTopic();
        if( empty($entity) ) {
            return null;
        }

        return $this->commentsTopic = $this->factory->createTopic($entity);
    }


    public function getCommentsNum(bool $formatted = true) : null|int|string
    {
        $topic = $this->getCommentsTopic();
        if( empty($topic) ) {
            return null;
        }

        $num = $topic->getPostNum();
        $num = empty($num) ? 0 : ($num - 1);

        if($formatted && !empty($num) ) {
            $num = number_format($num, 0, '', '.');
        }

        return $num;
    }


    public function getCommentsUrl() : ?string { return $this->getCommentsTopic()?->getUrl(); }


    public function getAddNewCommentUrl() : string { return $this->getCommentsTopic()?->getReplyUrl(); }


    public function getCommentsAjaxLoadingUrl() : ?string
    {
        if( $this->getCommentsNum() == 0 ) {
            return null;
        }

        return $this->getCommentsTopic()?->getCommentsAjaxLoadingUrl();
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🔄 Prev/Next articles ***">
    public function getPreviousArticle() : ?static { return $this->loadPrevNextArticle("prev"); }

    public function getNextArticle() : ?static { return $this->loadPrevNextArticle("next"); }

    protected function loadPrevNextArticle(string $index) : ?static
    {
        if( !$this->isPublished() ) {
            return null;
        }

        if( !empty($this->arrPrevNextArticles) ) {
            return $this->arrPrevNextArticles[$index] ?? null;
        }

        $collArticles = $this->factory->createArticleCollection()->loadPrevNextArticle($this);

        foreach($collArticles as $article) {

            $which = $article->getPublishedAt()->format('U') < $this->getPublishedAt()->format('U')  ? 'prev' : 'next';
            $this->arrPrevNextArticles[$which] = $article;
        }

        return $this->arrPrevNextArticles[$index] ?? null;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🛋️ Text ***">
    public function getTitle() : ?string
    {
        if( $this->isListable() ) {
            return parent::getTitle();
        }

        return 'Articolo non disponibile';
    }


    public function getTitleWithFreshUpdatedAt() : ?string
    {
        $title = $this->getTitle();

        if( !$this->isListable() ) {
            return $title;
        }

        $updatedAt  = $this->getUpdatedAt();
        $dateLimit  = (new DateTime())->modify('-2 months');

        if( !$this->isPublished() || $updatedAt < $dateLimit) {
            return $title;
        }

        $txtUpdatedAt =
            (new IntlDateFormatter(
                'it_IT', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, IntlDateFormatter::GREGORIAN,
                /**
                 * news     : 28 agosto 2024, ore 22:37
                 * article  : agosto 2024
                 */
                $this->isNews() ? "d MMMM yyyy, 'ore' HH:mm" : 'MMMM yyyy'

            ))->format($updatedAt);

        return "$title (aggiornato: $txtUpdatedAt)";
    }


    public function getTitleForHTMLAttribute() : ?string
    {
        return $this->encodeTextForHTMLAttribute( $this->getTitleWithFreshUpdatedAt() );
    }


    public function getAbstract() : ?string
    {
        if( $this->isListable() ) {
            return $this->entity->getAbstract();
        }

        return null;
    }


    public function getAbstractForHTMLAttribute() : ?string
    {
        $processing = $this->getAbstract();
        $processing = strip_tags($processing);
        $processing = HtmlProcessorBase::decode($processing);
        return htmlspecialchars($processing, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }


    public function getBody() : ?string
    {
        if( $this->isListable() ) {
            return $this->entity->getBody();
        }

        return null;
    }


    public function getBodyForDisplay() : string
    {
        if( is_string($this->articleBodyForDisplay) ) {
            return $this->articleBodyForDisplay;
        }

        return $this->articleBodyForDisplay = $this->htmlProcessor->processArticleBody($this);
    }

    public function textLengthIndex() : int
    {
        $articleBody = $this->getBodyForDisplay();

        $lengthIndex    = mb_strlen($articleBody) / 90;
        $imgCount       = mb_substr_count($articleBody, '<img');
        $aHrefCount     = mb_substr_count($articleBody, '<a');

        $lengthIndex    += $imgCount * 10;
        $lengthIndex    -= $aHrefCount * 0.5;

        return (int)$lengthIndex;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🕸️ URL ***">
    public function getUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->factory->getArticleUrlGenerator()->generateUrl($this, $urlType);
    }

    public function getShortUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
    {
        return $this->factory->getArticleUrlGenerator()->generateShortUrl($this, $urlType);
    }

    public function checkRealUrl(string $tagSlugDashId, string $articleSlugDashId) : ?string
    {
        $candidateUrl   = '/' . $tagSlugDashId . '/' . $articleSlugDashId;
        $realUrl        = $this->factory->getArticleUrlGenerator()->generateUrl($this, UrlGeneratorInterface::ABSOLUTE_PATH);
        return $candidateUrl == $realUrl ? null : $this->getUrl();
    }

    public function getSlug() : ?string { return $this->factory->getArticleUrlGenerator()->buildSlug($this); }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 📛 Badges ***">
    public function getBadges() : array
    {
        if( is_array($this->arrBadges) ) {
            return $this->arrBadges;
        }

        $this->arrBadges = [];

        $tags = $this->getTags();

        /** @var Tag $tag */
        foreach($tags as $tag) {

            $arrBadges = $tag->getBadges();
            $this->arrBadges = array_merge($this->arrBadges, $arrBadges);
        }

        return $this->arrBadges;
    }
    //</editor-fold>


    public function getFeedGuId() : string
    {
        return
            implode(',', array_filter([
                $this->entity->getId(),  $this->entity->getPublishingStatus(),
                $this->getPublishedAt()?->format('Y-m-d-H:i:s')
            ]));
    }


    public function getActiveMenu() : ?string
    {
        if( $this->isKo() ) {
            return null;
        }

        $topTagActiveMenu = $this->getTopTag()?->getActiveMenu();
        if( $topTagActiveMenu != 'guide' ) {
            return $topTagActiveMenu;
        }

        if( $this->isNews() ) {
            return 'news';
        }

        return 'guide';
    }


    public function getMetaRobots() : string { return $this->isPublished() ? 'index,follow' : 'noindex,nofollow'; }
}
