<?php
namespace App\Tests\ZZ_Commands;

use App\Command\ChristmasArticleGeneratorCommand;
use App\Command\FilesNotFoundCommand;
use App\Command\NewsletterSendCommand;
use App\Command\ShareOnSocialCommand;
use App\Command\SitemapGeneratorCommand;
use App\Tests\BaseT;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;


class CommandsTest extends BaseT
{
    public function testNewsletterCommand() : void
    {
        $command = static::getService(NewsletterSendCommand::class);
        $result = $command->run( new ArrayInput([]), new ConsoleOutput() );
        $this->assertEquals($result, NewsletterSendCommand::SUCCESS);
    }


    public function testShareOnSocialCommand() : void
    {
        $command = static::getService(ShareOnSocialCommand::class);
        $result = $command->run( new ArrayInput([]), new ConsoleOutput() );
        $this->assertEquals($result, ShareOnSocialCommand::SUCCESS);
    }


    public function testSitemapGeneratorCommand() : void
    {
        $command = static::getService(SitemapGeneratorCommand::class);
        $result = $command->run( new ArrayInput([]), new ConsoleOutput() );
        $this->assertEquals($result, SitemapGeneratorCommand::SUCCESS);
    }


    public function testChristmasArticleGeneratorCommand() : void
    {
        $command = static::getService(ChristmasArticleGeneratorCommand::class);
        $result = $command->run( new ArrayInput([]), new ConsoleOutput() );
        $this->assertEquals($result, ChristmasArticleGeneratorCommand::SUCCESS);
    }


    public function testFilesNotFoundCommand() : void
    {
        $command = static::getService(FilesNotFoundCommand::class);
        $result = $command->run( new ArrayInput([]), new ConsoleOutput() );
        $this->assertEquals($result, FilesNotFoundCommand::SUCCESS);
    }
}
