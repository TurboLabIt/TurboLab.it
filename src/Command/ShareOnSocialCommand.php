<?php
namespace App\Command;

use App\Service\Cms\Article;
use App\ServiceCollection\Cms\ArticleCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\Messengers\FacebookMessenger;
use TurboLabIt\Messengers\TelegramMessenger;

/**
 * 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/social-network-sharing.md
 */
#[AsCommand(
    name: 'ShareOnSocial',
    description: 'Share articles on social media',
    aliases: ['social']
)]
class ShareOnSocialCommand extends AbstractBaseCommand
{
    const int QUIET_HOURS_END   =  8;
    const int EXEC_INTERVAL     = 10;

    protected bool $isDevEnv;
    protected \DateTime $oNow;


    public function __construct(
        protected EntityManagerInterface $em, KernelInterface $kernel,
        protected ArticleCollection $articleCollection,
        protected TelegramMessenger $telegram,
        protected FacebookMessenger $facebook,
        /*protected Environment $twig,
        protected GenericUrlGenerator $urlGenerator,
        protected Telegram $telegram,
        protected FacebookPage $facebook,
        protected Twitter $twitter,*/
    )
    {
        parent::__construct();
        $this->isDevEnv = $kernel->getEnvironment() == 'dev';
        $this->oNow     = new \DateTime();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $isQuietHours = $this->isQuietHours();
        if( $isQuietHours && !$this->isDevEnv ) {

            $this->io->warning("Stopping the execution due to quiet hours");
            return $this->endWithSuccess();

        } elseif($isQuietHours) {

            $this->io->warning("The execution should stop due to quiet hours. Ignoring in DEV...");
        }

        $this->loadArticles();
        if( $this->articleCollection->count() == 0 ) {

            $this->io->warning("There are no articles to share");
            return $this->endWithSuccess();
        }

        foreach($this->articleCollection as $article) {

            $this
                ->shareOnTelegram($article)
                ->shareOnFacebook($article);
                //->shareOnTwitter($article);
        }

        return $this->endWithSuccess();
    }


    protected function isQuietHours(): bool
    {
        $this->fxTitle("Checking QuietHours...");
        $this->io->text("🛏 Quiet hours: from 00:00 to 0" . static::QUIET_HOURS_END . ":00");
        $this->io->text("🕰 Now it's " . $this->oNow->format("H:i"));

        $currentHour = $this->oNow->format('G');
        if( $currentHour < static::QUIET_HOURS_END ) {

            $this->fxWarning("🌙 YES, it is quiet hours");
            return true;
        }

        $this->fxOK("🌞 NO, it is NOT quiet hours");
        return false;
    }


    protected function loadArticles() : static
    {
        $this->fxTitle('Loading articles....');

        // on the very first execution after quiet hours => select all the articles from the start of quiet hours
        $currentHour    = $this->oNow->format('G');
        $currentMin     = $this->oNow->format('i');
        if( $currentHour == static::QUIET_HOURS_END && $currentMin <= static::EXEC_INTERVAL ) {

            $this->fxInfo("🌅 This is the very first execution of the day");

            $defaultTimeZone = new \DateTimeZone(date_default_timezone_get());
            $oLastMidnight  = \DateTime::createFromFormat('U', strtotime('midnight'))->setTimezone($defaultTimeZone);
            $maxMinutes = ( $this->oNow->format('U') - $oLastMidnight->format('U') ) / 60;
            $maxMinutes = ceil($maxMinutes);

        } else {

            $this->fxInfo("This is a regular intra-day execution");
            $maxMinutes = static::EXEC_INTERVAL;
        }

        $this->fxTitle("Loading articles published in the last " . $maxMinutes . " minutes...");
        $this->articleCollection->loadLatestForSocialSharing($maxMinutes);
        $this->fxOK("##" . $this->articleCollection->count() . "## article(s) loaded");

        return $this;
    }


    public function shareOnTelegram(Article $article) : static
    {
        $articleTitle   = $article->getTitle();
        $articleUrl     = $article->getShortUrl();

        $this->fxTitle("Sharing on Telegram: $articleTitle | $articleUrl");
        $messageHtml = '<b><a href="' . $articleUrl . '">📰 ' . $articleTitle . '</a></b>';
        $this->telegram
            ->setMessageButtons([
                [
                    "text"  => "👉🏻 LEGGI TUTTO 👈🏻",
                    "url"   => $articleUrl
                ]
            ])
            ->sendMessageToChannel($messageHtml);

        return $this;
    }


    public function shareOnFacebook(Article $article) : static
    {
        $articleTitle   = $article->getTitle();
        $articleUrl     = $article->getShortUrl();

        $this->fxTitle("Sharing on Facebook: $articleTitle | $articleUrl");
        $this->facebook->sendUrlToPage($articleUrl);

        return $this;
    }


    public function shareOnTwitter(Article $article) : static
    {
        $this->io->section('Sharing an article on Twitter....');
        $this->io->text( $article->getTitle() );

        $url = $this->urlGenerator->changeDevDomainToProduction( $article->getUrl() );

        $message =
            $this->twig->render('messenger/twitter.html.twig', [
                "emoji" => "📰",
                "title" => $article->getTitle(),
                "url"   => $url
            ]);

        try {
            $this->twitter->sendMessage($message);
            $this->io->text("<fg=green>OK</>");

        } catch(TwitterOAuthException $ex) {

            $message = $this->twitter->getLastResponseAsJson();
            $this->io->error( json_encode($message) );
            $this->io->error( $ex->getMessage() );
        }

        return $this;
    }
}
