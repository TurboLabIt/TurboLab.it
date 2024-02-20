<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;


#[AsCommand(
    name: 'Newsletter',
    description: 'Generate and send the weekly newsletter',
)]
class NewsletterCommand extends AbstractBaseCommand
{
    const string CLI_ARG_ACTION    = 'action';
    const string CLI_ACTION_TEST   = 'test';
    const string CLI_ACTION_CRON   = 'cron';
    const array CLI_ARG_ACTIONS     = [self::CLI_ACTION_TEST, self::CLI_ACTION_CRON];


    protected function configure(): void
    {
        $this->addArgument(
            static::CLI_ARG_ACTION, InputArgument::OPTIONAL,
            'Action to execute: test (default), cron',
            static::CLI_ACTION_TEST,
            [static::CLI_ACTION_TEST, static::CLI_ACTION_CRON]
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


        /*$this
            ->selectArticles()
            ->selectNewFromForum()
            ->createWebArticle()
            ->selectRecipients()
            ->sendNewsletter();
        */

        return $this->endWithSuccess();
    }


}
