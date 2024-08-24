<?php
namespace App\EventListener;

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use TurboLabIt\Messengers\TelegramMessenger;


class CommandFailureListener
{
    public function __construct(protected TelegramMessenger $messenger) {}

    public function onCommandFailure(ConsoleErrorEvent $event)
    {
        // https://symfony.com/doc/current/components/console/events.html#the-consoleevents-error-event

        $text =
            "🛑 TLI is failing" . PHP_EOL . PHP_EOL .
            "Command: *" . $event->getCommand()?->getName() . "*" . PHP_EOL .
            "Error: *" . $event->getError()->getMessage() . "*";

        $this->messenger->sendErrorMessage($text);
    }
}
