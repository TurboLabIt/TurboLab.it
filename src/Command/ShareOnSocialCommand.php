<?php
namespace App\Command;

use App\Service\Cms\Image;
use App\Service\Cms\Article;
use App\Service\YouTubeChannelApi;
use App\ServiceCollection\Cms\ArticleCollection;
use DateTime;
use DateTimeZone;
use Exception;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\MessengersBundle\BaseMessenger;
use TurboLabIt\MessengersBundle\FacebookPageMessenger;
use TurboLabIt\MessengersBundle\LinkedInPageMessenger;
use TurboLabIt\MessengersBundle\TelegramMessenger;
use TurboLabIt\MessengersBundle\TwitterMessenger;

/**
 * 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/social-network-sharing.md
 */
#[AsCommand(
    name: 'ShareOnSocial',
    description: 'Share articles and YouTube videos on social media',
    aliases: ['social']
)]
class ShareOnSocialCommand extends AbstractBaseCommand
{
    const int QUIET_HOURS_END       =  8;
    // Keep the interval synced with the cron https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/prod/cron
    // Keep the interval one minute higher than CACHE_MINUTES https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/YouTubeChannelApi.php
    const int EXEC_INTERVAL         = 15;
    const string CLI_OPT_CRON       = 'cron';
    const string CLI_OPT_SERVICES   = 'service';

    protected bool $allowDryRunOpt  = true;
    protected bool $allowIdOpt      = true;

    protected DateTime $oNow;
    protected array $arrYouTubeVideos = [];


    public function __construct(
        protected EntityManagerInterface $em, protected ParameterBagInterface $parameters,
        protected ArticleCollection $articleCollection, protected YouTubeChannelApi $YouTubeChannel,
        protected TelegramMessenger $telegram, protected FacebookPageMessenger $facebook,
        protected TwitterMessenger $twitter, protected LinkedInPageMessenger $linkedIn
    )
    {
        parent::__construct();
        $this->oNow = new DateTime();
    }


    protected function configure() : void
    {
        parent::configure();
        $this
            ->addOption(
                static::CLI_OPT_CRON, null, InputOption::VALUE_NONE,
                'Set if the command was started by a cron job'
            )
            ->addOption(
                static::CLI_OPT_SERVICES, null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Limit to these services'
            );
    }


    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        parent::execute($input, $output);

        $isQuietHours   = $this->isQuietHours();
        $cliArticleId   = $this->getCliId();

        if( $isQuietHours && !empty($cliArticleId) ) {

            $this->fxWarning("The execution should stop due to quiet hours. Ignoring due to ID ##$cliArticleId## provided via CLI");

        } elseif( $isQuietHours && $this->isProd() ) {

            $this->fxWarning("Stopping the execution due to quiet hours");
            return $this->endWithSuccess();

        } elseif($isQuietHours) {

            $this->fxWarning("The execution should stop due to quiet hours. Ignoring in non-prod...");
        }


        if( empty($cliArticleId) ) {

            $this
                ->loadArticles()
                ->loadVideos();

        } else {

            $this->fxWarning("Loading ##$cliArticleId## provided via CLI");
            $this->articleCollection->load($cliArticleId);

            if( $this->articleCollection->count() == 0 ) {
                $this->endWithError("Article ##$cliArticleId## not found!");
            }
        }


        if( $this->articleCollection->count() == 0 && count($this->arrYouTubeVideos) == 0) {

            $this->fxWarning("There are neither articles nor videos to share");

            if( $this->isProd() || $this->getCliOption(static::CLI_OPT_CRON) ) {
                return $this->endWithSuccess();
            }

            $this->fxWarning(
                "The execution should stop due to no-articles and no-videos. " .
                "Ignoring in non-prod and non-cron. Loading some random articles and videos..."
            );

            $this->articleCollection
                ->loadRandom(2);
                //->addIdComplete(Article::ID_QUALITY_TEST);

            $arrVideos = $this->YouTubeChannel->getLatestVideos(50);
            $randomKey = array_rand($arrVideos);
            $this->arrYouTubeVideos = [ $arrVideos[$randomKey] ];
        }

