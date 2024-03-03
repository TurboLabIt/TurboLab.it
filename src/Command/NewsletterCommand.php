<?php
namespace App\Command;

use App\Service\Newsletter;
use App\Service\User;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\PhpBB\TopicCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use TurboLabIt\BaseCommand\Service\Options;


#[AsCommand(
    name: 'Newsletter',
    description: 'Generate and send the weekly newsletter',
)]
class NewsletterCommand extends AbstractBaseCommand
{
    const string CLI_ARG_ACTION     = 'action';
    const null CLI_ACTION_TEST      = null;
    const string CLI_ACTION_DOIT    = 'DOIT';
    const array CLI_ARG_ACTIONS     = [self::CLI_ACTION_TEST, self::CLI_ACTION_DOIT];

    protected bool $allowDryRunOpt  = true;


    public function __construct(
        protected ArticleCollection $articleCollection, protected TopicCollection $topicCollection,
        protected Newsletter $newsletter
    )
    {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this->addArgument(
            static::CLI_ARG_ACTION, InputArgument::OPTIONAL,
            'Action to execute',
            static::CLI_ACTION_TEST,
            static::CLI_ARG_ACTIONS
        );

        parent::configure();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $argAction =$this->getCliArgument(static::CLI_ARG_ACTION);
        $this->fxInfo("Running with " . static::CLI_ARG_ACTION . " ##$argAction##");

        if( !in_array($argAction, static::CLI_ARG_ACTIONS) ) {
            return $this->endWithError("Invalid " . static::CLI_ARG_ACTION . ". Allowed values: " . implode(' | ', static::CLI_ARG_ACTIONS) );
        }

        $this->fxTitle("Email delivery check");
        if( $this->isDryRun() ) {
            $this->fxWarning("Email delivery is BLOCKED");
        } else {
            $this->fxWarning("📨🔥 Emails are HOT! 🔥📨");
        }

        $this->newsletter->block( $this->isDryRun(true) );


        $this
            ->fxTitle("Loading newsletter content...")
            ->newsletter->loadContent();

        $countArticles  = $this->newsletter->countArticles();
        $countTopics    = $this->newsletter->countTopics();
        $this->fxOK("$countArticles articles(s) and $countTopics topic(s) loaded");
        $this->fxInfo( $this->newsletter->getSubject() );

        if( $countArticles == 0 && $countTopics == 0 ) {

            $this->newsletter->lowContentNotification();
            return
                $this->endWithError(
                    "There isn't enough content! You can still check the preview on " . $this->newsletter->getPreviewUrl()
                );
        }

        $this->fxTitle("Generating article...");
        if( $argAction != static::CLI_ACTION_TEST && $this->isNotDryRun(true) ) {
            $this->newsletter->saveOnTheWeb();
        } else {
            $this->fxWarning('Skipped due to test mode or --' . Options::CLI_OPT_DRY_RUN);
        }


        if( $argAction == static::CLI_ACTION_TEST ) {

            $this
                ->fxTitle("Loading test recipients...")
                ->newsletter->loadTestRecipients();

        } else {

            $this
                ->fxTitle("Loading recipients...")
                ->newsletter->loadRecipients();
        }

        $recipientsCount = $this->newsletter->countRecipients();
        $this->fxOK("$recipientsCount recipient(s) loaded");

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


    protected function sendOne(int $key, User $user)
    {
        $this->newsletter
            ->buildForOne($user)
            ->send();
    }
}
