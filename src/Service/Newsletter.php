<?php
namespace App\Service;

use App\Entity\NewsletterExpiringWarn;
use App\Entity\NewsletterOpener;
use App\Repository\NewsletterExpiringWarnRepository;
use App\Repository\NewsletterOpenerRepository;
use App\Service\Cms\Article;
use App\Service\Cms\Tag;
use App\Service\PhpBB\Topic;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\PhpBB\TopicCollection;
use App\ServiceCollection\UserCollection;
use DateTime;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TurboLabIt\BaseCommand\Service\DateMagician;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use TurboLabIt\Encryptor\Encryptor;
use TurboLabIt\MessengersBundle\TelegramMessenger;
use Twig\Environment;


class Newsletter extends Mailer
{
    const array FORBIDDEN_WORDS = ['example.com', 'casino', 'casinò', 'scommesse', 'betting'];

    protected string $newsletterOnSiteUrl;
    protected string $privacyUrl;
    protected string $newsletterName;
    protected string $subject;
    protected array $arrVideos;
    protected Tag $tagNewsletterTli;
    protected User $userSystem;
    protected array $arrRecipients              = [];
    protected int $totalSubscribersCount;
    protected array $arrTopProviders;
    protected bool $showingTestArticles         = false;
    protected bool $showingTestTopics           = false;
    protected bool $addTimestampToWebArticle    = false;


    public function __construct(
        array $arrConfig,
        protected ArticleCollection $articleCollection, protected YouTubeChannelApi $YouTube,
        protected TopicCollection $topicCollection,
        protected UserCollection $userCollection, protected Factory $factory,
        protected UrlGeneratorInterface $urlGenerator, protected Encryptor $encryptor,
        protected Environment $twig, protected TelegramMessenger $alertMessenger,
        MailerInterface $mailer, ProjectDir $projectDir, protected ParameterBagInterface $parameters,
    )
    {
        // init to homepage (failsafe)
        $this->newsletterOnSiteUrl = $this->privacyUrl =
            $urlGenerator->generate("app_home", [], UrlGeneratorInterface::ABSOLUTE_URL);

        $newsletterDate =
            (new DateMagician())->intlFormat(
                (new DateTime())->modify('+1 day'), DateMagician::INTL_FORMAT_IT_DATE_COMPLETE
            );

        $this->newsletterName = "Questa settimana su TLI ($newsletterDate)";

        parent::__construct($mailer, $projectDir, $parameters, [
            "from" => [
                "name"      => "TurboLab.it",
                "address"   => "newsletter@turbolab.it"
            ],
            "subject" => [
                "tag" => "[TLI]",
                "use-top-article-title" => $arrConfig["useTopArticleTitleAsEmailSubject"]
            ]
        ]);
    }


    public function getRepositoryExpiringWarn() : NewsletterExpiringWarnRepository
    {
        /** @var NewsletterExpiringWarnRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(NewsletterExpiringWarn::class);
        return $repository;
    }

    public function getRepositoryOpener() : NewsletterOpenerRepository
    {
        /** @var NewsletterOpenerRepository $repository */
        $repository = $this->factory->getEntityManager()->getRepository(NewsletterOpener::class);
        return $repository;
    }


    public function loadContent() : static
    {
        $this->articleCollection->loadLatestForNewsletter();

        if( empty($this->arrVideos) ) {
            $this->arrVideos = $this->YouTube->getLatestVideos(4);
        }

        $this->topicCollection->loadLatestForNewsletter();

        return $this->loadAncillaryContent();
    }


    protected function loadAncillaryContent() : static
    {
        if( empty($this->privacyUrl) ) {

            $this->privacyUrl =
                $this->articleCollection->createService()->load(Article::ID_PRIVACY_POLICY)
                    ->getUrl();
        }

        if( empty($this->tagNewsletterTli) ) {
            $this->tagNewsletterTli = $this->factory->createTag()->load(Tag::ID_NEWSLETTER_TLI);
        }

        if( empty($this->userSystem) ) {
            $this->userSystem = $this->factory->createUser()->load(User::SYSTEM_USER_ID);
        }

        return $this->generateSubject();
    }


    public function generateSubject() : static
    {
        $this->subject = $this->newsletterName;

        if( !$this->arrConfig["subject"]["use-top-article-title"] ) {
            return $this;
        }

        $firstArticleTitle = $this->articleCollection->first()?->getTitle();

        if( empty($firstArticleTitle) ) {
            return $this;
        }

        $this->subject = '"' . $firstArticleTitle . '" e altre novità | ' . $this->newsletterName;
        return $this;
    }


    public function loadTestArticles() : static
    {
        $this->showingTestArticles = true;
        $this->articleCollection->loadRandom( rand(2,7) );
        return $this->loadAncillaryContent();
    }


    public function loadTestTopics() : static
    {
        $this->showingTestTopics = true;
        $this->topicCollection->loadRandom( rand(5,20) );
        return $this;
    }


    public function loadTestRecipients() : static
    {
        // count total subscribers and get top providers
        $this->loadRecipients();

        $this->arrRecipients =
            $this->userCollection
                ->loadNewsletterTestRecipients()
                ->getAll();

        return $this;
    }


    public function loadRecipients() : static
    {
        $this->arrRecipients =
            $this->userCollection
                ->loadNewsletterSubscribers()
                ->getAll();

        $this->totalSubscribersCount    = $this->userCollection->count();
        $this->arrTopProviders          = $this->userCollection->getTopEmailProviders();

        return $this;
    }


