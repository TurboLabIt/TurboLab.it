<?php
namespace App\EventListener;

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use TurboLabIt\Messengers\TelegramMessenger;


class CommandFailureListener
{
    public function __construct(protected TelegramMessenger $messenger) {}

    public function onCommandFailure(ConsoleErrorEvent $event)
    {
        $message =
            "<b>CommandFailure on " . $event->getCommand()?->getName() . "</b>" . PHP_EOL .
            "<code>" . $event->getError()->getMessage() . "</code>";

        $this->messenger->sendErrorMessage($message);
    }
}
