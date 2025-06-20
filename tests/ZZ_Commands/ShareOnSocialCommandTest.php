<?php
namespace App\Tests\Smoke;

use App\Command\ShareOnSocialCommand;
use App\Tests\BaseT;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;


class ShareOnSocialCommandTest extends BaseT
{
    public function testCommand()
        {
            $command = static::getService(ShareOnSocialCommand::class);
            $result = $command->run( new ArrayInput([]), new ConsoleOutput() );
            $this->assertEquals($result, ShareOnSocialCommand::SUCCESS);
        }
}
