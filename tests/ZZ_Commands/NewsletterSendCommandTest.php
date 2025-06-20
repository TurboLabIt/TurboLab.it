<?php
namespace App\Tests\Smoke;

use App\Command\NewsletterSendCommand;
use App\Tests\BaseT;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;


class NewsletterSendCommandTest extends BaseT
{
    public function testCommand()
        {
            $command = static::getService(NewsletterSendCommand::class);
            $result = $command->run( new ArrayInput([]), new ConsoleOutput() );
            $this->assertEquals($result, NewsletterSendCommand::SUCCESS);
        }
}
