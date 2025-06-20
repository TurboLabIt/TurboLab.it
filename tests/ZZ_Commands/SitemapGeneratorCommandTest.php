<?php
namespace App\Tests\Smoke;

use App\Command\SitemapGeneratorCommand;
use App\Tests\BaseT;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;


class SitemapGeneratorCommandTest extends BaseT
{
    public function testCommand()
    {
        $command = static::getService(SitemapGeneratorCommand::class);
        $result = $command->run( new ArrayInput([]), new ConsoleOutput() );
        $this->assertEquals($result, SitemapGeneratorCommand::SUCCESS);
    }
}
