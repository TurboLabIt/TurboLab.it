<?php
namespace App\Command;

use App\Service\Cms\Tag;
use App\ServiceCollection\Cms\TagCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;


#[AsCommand(name: 'TagAggregator', description: 'Aggregate and replace tags')]
class TagAggregatorCommand extends AbstractBaseCommand
{
    const array BAD_TAGS = [
        'windowsxp' => Tag::ID_WINDOWS, 'windows7' => Tag::ID_WINDOWS,
        'windows8' => Tag::ID_WINDOWS, 'windows8.0' => Tag::ID_WINDOWS, 'windows8.1' => Tag::ID_WINDOWS,
        'windows10' => Tag::ID_WINDOWS, 'windows11' => Tag::ID_WINDOWS, 'windows12' => Tag::ID_WINDOWS,
        //
        'malware' => Tag::ID_ANTIVIRUS_MALWARE, 'antivirus/antimalware' => Tag::ID_ANTIVIRUS_MALWARE,
        'ransomware' => Tag::ID_ANTIVIRUS_MALWARE, 'adware' => Tag::ID_ANTIVIRUS_MALWARE,
        'rootkit' => Tag::ID_ANTIVIRUS_MALWARE, 'browserhijacking' => Tag::ID_ANTIVIRUS_MALWARE,
        //
        'bufale' => Tag::ID_FAKE_NEWS, 'bufala' => Tag::ID_FAKE_NEWS,
        'fakenews' => Tag::ID_FAKE_NEWS, 'disinformazione' => Tag::ID_FAKE_NEWS,
        //
        'disinstallare' => Tag::ID_UNINSTALL, 'disinstallazioni' => Tag::ID_UNINSTALL,
        'rimozioneprogrammi' => Tag::ID_UNINSTALL,
        //
        'aggiornamentosoftware' => Tag::ID_SOFTWARE_UPDATE, 'aggiornamento' => Tag::ID_SOFTWARE_UPDATE,
        //
        'connettività' => Tag::ID_INTERNET_PROVIDER, 'connessione' => Tag::ID_INTERNET_PROVIDER,
        'retedatimobile3g4glte' => Tag::ID_INTERNET_PROVIDER,
        //
        'wakeonlan' => Tag::ID_WAKE_ON_LAN
    ];


    public function __construct(protected TagCollection $tags, protected EntityManagerInterface $em)
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        parent::execute($input, $output);

        $this
            ->fxTitle('Loading tags...')
            ->tags->loadAll();

        $this->fxOK( count($this->tags) . " tag(s) loaded");

        $this->processItems($this->tags, [$this, 'assignReplacement']);

        $this->em->flush();

        return $this->endWithSuccess();
    }


    /**
     * @param $id
     * @param $tag Tag
     * @return bool
     */
    protected function iteratorSkipCondition($id, $tag) : bool
    {
        $key = $tag->getTitleForAggregationComparison();
        return !array_key_exists($key, static::BAD_TAGS);
    }


    protected function assignReplacement($id, Tag $tag) : static
    {
        $key            = $tag->getTitleForAggregationComparison();
        $replacementId  = static::BAD_TAGS[$key];
        $replacement    = $this->tags->get($replacementId);

        if( empty($replacement) ) {
            $this->endWithError("Replacement tag with ID ##$replacementId## for ##$key## doesn't exist");
        }

        $tag->setReplacement($replacement);

        return $this;
    }
}
