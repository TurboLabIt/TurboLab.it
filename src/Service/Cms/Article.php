<?php
namespace App\Service\Cms;

use App\Entity\Cms\Article as ArticleEntity;
use App\Repository\Cms\ArticleRepository;
use App\Service\Cms\Image as ImageService;
use App\Service\Cms\Tag as TagService;
use App\Service\Factory;
use App\Service\PhpBB\Topic;
use App\Service\User;
use App\Trait\ArticleFormatsTrait;
use App\Trait\CommentTopicStatusesTrait;
use App\Trait\PublishingStatusesTrait;
use App\Trait\ViewableServiceTrait;
use DateTime;
use DateTimeInterface;
use IntlDateFormatter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Article extends BaseCmsService
{
    const string ENTITY_CLASS           = ArticleEntity::class;
    const string NOT_FOUND_EXCEPTION    = 'App\Exception\ArticleNotFoundException';

    const int ID_FORUM_IMAGES       = 24;       // 👀 https://turbolab.it/24
    const int ID_HOW_TO_JOIN        = 28;       // 👀 https://turbolab.it/28
    const int ID_ABOUT_US           = 40;       // 👀 https://turbolab.it/40
    const int ID_HOW_TO_WRITE       = 46;       // 👀 https://turbolab.it/46
    const int ID_ISSUE_REPORT       = 49;       // 👀 https://turbolab.it/49
    const int ID_PUBLISH_NEWS       = 222;      // 👀 https://turbolab.it/222
    const int ID_NEWSLETTER         = 402;      // 👀 https://turbolab.it/402
    const int ID_PRIVACY_POLICY     = 617;      // 👀 https://turbolab.it/617
    const int ID_COOKIE_POLICY      = 681;      // 👀 https://turbolab.it/681
    const int ID_DONATIONS          = 1126;     // 👀 https://turbolab.it/1126
    const int ID_PUBLISH_ARTICLE    = 3990;     // 👀 https://turbolab.it/3990
    const int ID_SIGN_ARTICLE       = 2329;     // 👀 https://turbolab.it/2329
    const int ID_BITTORRENT_GUIDE   = 669;      // 👀 https://turbolab.it/669
    const int ID_QUALITY_TEST       = 1939;     // 👀 https://turbolab.it/1939

    use ViewableServiceTrait { countOneView as protected traitCountOneView; }
    use PublishingStatusesTrait, ArticleFormatsTrait, CommentTopicStatusesTrait;

    protected ArticleEntity $entity;


    //<editor-fold defaultstate="collapsed" desc="*** 🍹 Class properties ***">
    protected ?array $arrTags               = null;
    protected ?array $arrAuthors            = null;
    protected ?array $arrFiles              = null;
    protected ?ImageService $spotlight;
    protected HtmlProcessor $htmlProcessor;
    protected ?TagService $topTag           = null;
    protected ?Topic $commentsTopic;
    protected ?string $articleBodyForDisplay = null;
    protected array $arrPrevNextArticles    = [];
    //</editor-fold>

    public function __construct(protected Factory $factory)
    {
        $this->clear();
        $this->htmlProcessor = new HtmlProcessor($factory);
    }

    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ORM entity ***">
    public function getRepository() : ArticleRepository
    {
        /** @var ArticleRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(ArticleEntity::class);
        return $repository;
    }

    public function setEntity(?ArticleEntity $entity = null) : static
    {
        $this->localViewCount = $entity->getViews();
        $this->entity = $entity;
        return $this;
    }

    public function getEntity() : ?ArticleEntity { return $this->entity ?? null; }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🗞️ Publishing ***">
    public function isPublished() : bool
    {
        return
            $this->entity->getPublishingStatus() == ArticleEntity::PUBLISHING_STATUS_PUBLISHED &&
            !empty( $this->getPublishedAt() );
    }


    public function getPublishedAt() : ?DateTimeInterface { return $this->entity->getPublishedAt(); }

    public function isNews() : bool { return $this->entity->getFormat() == ArticleEntity::FORMAT_NEWS; }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🏷️ Tags ***">
    public function getTags() : array
    {
        if( is_array($this->arrTags) ) {
            return $this->arrTags;
        }

        $this->arrTags = [];

        $tagJunctionEntities = $this->entity->getTags();
        foreach($tagJunctionEntities as $junctionEntity) {

            $tagEntity              = $junctionEntity->getTag();
            $tagId                  = $tagEntity->getId();
            $this->arrTags[$tagId]  = $this->factory->createTag($tagEntity);
        }

        return $this->arrTags;
    }


    public function getTopTag() : ?TagService
    {
        if( !empty($this->topTag) ) {
            return $this->topTag;
        }

        $arrTags = $this->getTags();
        if( empty($arrTags) ) {
            return null;
        }

        $this->topTag = reset($arrTags);

        /** @var TagService $topTagCandidate */
        foreach($arrTags as $topTagCandidate) {

            if( $topTagCandidate->getRanking() > $this->topTag->getRanking() ) {
                $this->topTag = $topTagCandidate;
            }

            if( $topTagCandidate->getRanking() == $this->topTag->getRanking() ) {

                $this->topTag =
                    $topTagCandidate->getUpdatedAt() < $this->topTag->getUpdatedAt() ? $topTagCandidate : $this->topTag;
            }
        }

        return $this->topTag;
    }


    public function getTopTagOrDefault() : TagService
    {
        $topTag = $this->getTopTag();
        if( !empty($topTag) ) {
            return $topTag;
        }

        return $this->topTag = $this->factory->createDefaultTag();
    }


    public function isSponsored() :bool { return array_key_exists(Tag::ID_SPONSOR, $this->getTags()); }

    public function isNewsletter() :bool { return array_key_exists(Tag::ID_NEWSLETTER_TLI, $this->getTags()); }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 👔 Authors ***">
    public function getAuthors() : array
    {
        if( is_array($this->arrAuthors) ) {
            return $this->arrAuthors;
        }

        $this->arrAuthors = [];

        $authorJunctionEntities = $this->entity->getAuthors();
        foreach($authorJunctionEntities as $junctionEntity) {

            $userEntity                     = $junctionEntity->getUser();
            $authorId                       = $userEntity->getId();
            $this->arrAuthors[$authorId]    = $this->factory->createUser($userEntity);
        }

        return $this->arrAuthors;
    }


    public function getAuthorsNotSystem() : array
        { return array_filter($this->getAuthors(), fn(User $user) => !$user->isSystem()); }
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

    //<editor-fold defaultstate="collapsed" desc="*** ☀️ Spotlight ***">
    public function getSpotlightOrDefaultUrl(string $size) : string
        { return $this->getSpotlightOrDefault()->getUrl($this, $size); }

    public function getSpotlightUrl(string $size) : ?string { return $this->getSpotlight()?->getUrl($this, $size); }

    public function getSpotlight() : ?ImageService
    {
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
        if( ! $this->isPublished() ) {
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
    public function getTitleWithFreshUpdatedAt() : ?string
    {
        $title      = $this->getTitleFormatted();
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


    public function getAbstract() : ?string { return $this->entity->getAbstract(); }

    public function getBody() : ?string { return $this->entity->getBody(); }

    public function getBodyForDisplay() : string
    {
        if( is_string($this->articleBodyForDisplay) ) {
            return $this->articleBodyForDisplay;
        }

        return $this->articleBodyForDisplay = $this->htmlProcessor->processArticleBodyForDisplay($this);
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
        { return $this->factory->getArticleUrlGenerator()->generateUrl($this, $urlType); }

    public function getShortUrl(int $urlType = UrlGeneratorInterface::ABSOLUTE_URL) : string
        { return $this->factory->getArticleUrlGenerator()->generateShortUrl($this, $urlType); }

    public function checkRealUrl(string $tagSlugDashId, string $articleSlugDashId) : ?string
    {
        $candidateUrl   = '/' . $tagSlugDashId . '/' . $articleSlugDashId;
        $realUrl        = $this->factory->getArticleUrlGenerator()->generateUrl($this, UrlGeneratorInterface::ABSOLUTE_PATH);
        return $candidateUrl == $realUrl ? null : $this->getUrl();
    }

    public function getSlug() : ?string { return $this->factory->getArticleUrlGenerator()->buildSlug($this); }
    //</editor-fold>


    public function countOneView() : static
    {
        if( !$this->entity->publishingStatusCountOneView() ) {
            return $this;
        }

        return $this->traitCountOneView();
    }


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
        $topTagActiveMenu = $this->getTopTag()?->getActiveMenu();
        if( $topTagActiveMenu != 'guide' ) {
            return $topTagActiveMenu;
        }

        if( $this->isNews() ) {
            return 'news';
        }

        return 'guide';
    }
}
