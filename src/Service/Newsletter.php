<?php
namespace App\Service;

use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\PhpBB\TopicCollection;
use App\ServiceCollection\UserCollection;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use TurboLabIt\Encryptor\Encryptor;


class Newsletter extends Mailer
{
    protected string $newsletterOnSiteUrl;
    protected string $subject       = "Questa settimana su TurboLab.it";
    protected array $arrRecipients  = [];


    public function __construct(
        protected ArticleCollection $articleCollection, protected TopicCollection $topicCollection,
        protected UserCollection $userCollection,
        protected UrlGeneratorInterface $urlGenerator, protected Encryptor $encryptor,
        //
        MailerInterface $mailer, ProjectDir $projectDir
    )
    {
        $this->newsletterOnSiteUrl = $urlGenerator->generate("app_home", [], UrlGeneratorInterface::ABSOLUTE_URL);
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
        $this->articleCollection->loadLatestForNewsletter();
        $this->topicCollection->loadLatestForNewsletter();

        $firstArticleTitle = $this->articleCollection->first()?->getTitle();
        if( !empty($firstArticleTitle) ) {
            $this->subject = '"' . $firstArticleTitle . '" e altre novità su TurboLab.it';
        }

        $this->subject .= " (" . $this->getDateString() . ")";

        return $this;
    }


    public function loadTestRecipients() : static
    {
        $this->arrRecipients =
            $this->userCollection
                ->loadNewsletterTestRecipients()
                ->getAll();

        return $this;
    }


    public function countArticles()     : int { return $this->articleCollection->count(); }
    public function countTopics()       : int { return $this->topicCollection->count(); }
    public function countRecipients()   : int { return $this->userCollection->count(); }
    public function getRecipients()     : array { return $this->arrRecipients; }
    public function getSubject()        : string { return $this->subject; }


    public function buildForOne(string $recipientName, string $recipientAddress, string $unsubscribeUrl) : static
    {
        $arrTemplateParams = [
            "Articles"  => $this->articleCollection,
            "Topics"    => $this->topicCollection,
            'unsubscribeUrl'        => $unsubscribeUrl,
            'newsletterOnSiteUrl'   => $this->newsletterOnSiteUrl
        ];

        return
            $this->build(
                $this->subject, "email/newsletter.html.twig", $arrTemplateParams,
                [[ "name" => $recipientName, "address" => $recipientAddress ]]
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


    public function lowContentNotification()
    {
        $this
            ->build(
            "📭 Contenuti insufficienti per generare la newsletter", "email/newsletter-skipped-notification.html.twig", [],
            [[ "name" => 'Manager', "address" => "info@turbolab.it" ]]
            )
            ->send();
    }
}
