<?php
namespace App\Tests\Smoke;

use App\Command\SitemapGeneratorCommand;
use App\Tests\BaseT;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;


class SitemapTest extends BaseT
{
    public function testCommand()
    {
        /** @var SitemapGeneratorCommand $command */
        $command = static::getService("App\\Command\\SitemapGeneratorCommand");
        $result = $command->run( new ArrayInput([]), new ConsoleOutput() );
        $this->assertEquals($result, SitemapGeneratorCommand::SUCCESS);
    }
}