    public function countArticles()     : int { return $this->articleCollection->count(); }
    public function countTopics()       : int { return $this->topicCollection->count(); }
    public function countRecipients()   : int { return $this->userCollection->count(); }
    public function getRecipients()     : array { return $this->arrRecipients; }
    public function getSubject()        : string { return $this->subject; }


    public function buildForOne(User $user) : static
    {
        $homeUrl        = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $unsubscribeUrl = $user->getNewsletterUnsubscribeUrl();

        $arrTemplateParams = [
            "Articles"                      => $this->articleCollection,
            "showingTestArticles"           => $this->showingTestArticles,
            "Videos"                        => $this->arrVideos,
            "Topics"                        => $this->topicCollection,
            "showingTestTopics"             => $this->showingTestTopics,
            "openerUrl"                     => $user->getNewsletterOpenerUrl(),
            "homeWithOpenerUrl"             => $user->getNewsletterOpenerUrl($homeUrl),
            "forumWithOpenerUrl"            => $user->getNewsletterOpenerUrl( $homeUrl . "forum/" ),
            "privacyWithOpenerUrl"          => $user->getNewsletterOpenerUrl($this->privacyUrl),
            "unsubscribeUrl"                => $unsubscribeUrl,
            "newsletterOnSiteWithOpenerUrl" => $user->getNewsletterOpenerUrl($this->newsletterOnSiteUrl),
            "feedbackTopicWithOpenerUrl"    => $user->getNewsletterOpenerUrl(
                $homeUrl . "forum/posting.php?mode=reply&t=" . Topic::ID_NEWSLETTER_COMMENTS
            ),
            "subscriberCount"               => $this->totalSubscribersCount,
            "TopEmailProviders"             => $this->arrTopProviders,
        ];

        return
            $this
                ->addUnsubscribeHeader($unsubscribeUrl, null)
                ->build(
                $this->subject, "newsletter/email.html.twig", $arrTemplateParams,
                [[ "name" => $user->getUsername(), "address" => $user->getEmail() ]]
            );
    }


    public function getPreviewUrl() : string
    {
        return $this->urlGenerator->generate("app_newsletter_preview", [], UrlGeneratorInterface::ABSOLUTE_URL);
    }


    public function sendLowContentNotification() : string
    {
        $message =
            "<b>Newsletter error</b>" . PHP_EOL .
            "Contenuti insufficienti per generare la newsletter";

        $this->alertMessenger->sendErrorMessage($message);

        return $message;
    }


    public function setAddTimestampToWebArticle(bool $addTimestampToWebArticle = true) : static
    {
        $this->addTimestampToWebArticle = $addTimestampToWebArticle;
        return $this;
    }


    public function saveOnTheWeb(bool $persist) : ?string
    {
        $articleBody =
            $this->twig->render('newsletter/article.html.twig', [
                    "Articles"          => $this->articleCollection,
                    "Topics"            => $this->topicCollection,
                    "Videos"            => $this->arrVideos,
                    "newsletterUrl"     => $this->articleCollection->createService()->load(Article::ID_NEWSLETTER)->getUrl(),
                    "subscriberCount"   => $this->totalSubscribersCount,
                    "TopEmailProviders" => $this->arrTopProviders,
                ]
            );

        $topicComment = $this->factory->createTopic()->load(Topic::ID_NEWSLETTER_COMMENTS);

        $articleTitle = $this->newsletterName;
        if( $this->addTimestampToWebArticle ) {
            $articleTitle .= ' - ' . (new DateTime())->format('H:i:s');
        }

        $article =
            $this->factory->createArticleEditor()
                ->setTitle($articleTitle)
                ->addAuthor($this->userSystem)
                ->addTag($this->tagNewsletterTli, $this->userSystem)
                ->setFormat(Article::FORMAT_ARTICLE)
                ->setBody($articleBody)
                ->setPublishedAt(
                    ( new DateTime() )
                        ->modify('+1 day')
                        ->setTime(0, 0)
                )
                ->setCommentsTopic($topicComment)
                ->setPublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED)
                ->setArchived(true)
                //->setCommentTopicNeedsUpdate(Article::COMMENT_TOPIC_UPDATE_NEVER)
                ->save($persist);

        if($persist) {
            $this->newsletterOnSiteUrl = $article->getUrl();
        }

        return $this->newsletterOnSiteUrl;
    }


    public function confirmOpener(int $userId) : bool
    {
        try {
            $userEntity = $this->factory->createUser()->load($userId)->getEntity();

            $opener = $this->getRepositoryOpener()->getByUserOrNew($userEntity);
            $opener->setUpdatedAt( new DateTime() );

            $this->getRepositoryExpiringWarn()->deleteByUserId($userId);

            $this->factory->getEntityManager()->persist($opener);
            $this->factory->getEntityManager()->flush();

        } catch (Exception) { return false; }

        return true;
    }


    public function unsubscribeUser(User $user) : static
    {
        $user->unsubscribeFromNewsletter();

        $userId = $user->getId();
        $this->getRepositoryOpener()->deleteByUserId($userId);
        $this->getRepositoryExpiringWarn()->deleteByUserId($userId);

        $this->factory->getEntityManager()->flush();

        return $this;
    }
}
