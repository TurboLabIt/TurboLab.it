<?php
namespace App\Command;

use App\Service\Cms\Article;
use App\ServiceCollection\Cms\ArticleCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\Messengers\FacebookMessenger;
use TurboLabIt\Messengers\TelegramMessenger;
use TurboLabIt\Messengers\TwitterMessenger;

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
    const string CLI_OPT_CRON   = 'cron';

    protected \DateTime $oNow;


    public function __construct(
        protected EntityManagerInterface $em, protected ParameterBagInterface $parameters,
        protected ArticleCollection $articleCollection,
        protected TelegramMessenger $telegram, protected FacebookMessenger $facebook,
        protected TwitterMessenger $twitter
    )
    {
        parent::__construct();
        $this->oNow = new \DateTime();
    }


    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            static::CLI_OPT_CRON, null, InputOption::VALUE_NONE,
            'Set if the command was started by a cron job'
        );
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $isQuietHours = $this->isQuietHours();
        if( $isQuietHours && $this->isProd() ) {

            $this->fxWarning("Stopping the execution due to quiet hours");
            return $this->endWithSuccess();

        } elseif($isQuietHours) {

            $this->fxWarning("The execution should stop due to quiet hours. Ignoring in non-prod...");
        }
        

        $this->loadArticles();

        if( $this->articleCollection->count() == 0 ) {

            $this->fxWarning("There are no articles to share");

            if( $this->isProd() || $this->getCliOption(static::CLI_OPT_CRON) ) {
                return $this->endWithSuccess();
            }

            $this->fxWarning(
                "The execution should stop due to no-articles. " . 
                "Ignoring in in non-prod. Loading some random articles..."
            );

            $this->articleCollection
                ->loadRandom(2)
                ->addIdComplete(Article::ID_QUALITY_TEST);
        }


        foreach($this->articleCollection as $article) {

            $articleTitle = $article->getTitle();
            $this->fxTitle($articleTitle);

            $articleUrl = $article->getShortUrl();
            $this->io->writeLn("🔗 $articleUrl");

            $this
                ->shareOnTelegram($articleTitle, $articleUrl)
                ->shareOnFacebook($articleTitle, $articleUrl)
                ->shareOnTwitter($articleTitle, $articleUrl);
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

        $this->fxInfo("Loading articles published in the last $maxMinutes minutes...");
        $this->articleCollection->loadLatestForSocialSharing($maxMinutes);
        $this->fxOK("##" . $this->articleCollection->count() . "## article(s) loaded");

        return $this;
    }


    protected function shareOnTelegram(string $articleTitle, string $articleUrl) : static
    {
        $this->io->write("✴ Telegram: ");

        try {
            $messageHtml =
                "<b>📰 <a href=\"$articleUrl\">$articleTitle</a></b>";

            $result =
                $this->telegram
                    ->setMessageButtons([[
                        "text"  => "👉🏻 LEGGI TUTTO 👈🏻",
                        "url"   => $articleUrl
                    ]])
                    ->sendMessageToChannel($messageHtml);

            $url = $this->telegram->buildNewMessageUrl($result);
            $this->io->writeln("<info>$url</info>");

        } catch(\Exception $ex) {

            $this->io->writeln("<error>ERROR: " . $ex->getMessage()  . "</error>");
            $this->sendAlert("Telegram", $ex, $articleTitle, $articleUrl);
        }

        return $this;
    }


    protected function shareOnFacebook(string $articleTitle, string $articleUrl) : static
    {
        $this->io->write("✴ Facebook: ");

        try {
            $postId = $this->facebook->sendUrlToPage($articleUrl);
            $url = $this->facebook->buildMessageUrl($postId);
            $this->io->writeln("<info>$url</info>");

        } catch(\Exception $ex) {

            $this->io->writeln("<error>ERROR: " . $ex->getMessage()  . "</error>");
            $this->sendAlert("Facebook", $ex, $articleTitle, $articleUrl);
        }

        return $this;
    }


    protected function shareOnTwitter(string $articleTitle, string $articleUrl) : static
    {
        $this->io->write("✴ Twitter: ");

        try {
            $message = "$articleTitle $articleUrl";
            $postId  = $this->twitter->sendMessage($message);

            $url = $this->twitter->buildMessageUrl($postId);
            $this->io->writeln("<info>$url</info>");

        } catch(\Exception $ex) {

            $this->io->writeln("<error>ERROR: " . $ex->getMessage()  . "</error>");
            $this->sendAlert("Twitter", $ex, $articleTitle, $articleUrl);
        }

        return $this;
    }


    protected function sendAlert(string $serviceName, \Exception $ex, string $articleTitle, string $articleUrl) : static
    {
        $message =
            "<b>ShareOnSocial error on $serviceName</b>" . PHP_EOL .
            "<code>" . $ex->getMessage() . "</code>" . PHP_EOL .
            "URL: $articleUrl";

        $this->telegram
            ->setMessageButtons([])
            ->sendErrorMessage($message);

        return $this;
    }
}
