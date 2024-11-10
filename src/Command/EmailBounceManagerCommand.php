<?php
namespace App\Command;

use App\Repository\PhpBB\UserRepository;
use Ddeboer\Imap\Message;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use Ddeboer\Imap\Server;


#[AsCommand(name: 'EmailBounceManager', description: 'Unsubscribe bouncing email addresses')]
class EmailBounceManagerCommand extends AbstractBaseCommand
{
    const array MAILBOXES_TO_CHECK  = ['inbox', 'spam'];
    const array SUBJECT_TO_PROCESS  = [
        'Undelivered mail returned to sender', 'Delivery status notification'
    ];

    protected bool $allowDryRunOpt  = true;

    protected array $arrMessagesToDelete    = [];
    protected array $arrExtractedAddresses  = [];


    public function __construct(
        protected array $arrConfig, protected ParameterBagInterface $parameters, protected UserRepository $userRepository
    )
        { parent::__construct($arrConfig); }


    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        parent::execute($input, $output);

        $this->fxTitle("Authenticating on ##" . $this->arrConfig["mailbox"]["hostname"] . "##...");
        $mailserver =
            (new Server($this->arrConfig["mailbox"]["hostname"]))
                ->authenticate($this->arrConfig["mailbox"]["username"], $this->arrConfig["mailbox"]["password"]);

        $this->fxOK();

        $this->fxTitle("Iterating over each mailbox...");
        $mailboxes = $mailserver->getMailboxes();
        foreach ($mailboxes as $mailbox) {

            // Skip container-only mailboxes
            // @see https://secure.php.net/manual/en/function.imap-getmailboxes.php
            if( $mailbox->getAttributes() & \LATT_NOSELECT ) {

                $this->fxInfo("🦘 Skipping IMAP LATT_NOSELECT");
                continue;
            }

            $mailboxName = mb_strtolower( $mailbox->getName() );
            if( !in_array($mailboxName, self::MAILBOXES_TO_CHECK) ) {

                $mailboxName = mb_strtoupper( $mailbox->getName() );
                $this->fxInfo("🦘 Mailbox ##$mailboxName## not whitelisted, skipped");
                continue;
            }

            $mailboxName = mb_strtoupper( $mailbox->getName() );
            $this->fxInfo("📬 Working on ##$mailboxName##");
            $messages = $mailbox->getMessages();
            $this->processItems($messages, [$this, 'processOneMessage'], null, [$this, 'buildItemTitle']);
        }

        $this->fxTitle("Post-extraction status");
        $addressesNum = count($this->arrExtractedAddresses);
        $this->fxOK("$addressesNum address(es) extracted");

        if( $addressesNum == 0 ) {

            $this->fxInfo("No address extracted. There is nothing to do");
            return $this->endWithSuccess();
        }

        (new Table($output))
            ->setRows( array_map(fn($str) => [$str], $this->arrExtractedAddresses) )
            ->render();

        $this->fxTitle("Unsubscribing from newsletter, stop all notifications...");
        if( $this->isNotDryRun() ) {
            $this->userRepository->handleBounceEmailAddress($this->arrExtractedAddresses);
        }

        $this->fxTitle("Deleting emails...");
        if( $this->isNotProd() ) {

            $this->fxInfo("🦘 Skipped in non-prod");

        } elseif( $this->isNotDryRun() ) {

            foreach($this->arrMessagesToDelete as $message) {
                $message->delete();
            }

            $mailserver->expunge();
        }

        return $this->endWithSuccess();
    }


    protected function buildItemTitle($key, $item): string
    {
        $date       = $item->getDate()->format("Y-m-d H:i:s");
        $subject    = mb_substr($item->getSubject(), 0, 50);
        $from       = $item->getFrom()->getName() . " <" . $item->getFrom()->getAddress() . ">";
        return "🗓️ $date 💬 $subject ✉️ $from";
    }


    protected function iteratorSkipCondition($key, $item) : bool
    {
        $subject = $item->getSubject();

        foreach(static::SUBJECT_TO_PROCESS as $check) {

            $subject    = mb_strtolower($subject);
            $check      = mb_strtolower($check);

            if( str_contains($subject, $check) ) {
                return false;
            }
        }

        return true;
    }


    protected function processOneMessage($key, $message) : static
    {
        $arrAddresses = $this->extractAddressesFromBody($message);

        if( empty($arrAddresses) ) {
            return $this;
        }

        $this->arrMessagesToDelete[] = $message;

        foreach($arrAddresses as $address) {

            if( in_array($address, $this->arrExtractedAddresses) ) {
                continue;
            }

            $this->arrExtractedAddresses[] = $address;
        }

        return $this;
    }


    protected function extractAddressesFromBody(Message $message) : array
    {
        // Content of text/html part, if present
        $body = $message->getCompleteBodyHtml();

        if( empty($body) ) {
            // Content of text/plain part, if present
            $body = $message->getCompleteBodyText();
        }

        if( empty($body) ) {
            return [];
        }

        $arrAddresses = [];
        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $body, $arrAddresses);
        $arrAddresses = reset($arrAddresses);

        foreach($arrAddresses as $k => $address) {

            $address = mb_strtolower($address);
            $address = trim($address);

            if( str_contains($address, '@turbolab.it') || str_contains($address, 'postmaster@') || str_contains($address, 'mailer-daemon@') ) {

                unset($arrAddresses[$k]);
                continue;
            }

            $arrAddresses[$k] = $address;
        }

        $arrAddresses = array_unique($arrAddresses);
        return $arrAddresses;
    }
}
