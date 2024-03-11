<?php
namespace App\Command;

use App\Entity\PhpBB\Forum;
use App\Service\PhpBB\ForumUrlGenerator;
use App\Service\PhpBB\Topic;
use App\ServiceCollection\PhpBB\TopicCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use Twig\Environment;


#[AsCommand(
    name: 'SitemapGenerator',
    description: 'Generate the XML Sitemap files',
    aliases: ['sitemap']
)]
class SitemapGeneratorCommand extends AbstractBaseCommand
{
    const int URL_LIMIT_PER_FILE = 49500;
    const string VAR_PATH       = 'sitemaps';
    const string VAR_PATH_TEMP  = 'sitemaps_new';

    protected bool $allowDryRunOpt = true;

    protected string $outDir;
    protected string $outDirFinal;

    protected array $arrSections = [
        "site"  => [],
        "forum" => []
    ];

    protected array $arrFilesForIndex = [];


    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected TopicCollection $topicCollection,
        protected Environment $twig, protected ProjectDir $projectDir,
        protected Filesystem $filesystem, protected ForumUrlGenerator $forumUrlGenerator
    )
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this
            ->fxTitle("Creating folders...")
            ->generateOutputFolders();


        // forum
        $this
            ->addForumIndexes()
            ->addForumTopics()
            ->writeForumXML();

        $this
            ->fxTitle("Move new directory to final, public path...")
            ->moveDirectory();

        return $this->endWithSuccess();
    }


    protected function generateOutputFolders() : static
    {
        $this->outDir = $this->projectDir->getVarDir(static::VAR_PATH_TEMP);
        if( is_dir($this->outDir) ) {
            $this->filesystem->remove($this->outDir);
        }

        $this->projectDir->createVarDir(static::VAR_PATH_TEMP);
        $this->fxOK("New sitemaps are about to be generated in ##$this->outDir##");

        $this->outDirFinal = $this->projectDir->getVarDir(static::VAR_PATH);
        $this->fxOK("The final sitemap files will live into ##$this->outDirFinal##");

        return $this;
    }


    protected function addForumIndexes() : static
    {
        $this->fxTitle("Adding /forum...");
        $this->arrSections["forum"][0][] = [
            "url"       => $this->forumUrlGenerator->generateHomeUrl(),
            "lastmod"   => (new \DateTime())->format(DATE_W3C),
            "changefreq"=> 'hourly'
        ];

        $this->fxTitle("Selecting Forums...");
        $arrForums = $this->entityManager->getRepository(Forum::class)->findAll();
        $countForums = count($arrForums);
        $this->fxOK("$countForums forum(s) loaded");

        $this->fxTitle("Building in-memory structure...");

        /** @var Forum $forum */
        foreach($arrForums as $forum) {

            $oDateTime = \DateTime::createFromFormat('U', $forum->getLastPostTime());
            $oDateTime->setTimezone(new \DateTimeZone('Europe/Rome'));

            $this->arrSections["forum"][0][] = [
                "url"       => $this->forumUrlGenerator->generateForumUrlFromId($forum->getId()),
                "lastmod"   => $oDateTime->format(DATE_W3C),
                "changefreq"=> $this->buildScanFrequencyFromDateTime($oDateTime)
            ];
        }

        return $this;
    }


    protected function addForumTopics() : static
    {
        $this->fxTitle("Selecting forum Topics...");
        $this->topicCollection->loadAll();
        $countTopics =  $this->topicCollection->count();
        $this->fxOK( number_format($countTopics, 0, ',', '.') . " topic(s) loaded");

        $this->fxTitle("Building in-memory structure...");
        $currentFileItem    = count($this->arrSections["forum"][0]);
        $currentFileIndex   = 0;

        /** @var Topic $topic */
        foreach($this->topicCollection as $topic) {

            if( $currentFileItem == static::URL_LIMIT_PER_FILE ) {

                $currentFileIndex++;
                $currentFileItem = 0;
            }

            $oDateTime = $topic->getLastPostDateTime();
            $this->arrSections["forum"][$currentFileIndex][] = [
                "url"       => $topic->getUrl(),
                "lastmod"   => $oDateTime->format(DATE_W3C),
                "changefreq"=> $this->buildScanFrequencyFromDateTime($oDateTime)
            ];

            $currentFileItem++;
        }

        return $this;
    }


    protected function writeForumXML() : static
    {
        $this->fxTitle("Building the XML...");
        foreach($this->arrSections["forum"] as $index => $arrData) {

            $txtXml = $this->twig->render('sitemap/sitemap.xml.twig', [
                "Items" => $arrData
            ]);

            $XMLDoc = new \DOMDocument();
            $XMLDoc->preserveWhiteSpace = false;
            $XMLDoc->formatOutput = true;
            $XMLDoc->loadXML($txtXml);
            $txtXml = $XMLDoc->saveXML();

            $fileName   = "forum_" . $index . ".xml";
            $filePath   = $this->outDir . $fileName;
            $this->arrFilesForIndex[] = $fileName;

            $countTopics = count($arrData);
            $label = "##$filePath## with " . number_format($countTopics, 0, ',', '.') . " items";

            if( $this->isNotDryRun(true) ) {

                file_put_contents($filePath, $txtXml);
                $this->fxOK($label . " written");

            } else {

                $this->fxWarning($label . " wasn't written due to --dry-run");
            }
        }

        return $this;
    }


    protected function buildScanFrequencyFromDateTime(\DateTime $date) : string
    {
        $diff       = (new \DateTime())->format('U') - $date->getTimestamp();
        $diffDays   = round($diff / 86400);

        if( $diffDays < 7 ) {
            return 'hourly';
        }

        if( $diffDays < (365*2) ) {
            return 'daily';
        }

        return 'weekly';
    }


    public function moveDirectory() : static
    {
        if( $this->isDryRun(true) ) {
            return $this->fxWarning("Skipped due to --dry-run");
        }

        // the new dir must exist and have some files in it
        if( !is_dir($this->outDir) || !(new \FilesystemIterator($this->outDir))->valid() ) {
            throw new \Exception("##" . $this->outDir . "## must exist and have some XML in it!");
        }

        if( is_dir($this->outDirFinal) ) {

            $this->filesystem->remove($this->outDirFinal);
            $this->fxOK("The old folder ##" . $this->outDirFinal . "## was deleted successfully");
        }

        $this->filesystem->rename($this->outDir, $this->outDirFinal);
        $this->fxOK("The new folder was moved to it's final destination: ##" . $this->outDirFinal . "##");

        return $this;
    }
}
