<?php
namespace App\Service\Cms;

use App\Service\Mailer;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\PhpBB\TopicCollection;
use Symfony\Component\Mailer\MailerInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;


class Newsletter extends Mailer
{
    protected string $subject;

    public function __construct(
        protected ArticleCollection $articleCollection, protected TopicCollection $topicCollection,
        //
        MailerInterface $mailer, ProjectDir $projectDir
    )
    {
        parent::__construct($mailer, $projectDir);
    }


    public function prepare() : static
    {
        $this->articleCollection->loadLatestForNewsletter();
        $this->topicCollection->loadLatestForNewsletter();

        $firstArticleTitle = $this->articleCollection->first()?->getTitle();
        $this->subject =
            empty($firstArticleTitle)
                ? "Questa settimana su TurboLab.it"
                : $firstArticleTitle . " e le altre novità della settimana su TurboLab.it";

        $this->subject .= " (" . $this->getDateString() . ")";

        return $this;
    }


    public function buildForOne(string $recipientName, string $recipientAddress) : static
    {
        $arrTemplateParams = [
            "Articles"  => $this->articleCollection,
            "Topics"    => $this->topicCollection,
            "Email"     => [
                "To" => [
                    "name"      => $recipientName,
                    "address"   => $recipientAddress
                ]
            ],
            'unsubscribeUrl'        => "https://....",
            'newsletterOnSiteUrl'   => "https://...."
        ];

        return
            $this->build(
                $this->subject, "email/newsletter.html.twig", $arrTemplateParams,
                [[ "name" => $recipientName, "address" => $recipientAddress ]]
            );
    }


    protected function getDateString() : string
    {
        $text =
            (new \IntlDateFormatter('it_IT', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, NULL, NULL, "dd MMMM y"))
                ->format( new \DateTime() );

        return $text;
    }
}
