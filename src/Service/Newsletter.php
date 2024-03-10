<?php
namespace App\Service;

use App\Service\Cms\Article;
use App\Service\Cms\Tag;
use App\Service\PhpBB\Topic;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\PhpBB\TopicCollection;
use App\ServiceCollection\UserCollection;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use TurboLabIt\Encryptor\Encryptor;
use Twig\Environment;


class Newsletter extends Mailer
{
    protected string $newsletterOnSiteUrl;
    protected string $privacyUrl;
    protected string $newsletterName        = "Questa settimana su TLI";
    protected string $subject;
    protected array $arrRecipients          = [];
    protected int $totalSubscribersCount;
    protected array $arrTopProviders;


    public function __construct(
        protected ArticleCollection $articleCollection, protected TopicCollection $topicCollection,
        protected UserCollection $userCollection, protected Factory $factory,
        protected UrlGeneratorInterface $urlGenerator, protected Encryptor $encryptor,
        protected Environment $twig,
        //
        MailerInterface $mailer, ProjectDir $projectDir
    )
    {
        // init to homepage (failsafe)
        $this->newsletterOnSiteUrl = $this->privacyUrl =
            $urlGenerator->generate("app_home", [], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->subject = $this->newsletterName;

        parent::__construct($mailer, $projectDir, [
            "from" => [
                "name"      => "TurboLab.it",
                "address"   => "newsletter@turbolab.it"
            ],
            "subject" => [
                "tag" => "[TLI]"
            ]
        ]);
    }


    public function loadContent() : static
    {
        // 👀 https://turbolab.it/617
        $this->privacyUrl = $this->articleCollection->createService()->load(617)->getUrl();

        $this->articleCollection->loadLatestForNewsletter();
        $this->topicCollection->loadLatestForNewsletter();

        //
        $this->tagNewsletterTli = $this->factory->createTag()->load(Tag::ID_NEWSLETTER_TLI);
        $this->userSystem       = $this->factory->createUser()->load(User::SYSTEM_USER_ID);

        $firstArticleTitle = $this->articleCollection->first()?->getTitle();
        if( !empty($firstArticleTitle) ) {
            $this->subject = '"' . $firstArticleTitle . '" e altre novità: ' . $this->newsletterName;
        }

        $this->subject .= " (" . $this->getDateString() . ")";

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
            "Topics"                        => $this->topicCollection,
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


    protected function getDateString() : string
    {
        $text =
            (new \IntlDateFormatter('it_IT', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, NULL, NULL, "dd MMMM y"))
                ->format( new \DateTime() );

        return $text;
    }


    public function sendLowContentNotification()
    {
        $this
            ->build(
            "📭 Contenuti insufficienti per generare la newsletter", "email/newsletter-skipped-notification.html.twig", [],
            [[ "name" => 'Manager', "address" => "info@turbolab.it" ]]
            )
            ->send();
    }


    public function saveOnTheWeb() : string
    {
        $articleBody =
            $this->twig->render('newsletter/article.html.twig', [
                    "Articles"                      => $this->articleCollection,
                    "Topics"                        => $this->topicCollection,
                    "newsletterUrl"                 => $this->articleCollection->createService()->load(Article::ID_NEWSLETTER)->getUrl(),
                    "subscriberCount"               => $this->totalSubscribersCount,
                    "TopEmailProviders"             => $this->arrTopProviders,
                ]
            );

        $title = $this->newsletterName . " (" . $this->getDateString() . ")";

        $topicComment = $this->factory->createTopic()->load(Topic::ID_NEWSLETTER_COMMENTS);

        $article =
            $this->factory->createArticleEditor()
                ->setTitle($title)
                ->addAuthor($this->userSystem)
                ->addTag($this->tagNewsletterTli, $this->userSystem)
                ->setFormat(Article::FORMAT_ARTICLE)
                ->setBody($articleBody)
                ->setPublishedAt(
                    ( new \DateTime() )
                        ->modify('+1 day')
                        ->setTime(0, 0)
                )
                ->setCommentsTopic($topicComment)
                ->setPublishingStatus(Article::PUBLISHING_STATUS_PUBLISHED)
                ->setCommentTopicNeedsUpdate(Article::COMMENT_TOPIC_UPDATE_NEVER)
                ->save();

        return $this->newsletterOnSiteUrl = $article->getUrl();
    }
}