        /** @var Article $article */
        foreach($this->articleCollection as $article) {
            $this->shareOnAll([
                "title"     => $article->getTitle(),
                "url"       => $article->getUrl(),
                "prodUrl"   => $this->buildProductionUrl( $article->getUrl() ),
                "spotlight" => $this->buildProductionUrl( $article->getSpotlightOrDefaultUrl(Image::SIZE_MAX) ),
                "emoji"     => "📰",
                "cta"       => "LEGGI TUTTO"
            ]);
        }

        /** @var stdClass $video */
        foreach($this->arrYouTubeVideos as $video) {
            $this->shareOnAll([
                "title"     => $video->title,
                "url"       => $video->url,
                "prodUrl"   => $video->url,
                "spotlight" => $video->thumbnails->high->url,
                "emoji"     => "📺",
                "cta"       => "GUARDA IL VIDEO"
            ]);
        }

        return $this->endWithSuccess();
    }


    protected function isQuietHours() : bool
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

        $maxMinutes = $this->getMaxEligibleMinutesAgo();

        $this->fxInfo("Loading articles published in the last $maxMinutes minutes...");
        $this->articleCollection->loadLatestForSocialSharing($maxMinutes);

        return $this->fxOK("##" . $this->articleCollection->count() . "## article(s) loaded");
    }


    protected function getMaxEligibleMinutesAgo() : int
    {
        // on the very first execution after quiet hours => select all since the start of quiet hours
        $currentHour    = $this->oNow->format('G');
        $currentMin     = $this->oNow->format('i');

        if( $currentHour == static::QUIET_HOURS_END && $currentMin < static::EXEC_INTERVAL ) {

            $this->fxInfo("🌅 This is the very first execution of the day");

            $defaultTimeZone = new DateTimeZone(date_default_timezone_get());
            $oLastMidnight  = DateTime::createFromFormat('U', (string)strtotime('midnight'))->setTimezone($defaultTimeZone);
            $maxMinutes = ( $this->oNow->format('U') - $oLastMidnight->format('U') ) / 60;
            $maxMinutes = ceil($maxMinutes);

        } else {

            $this->fxInfo("This is a regular intra-day execution");
            $maxMinutes = static::EXEC_INTERVAL;
        }

        $maxEligibleTime = (new DateTime())->modify("-$maxMinutes minutes")->format('Y-m-d H:i:s');
        $this->fxInfo("⏱ Max eligible: $maxMinutes minutes ago ($maxEligibleTime)");

        return $maxMinutes;
    }


    protected function loadVideos() : static
    {
        $this->fxTitle('Loading videos from YouTube....');
        $arrVideos = $this->YouTubeChannel->getLatestVideos();

        $maxMinutes = $this->getMaxEligibleMinutesAgo();

        $this->fxInfo("Filtering out older videos...");
        foreach($arrVideos as $key => $video) {

            // 2024-10-03 22:03:44.000000
            if( $video->publishedAt->modify("+$maxMinutes minutes") > $this->oNow ) {
                continue;
            }

            unset($arrVideos[$key]);
        }

        $this->arrYouTubeVideos = $arrVideos;

        return $this->fxOK("##" . count($arrVideos) . "## new videos(s) detected");
    }


    protected function shareOnAll(array $arrParams) : static
    {
        $title  = html_entity_decode($arrParams["title"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url    = $arrParams["url"];
        $emoji  = $arrParams["emoji"];

        $this->fxTitle("$emoji $title");
        $this->io->writeLn("🔗 $url");

        $arrServiceFilter = $this->getCliOption(static::CLI_OPT_SERVICES);
        $arrServiceFilter = array_map('strtolower', $arrServiceFilter);

        if( !empty($arrServiceFilter) && in_array(BaseMessenger::SERVICE_X, $arrServiceFilter) ) {
            $arrServiceFilter[] = BaseMessenger::SERVICE_TWITTER;
        }

        $arrServicesMap = [
            TelegramMessenger::SERVICE_NAME     => 'shareOnTelegram',
            FacebookPageMessenger::SERVICE_NAME => 'shareOnFacebook',
            TwitterMessenger::SERVICE_NAME      => 'shareOnTwitter',
            LinkedInPageMessenger::SERVICE_NAME => 'shareOnLinkedIn'
        ];

        foreach($arrServicesMap as $serviceName => $fx) {

            if( empty($arrServiceFilter) || in_array($serviceName, $arrServiceFilter) ) {

                $this->$fx($arrParams);
                continue;
            }

            $this->fxWarning(
                'Sharing on ' . ucfirst($serviceName) . ' ' .
                'skipped due to --' . static::CLI_OPT_SERVICES
            );
        }

        return $this;
    }


    protected function buildProductionUrl(string $url) : string
    {
        $parts  = parse_url($url);
        $host   = $parts['host'];
        $arrHostParts = explode('.', $host);

        if( count($arrHostParts) > 2 ) {

            $arrNewHostParts    = array_slice($arrHostParts, -2);
            $newHost            = implode('.', $arrNewHostParts);
            $prodUrl            = str_replace("https://$host", "https://$newHost", $url);

        } else {

            $prodUrl = $url;
        }

        return $prodUrl;
    }


    protected function shareOnTelegram(array $arrParams) : static
    {
        $this->io->write("✴ Telegram: ");

        $title          = $arrParams["title"];
        $url            = $arrParams["prodUrl"];
        $emoji          = $arrParams["emoji"];
        $buttonLabel    = $arrParams["cta"];

        try {
            $messageHtml =
                "<b>$emoji <a href=\"$url\">$title</a></b>";

            $this->telegram
                ->setMessageButtons([[
                    "text"  => "👉🏻 $buttonLabel 👈🏻",
                    "url"   => $url
                ]]);

            if( $this->isNotDryRun() ) {

                $result = $this->telegram->sendMessageToChannel($messageHtml);
                $url = $this->telegram->buildNewMessageUrl($result);
                $this->io->writeln("<info>$url</info>");
            }

        } catch(Exception $ex) {

            $this->io->writeln("<error>ERROR: " . $ex->getMessage()  . "</error>");
            $this->sendAlert("Telegram", $ex, $title, $url);
        }

        return $this;
    }


    protected function shareOnFacebook(array $arrParams) : static
    {
        $this->io->write("✴ Facebook: ");

        $title  = $arrParams["title"];
        // Facebook could ban the app (???) for posting unreachable URLs => forcing production URLs, even on dev/staging
        $url    = $arrParams["prodUrl"];

        try {
            if( $this->isNotDryRun() ) {

                $postId = $this->facebook->sendUrl($url);
                $url = $this->facebook->buildMessageUrl($postId);
                $this->io->writeln("<info>$url</info>");
            }

        } catch(Exception $ex) {

            $this->io->writeln("<error>ERROR: " . $ex->getMessage()  . "</error>");
            $this->sendAlert("Facebook", $ex, $title, $url);
        }

        return $this;
    }


    protected function shareOnTwitter(array $arrParams) : static
    {
        $this->io->write("✴ Twitter: ");

        $emoji  = $arrParams["emoji"];
        $title  = $arrParams["title"];
        $url    = $arrParams["prodUrl"];

        try {
            $message = "$emoji $title $url";

            if( $this->isNotDryRun() ) {

                $postId = $this->twitter->sendMessage($message);
                $url    = $this->twitter->buildMessageUrl($postId);
                $this->io->writeln("<info>$url</info>");
            }

        } catch(Exception $ex) {

            $this->io->writeln("<error>ERROR: " . $ex->getMessage()  . "</error>");
            $this->sendAlert("Twitter", $ex, $title, $url);
        }

        return $this;
    }


    protected function shareOnLinkedIn(array $arrParams) : static
    {
        $this->io->write("✴ LinkedIn: ");

        $title          = $arrParams["title"];
        $url            = $arrParams["prodUrl"];
        $spotlight      = $arrParams["spotlight"];

        try {
            if( $this->isNotDryRun() ) {

                $result = $this->linkedIn->sendUrl($title, $url, $spotlight);
                $url    = $this->linkedIn->buildNewMessageUrl($result);
                $this->io->writeln("<info>$url</info>");
            }

        } catch(Exception $ex) {

            $this->io->writeln("<error>ERROR: " . $ex->getMessage()  . "</error>");
            $this->sendAlert("LinkedIn", $ex, $title, $url);
        }

        return $this;
    }


    protected function sendAlert(string $serviceName, Exception $ex, string $title, string $url) : static
    {
        $message =
            "<b>ShareOnSocial error on $serviceName</b>" . PHP_EOL .
            "<code>" . $ex->getMessage() . "</code>" . PHP_EOL .
            "URL: $url";

        $this->telegram
            ->setMessageButtons([])
            ->sendErrorMessage($message);

        return $this;
    }
}
