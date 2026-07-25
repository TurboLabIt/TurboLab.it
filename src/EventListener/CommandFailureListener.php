<?php
namespace App\EventListener;

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use TurboLabIt\MessengersBundle\TelegramMessenger;


class CommandFailureListener
{
    public function __construct(protected TelegramMessenger $messenger) {}

    public function onCommandFailure(ConsoleErrorEvent $event)
    {
        // escape the dynamic parts: the error message can carry user input / markup and is sent to
        // Telegram with parse_mode=HTML (same class of bug as security-audit #29).
        $message =
            "<b>CommandFailure on " . htmlspecialchars((string)$event->getCommand()?->getName(), ENT_QUOTES) . "</b>" . PHP_EOL .
            "<code>" . htmlspecialchars($event->getError()->getMessage(), ENT_QUOTES) . "</code>";

        $this->messenger->sendErrorMessage($message);
    }
}
