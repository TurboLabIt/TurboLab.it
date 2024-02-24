<?php
namespace App\Command;

use App\Service\Mailer;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\PhpBB\TopicCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;


#[AsCommand(
    name: 'Newsletter',
    description: 'Generate and send the weekly newsletter',
)]
class NewsletterCommand extends AbstractBaseCommand
{
    const string CLI_ARG_ACTION     = 'action';
    const string CLI_ACTION_TEST    = 'test';
    const string CLI_ACTION_CRON    = 'cron';
    const array CLI_ARG_ACTIONS     = [self::CLI_ACTION_TEST, self::CLI_ACTION_CRON];

    protected bool $allowDryRunOpt  = true;


    public function __construct(
        protected ArticleCollection $articleCollection, protected TopicCollection $topicCollection,
        protected Mailer $mailer
    )
    {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this->addArgument(
            static::CLI_ARG_ACTION, InputArgument::OPTIONAL,
            'Action to execute: test (default), cron',
            static::CLI_ACTION_TEST,
            static::CLI_ARG_ACTIONS
        );
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $argAction =$this->getCliArgument(static::CLI_ARG_ACTION);
        $this->fxInfo("Running with " . static::CLI_ARG_ACTION . " ##$argAction##");

        if( !in_array($argAction, static::CLI_ARG_ACTIONS) ) {
            return $this->endWithError("Invalid " . static::CLI_ARG_ACTION . ". Allowed values: " . implode(' | ', static::CLI_ARG_ACTIONS) );
        }


        $this
            ->selectArticles()
            ->selectForumTopics()
//            ->createWebArticle()
//            ->selectRecipients()
            ->sendNewsletter()
        ;

        return $this->endWithSuccess();
    }


    protected function selectArticles() : static
    {
        $this->fxTitle('Selecting articles....');
        $this->articleCollection->loadLatestForNewsletter();
        return $this->fxOK("##" . $this->articleCollection->count() . "## article(s) loaded");
    }


    protected function selectForumTopics() : static
    {
        $this->fxTitle('Selecting new forum topics....');
        $this->topicCollection->loadLatestForNewsletter();
        return $this->fxOK("##" . $this->topicCollection->count() . "## topic(s) loaded");
    }


    protected function createWebArticle() : static
    {
        // TODO zaneee!!! createWebArticle in newsletter
        return $this;
    }


    protected function sendNewsletter() : static
    {

    }
}
