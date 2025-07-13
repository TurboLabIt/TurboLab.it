<?php
namespace App\Command;

use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use App\Repository\PhpBB\UserRepository;
use Ddeboer\Imap\Server;
use Ddeboer\Imap\Message;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


#[AsCommand(name: 'EmailBounceManager', description: 'Unsubscribe bouncing email addresses')]
class EmailBounceManagerCommand extends AbstractBaseCommand
{
    const array MAILBOXES_TO_CHECK  = ['inbox', 'spam'];
    const array SUBJECT_TO_PROCESS  = [
        'Undelivered mail returned to sender', 'Delivery status notification',
        'Undeliverable:', 'failure notice', 'Mail system error',
        'Mail delivery failed', 'Rejected:'
    ];
    const string EMAIL_ADDRESS_REGEX = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/';

    const array BODY_TO_IGNORE = [
        // Unacceptable Mail Content "example.com"
        'Unacceptable Mail Content'
    ];

    protected bool $allowDryRunOpt  = true;

    protected array $arrMessagesToDelete        = [];
    protected array $arrAddressesToUnsubscribe  = [];
    protected array $arrAddressesFromBogus      = [];


    public function __construct(
        protected array $arrConfig, protected ParameterBagInterface $parameters, protected UserRepository $userRepository
    )
    {
        parent::__construct($arrConfig);
    }


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

        //
        $this->displayRecapSection('Address(es) to unsubscribe', $this->arrAddressesToUnsubscribe);
        if( $this->isNotDryRun() ) {
            $this->userRepository
                ->updateSubscriptions($this->arrAddressesToUnsubscribe, false, true);
        }

        //
        $this->displayRecapSection('Bogus (ignored) addresses', $this->arrAddressesFromBogus);
        if( $this->isNotDryRun() ) {
            $this->userRepository
                ->updateSubscriptions($this->arrAddressesFromBogus, true, false);
        }

        //
        $this->fxTitle("Deleting emails...");
        if( $this->isNotProd() ) {

            $this->fxInfo("🦘 Always skipped in non-prod");

        } elseif( $this->isNotDryRun() ) {

            foreach($this->arrMessagesToDelete as $message) {
                $message->delete();
            }

            $mailserver->expunge();
        }

        return $this->endWithSuccess();
    }


    protected function buildItemTitle($key, $item) : string
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
        $arrAddresses = array_merge( $this->extractAddressesFromBody($message), $this->extractAddressesFromSubParts($message) );
        $arrAddresses = $this->processAddresses($arrAddresses);

        if( empty($arrAddresses) ) {
            return $this;
        }

        $isBogusMessage = $this->isBogusBody($message);

        $this->arrMessagesToDelete[] = $message;

        foreach($arrAddresses as $address) {

            if( in_array($address, $this->arrAddressesToUnsubscribe) ) {
                continue;
            }

            if($isBogusMessage) {

                if( in_array($address, $this->arrAddressesFromBogus) ) {
                    continue;
                }

                $this->arrAddressesFromBogus[] = $address;

            } else {

                $keyInBogus = array_search($address, $this->arrAddressesFromBogus);
                if($keyInBogus !== false) {
                    unset($this->arrAddressesFromBogus[$keyInBogus]);
                }

                $this->arrAddressesToUnsubscribe[] = $address;
            }
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
        preg_match_all(static::EMAIL_ADDRESS_REGEX, $body, $arrAddresses);
        $arrAddresses = reset($arrAddresses);

        return $arrAddresses;
    }


    protected function extractAddressesFromSubParts(Message $message) : array
    {
        $arrAllAddresses = [];

        $iterator = new \RecursiveIteratorIterator($message, \RecursiveIteratorIterator::SELF_FIRST);
        foreach($iterator as $part) {

            $arrAddresses = [];
            $partContent  = $part->getContent();

            preg_match_all(static::EMAIL_ADDRESS_REGEX, $partContent, $arrAddresses);
            $arrAddresses = reset($arrAddresses);

            $arrAllAddresses = array_merge($arrAllAddresses, $arrAddresses);
        }

        return $arrAllAddresses;
    }


    protected function processAddresses(array $arrAddresses) : array
    {
        $arrCleanAddresses = [];
        foreach($arrAddresses as $address) {

            $address = mb_strtolower($address);
            $address = trim($address);

            if(
                str_contains($address, '@turbolab.it') || str_contains($address, 'postmaster@') ||
                str_contains($address, 'mailer-daemon@')
            ) {
                continue;
            }

            $arrCleanAddresses[] = $address;
        }

        return array_unique($arrCleanAddresses);
    }


    protected function isBogusBody(Message $message) : bool
    {
        // Content of text/html part, if present
        $body = $message->getCompleteBodyHtml();

        if( empty($body) ) {
            // Content of text/plain part, if present
            $body = $message->getCompleteBodyText();
        }

        if( empty($body) ) {
            return false;
        }

        $body = mb_strtolower($body);

        foreach(static::BODY_TO_IGNORE as $check) {

            $check = mb_strtolower($check);

            if( str_contains($body, $check) ) {
                return true;
            }
        }

        return false;
    }


    public function displayRecapSection(string $title, array $arrAddresses) : static
    {
        $this->fxTitle($title);
        $addressesNum = count($arrAddresses);
        $this->fxOK("$addressesNum address(es) extracted");

        if( $addressesNum > 0 ) {
            (new Table($this->output))
                ->setRows( array_map(fn($str) => [$str], $arrAddresses) )
                ->render();
        }

        return $this;
    }
}
