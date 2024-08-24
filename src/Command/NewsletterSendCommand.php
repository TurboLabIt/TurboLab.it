<?php
namespace App\Command;

use App\Service\Newsletter;
use App\Service\User;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\PhpBB\TopicCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
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
    protected bool $allowDryRunOpt          = true;
    protected bool $limitedByDefaultOpt     = true;
    protected ?array $allowUnlockOptIn = null;


    public function __construct(
        protected ParameterBagInterface $parameters,
        protected ArticleCollection $articleCollection, protected TopicCollection $topicCollection,
        protected Newsletter $newsletter
    )
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this->fxTitle("Email delivery check");
        if( $this->isDryRun() ) {

            $this->fxWarning("Email delivery is BLOCKED");

        } else {

            $this->fxWarning("📨🔥 Emails are HOT! 🔥📨");
        }

        $this->newsletter->block( $this->isDryRun(true) );


        $countArticles =
            $this
                ->fxTitle("Loading newsletter content...")
                ->newsletter
                    ->loadContent()
                    ->countArticles();

        if( $countArticles == 0 ) {

            $this->fxWarning("No articles loaded!");

            if( $this->isLimited(true) ) {

                $this
                    ->fxWarning("Loading some random articles due to test execution...")
                    ->newsletter->loadTestArticles();

                $countArticles = $this->newsletter->countArticles();
            }
        }

        $this->fxOK("$countArticles articles(s) loaded");


        $countTopics = $this->newsletter->countTopics();
        if( $countTopics == 0 ) {

            $this->fxWarning("No topics loaded!");

            if( $this->isLimited(true) ) {

                $this
                    ->fxWarning("Loading some random topics due to test execution...")
                    ->newsletter->loadTestTopics();

                $countTopics = $this->newsletter->countTopics();
            }
        }

        $this->fxOK("$countTopics topic(s) loaded");


        $this
            ->fxTitle("Newsletter subject")
            ->fxInfo( $this->newsletter->getSubject() );


        if( $countArticles == 0 && $countTopics == 0 ) {

            $errorMessage = 
                $this->newsletter->sendLowContentNotification();

            return $this->endWithWarning($errorMessage);
        }


        if( $this->isLimited(true) ) {

            $this
                ->fxTitle("Loading TEST recipients...")
                ->newsletter->loadTestRecipients();

        } else {

            $this
                ->fxTitle("Loading REAL recipients...")
                ->newsletter->loadRecipients();
        }


        $recipientsCount = $this->newsletter->countRecipients();

        // this shouldn't happen. It's just me being a maniac
        if( $recipientsCount > 5 && !$this->isNotProd() ) {

            throw new \RuntimeException(
                "Fail-safe triggered! There are more than 5 recipients in non-prod!"
            );
        }

        $this->fxOK("$recipientsCount recipient(s) loaded");

        
        $this->fxTitle("Generating the article on the website...");
        $persistArticle = $this->isNotDryRun() && ( $this->isNotProd() || $this->isUnlocked() );
        $articleUrl = $this->newsletter->saveOnTheWeb($persistArticle);

        if($persistArticle) {

            $this->fxOK("Article ready: " . $articleUrl);

        } else {

            $this->fxWarning('The article was NOT saved due to test mode or --' . Options::CLI_OPT_DRY_RUN);
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


    protected function sendOne(int $key, User $user)
    {
        $this->newsletter
            ->buildForOne($user)
            ->send();
    }
}
