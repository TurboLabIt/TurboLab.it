<?php
namespace App\Command;

use App\Service\Newsletter;
use App\Service\User;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\PhpBB\TopicCollection;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use TurboLabIt\BaseCommand\Service\Options;


/**
 * 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/newsletter.md
 */
#[AsCommand(
    name: 'NewsletterSend',
    description: 'Generate and send the weekly newsletter',
)]
class NewsletterSendCommand extends AbstractBaseCommand
{
    const string CLI_OPT_REAL_RECIPIENTS    = 'real-recipients';
    const string CLI_OPT_USE_LOCAL_SMTP     = 'local-smtp';

    protected bool $allowSendMessagesOpt    = true;
    protected bool $allowDryRunOpt          = true;

    protected Newsletter $mailer;


    public function __construct(
        protected ParameterBagInterface $parameters,
        protected ArticleCollection $articleCollection, protected TopicCollection $topicCollection,
        protected Newsletter $newsletter
    )
    {
        // this is required for the auto-check performed by AbstractBaseCommand
        $this->mailer = $newsletter;
        parent::__construct();
    }


    protected function configure() : void
    {
        parent::configure();
        $this->addOption(static::CLI_OPT_REAL_RECIPIENTS, null, InputOption::VALUE_NONE);
        $this->addOption(
            static::CLI_OPT_USE_LOCAL_SMTP, null, InputOption::VALUE_NONE,
            'Change the DSN to smtp://localhost'
        );
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this->fxTitle("📬 Loading the recipients...");
        $realRecipients = $this->getCliOption(static::CLI_OPT_REAL_RECIPIENTS);
        if( $realRecipients && $this->isSendingMessageAllowed(true) && $this->isNotProd() ) {
            return $this->endWithError("Cannot use real recipients with hot emails in non-prod environment!");
        }

        if($realRecipients) {

            $this
                ->fxWarning("👤👤👤 REAL RECIPIENTS 👤👤👤")
                ->newsletter->loadRecipients();

        } else {

            $this
                ->fxInfo("🧪 TEST recipients")
                ->newsletter->loadTestRecipients();

            $this->io->newLine();

            $arrRecipients = $this->newsletter->getRecipients();
            foreach($arrRecipients as $recipient) {
                $this->fxInfo( "📬 " . $recipient->getEmail() );
            }

            $this->io->newLine();
        }

        $recipientsCount = $this->newsletter->countRecipients();
        $this->fxOK("$recipientsCount recipient(s) loaded");


        $countArticles =
            $this
                ->fxTitle("Loading newsletter content...")
                ->newsletter
                    ->loadContent()
                    ->countArticles();

        $canLoadTestContent = !$this->isSendingMessageAllowed(true) || !$realRecipients;

        if( $countArticles == 0 ) {

            $this->fxWarning("No articles loaded!");

            if($canLoadTestContent) {

                $this
                    ->fxWarning("🧪 Loading some random articles...")
                    ->newsletter->loadTestArticles();

                $countArticles = $this->newsletter->countArticles();
            }
        }

        $this->fxOK("$countArticles articles(s) loaded");


        $countTopics = $this->newsletter->countTopics();
        if( $countTopics == 0 ) {

            $this->fxWarning("No topics loaded!");

            if($canLoadTestContent) {

                $this
                    ->fxWarning("🧪 Loading some random topics...")
                    ->newsletter->loadTestTopics();

                $countTopics = $this->newsletter->countTopics();
            }
        }

        $this->fxOK("$countTopics topic(s) loaded");


        $this
            ->fxTitle("Newsletter subject")
            ->fxInfo( $this->newsletter->getSubject() );


        if( $countArticles == 0 && $countTopics == 0 ) {

            $errorMessage = $this->newsletter->sendLowContentNotification();
            $errorMessage = strip_tags($errorMessage);
            return $this->endWithWarning($errorMessage);
        }


        $this->fxTitle("Generating the article on the website...");
        $sendingInProd  = $this->isProd() && $realRecipients && $this->isSendingMessageAllowed();
        $persistArticle = $this->isNotDryRun() && ( $sendingInProd || $this->isNotProd() );

        // while TLI1 is still live, don't save the web article in prod
        if( $this->isProd() ) {
            $persistArticle = false;
        }

        $articleUrl = $this->newsletter->saveOnTheWeb($persistArticle);

        if($persistArticle) {

            $this->fxOK("Article ready: " . $articleUrl);

        } else {

            $this->fxWarning('The web article was NOT saved');
        }

        $this->fxTitle("Processing every recipient...");
        $arrRecipients = $this->newsletter->getRecipients();
        $this->processItems($arrRecipients, [$this, 'sendOne'], null, [$this, 'buildItemTitle']);

        $this->fxTitle("Error report");
        $arrErrorReport = $this->newsletter->getFailingReport();
        if( empty($arrErrorReport) ) {

            $this->fxOK("No errors");

        } else {

            $this->bashFx->fxError("Some items are FAILING!");

            $arrHeader = array_keys( reset($arrErrorReport));
            (new Table($output))
                ->setHeaders($arrHeader)
                ->setRows($arrErrorReport)
                ->render();

            $this->io->newLine();
        }

        $this
            ->fxTitle("📄 Check the newsletter preview!")
            ->fxOK( $this->newsletter->getPreviewUrl() );

        return $this->endWithSuccess();
    }


    protected function buildItemTitle($key, $item) : string
    {
        return "[$key] " . $item->getUsername() . " <" . $item->getEmail() . ">";
    }


    protected function sendOne(int $key, User $user) : void
    {
        if( $this->getCliOption(static::CLI_OPT_USE_LOCAL_SMTP) ) {
            $this->newsletter->useLocalSmtpOnce();
        }

        $this->newsletter
            ->buildForOne($user)
            ->send();
    }
}
