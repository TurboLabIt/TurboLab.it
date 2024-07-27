<?php
namespace App\Command;

use App\Entity\PhpBB\Forum;
use App\Service\Cms\Article;
use App\Service\Cms\Paginator;
use App\Service\Cms\Tag;
use App\Service\PhpBB\ForumUrlGenerator;
use App\Service\PhpBB\Topic;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\TagCollection;
use App\ServiceCollection\PhpBB\TopicCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use Twig\Environment;


/**
 * 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/sitemaps.md
 */
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
        protected ArticleCollection $articleCollection, protected TagCollection $tagCollection,
        protected TopicCollection $topicCollection,
        protected Environment $twig, protected ProjectDir $projectDir,
        protected Filesystem $filesystem,
        protected ForumUrlGenerator $forumUrlGenerator, protected UrlGeneratorInterface $symfonyUrlGenerator,
        protected ParameterBagInterface $parameters
    )
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this
            ->generateOutputFolders();

        // site
        $this
            ->addHomePage()
            ->addNews()
            ->addTags()
            ->addArticles();

        // forum
        $this
            ->addForumIndexes()
            ->addForumTopics();

        //
        $this
            ->writeXMLs()
            ->writeIndex();


        $this->fxTitle("Listing...");
        $this->fxListFiles($this->outDir);

        $this->moveDirectory();

        $this->fxTitle("The sitemap is ready!");
        $finalUrlSitemapIndex = $this->symfonyUrlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'sitemap/sitemap.xml';

        (new Table($this->output))
            ->setHeaders(['Version', 'Available', 'URL'])
            ->setRows([
                ['🗜 Compressed', "✅", $finalUrlSitemapIndex . ".gz"],
                ['🐡 Uncompressed', $this->isNotProd() ? "✅" : "❌", $finalUrlSitemapIndex]
            ])->render();

        // ping the search engines is no longer relevant 📚 https://developers.google.com/search/blog/2023/06/sitemaps-lastmod-ping

        return $this->endWithSuccess();
    }


    protected function generateOutputFolders() : static
    {
        $this->fxTitle("Creating folders...");

        $this->outDir = $this->projectDir->getVarDir(static::VAR_PATH_TEMP);
        if( is_dir($this->outDir) ) {
            $this->filesystem->remove($this->outDir);
        }

        $this->projectDir->createVarDir(static::VAR_PATH_TEMP);
        $this->fxOK();

        $this->outDirFinal = $this->projectDir->getVarDir(static::VAR_PATH);
        $this->fxOK();

        $this->output->writeln('');

        (new Table($this->output))
            ->setHeaders(['Purpose', 'Path', 'Result'])
            ->setRows([
                ['Temporary', $this->outDir, 'OK'],
                ['Final', $this->outDirFinal, 'OK']
            ])->render();

        return $this;
    }


    protected function addHomePage() : static
    {
        $this->fxTitle("Adding the Home Page...");
        $url = $this->symfonyUrlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $url = trim($url, "/");
        $this->arrSections["site"][0][] = [
            "url"           => $url,
            "lastmod"       => (new \DateTime())->format(DATE_W3C),
            "changefreq"    => 'hourly',
            "priority"      => 0.9
        ];

        return $this->fxOK("##$url## added");
    }


    protected function addNews() : static
    {
        $this->fxTitle("Adding the News Page...");
        $url = $this->symfonyUrlGenerator->generate('app_news', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->arrSections["site"][0][] = [
            "url"           => $url,
            "lastmod"       => (new \DateTime())->format(DATE_W3C),
            "changefreq"    => 'hourly',
            "priority"      => 0.8
        ];

        return $this->fxOK("##$url## added");
    }


    protected function addTags() : static
    {
        $this->fxTitle("Adding Tags...");
        $this->tagCollection->loadAll();
        $countTags =  $this->tagCollection->count();
        $this->fxOK( number_format($countTags, 0, ',', '.') . " tag(s) loaded");

        $lastSiteFileIndex  = array_key_last( $this->arrSections["site"] );
        $currentFileItem    = count($this->arrSections["site"][$lastSiteFileIndex]);
        $currentFileIndex   = $lastSiteFileIndex;

        $oNow = new \DateTime();

        /** @var Tag $tag */
        foreach($this->tagCollection as $tag) {

            if( $currentFileItem == static::URL_LIMIT_PER_FILE ) {

                $currentFileIndex++;
                $currentFileItem = 0;
            }

            $this->arrSections["site"][$currentFileIndex][] = [
                "url"       => $tag->getUrl(),
                "changefreq"=> 'weekly'
            ];

            $currentFileItem++;
        }

        return $this;
    }


    protected function addArticles() : static
    {
        $this->fxTitle("Adding Articles...");
        $this->articleCollection->loadAllPublished();
        $countArticles =  $this->articleCollection->count();
        $this->fxOK( number_format($countArticles, 0, ',', '.') . " article(s) loaded");

        $currentFileItem    = count($this->arrSections["site"][0]);
        $currentFileIndex   = 0;

        $oNow = new \DateTime();

       /** @var Article $article */
        foreach($this->articleCollection as $article) {

            if( $currentFileItem == static::URL_LIMIT_PER_FILE ) {

                $currentFileIndex++;
                $currentFileItem = 0;
            }

            $oDateTime = $article->getUpdatedAt();
            if( $oDateTime->format('U') > $oNow->format('U') ) {
                $oDateTime = $oNow;
            }

            $this->arrSections["site"][$currentFileIndex][] = [
                "url"       => $article->getUrl(),
                "lastmod"   => $oDateTime->format(DATE_W3C),
                "changefreq"=> $this->buildScanFrequencyFromDateTime($oDateTime)
            ];

            $currentFileItem++;
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


    protected function addForumIndexes() : static
    {
        $this->fxTitle("Adding /forum...");
        $url = $this->forumUrlGenerator->generateHomeUrl();
        $this->arrSections["forum"][0][] = [
            "url"       => $url,
            "lastmod"   => (new \DateTime())->format(DATE_W3C),
            "changefreq"=> 'hourly'
        ];

        $this->fxOK("##$url## added");

        $this->fxTitle("Adding Forums...");
        $arrForums = $this->entityManager->getRepository(Forum::class)->findAll();
        $countForums = count($arrForums);
        $this->fxOK("$countForums forum(s) loaded");

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
        $this->fxTitle("Adding forum Topics...");
        $this->topicCollection->loadAll();
        $countTopics =  $this->topicCollection->count();
        $this->fxOK( number_format($countTopics, 0, ',', '.') . " topic(s) loaded");

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


    protected function writeXMLs() : static
    {
        $this->fxTitle("Building the XMLs...");
        foreach($this->arrSections as $filename => $arrSection) {

            foreach($arrSection as $index => $arrData) {

                $countItems = number_format(count($arrData), 0, ',', '.');

                $txtXml = $this->twig->render('sitemap/sitemap.xml.twig', [
                    "Items" => $arrData
                ]);

                $XMLDoc = new \DOMDocument();
                $XMLDoc->preserveWhiteSpace = false;
                $XMLDoc->formatOutput = true;
                $XMLDoc->loadXML($txtXml);
                $txtXml = $XMLDoc->saveXML();

                // uncompressed file (non-prod only)
                $fileName   = "{$filename}_$index.xml";
                $filePath   = $this->outDir . $fileName;
                $cliMessage = "##$filePath## with $countItems item(s)";
                if( $this->isProd() ) {

                    $this->fxInfo("🐡 $cliMessage (uncompressed) is NOT stored on prod");

                } else {

                    file_put_contents($filePath, $txtXml);
                    $this->fxOK("🐡 $cliMessage (uncompressed) stored (non-prod only)");
                }

                // compressed file
                $fileName   .= ".gz";
                $filePath   = $this->outDir . $fileName;
                file_put_contents("compress.zlib://$filePath", $txtXml);
                $this->fxOK("🗜️ ##$filePath## (compressed) stored");

                $this->arrFilesForIndex[] = $fileName;

                $this->output->writeln('');
            }
        }

        return $this;
    }


    public function writeIndex()
    {
        $this->fxTitle("Building the Sitemap index...");
        $urlBase = $this->symfonyUrlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'sitemap/';

        $arrItems = [];
        foreach($this->arrFilesForIndex as $fileName) {

            $url = $urlBase . $fileName;
            $this->fxInfo($url);

            $arrItems[] = [
                "url"       => $url,
                "lastmod"   => (new \DateTime())->format(DATE_W3C)
            ];
        }

        $this->output->writeln('');

        $countItems = number_format(count($arrItems), 0, ',', '.');

        $txtXml = $this->twig->render('sitemap/index.xml.twig', [
            "Items" => $arrItems
        ]);

        $XMLDoc = new \DOMDocument();
        $XMLDoc->preserveWhiteSpace = false;
        $XMLDoc->formatOutput = true;
        $XMLDoc->loadXML($txtXml);
        $txtXml = $XMLDoc->saveXML();

        // uncompressed file (non-prod only)
        $filePath   = $this->outDir . "sitemap.xml";
        $cliMessage = "##$filePath## with $countItems item(s)";
        if( $this->isProd() ) {

            $this->fxInfo("🐡 $cliMessage (uncompressed) is NOT stored on prod");

        } else {

            $txtXmlNoGz = str_ireplace('.xml.gz</loc>', '.xml</loc>', $txtXml);
            file_put_contents($filePath, $txtXmlNoGz);
            $this->fxOK("🐡 $cliMessage (uncompressed) stored (non-prod only)");
        }

        // compressed file
        $filePath .= ".gz";
        file_put_contents("compress.zlib://$filePath", $txtXml);
        $this->fxOK("🗜️ ##$filePath## (compressed) stored");

        return $this;
    }


    public function moveDirectory() : static
    {
        $this->fxTitle("Move the new directory to the final, public path...");

        // the new dir must exist and have some files in it
        if( !is_dir($this->outDir) || !(new \FilesystemIterator($this->outDir))->valid() ) {
            throw new \Exception("##" . $this->outDir . "## must exist and have some XML in it!");
        }

        if( $this->isNotDryRun() && is_dir($this->outDirFinal) ) {

            $this->filesystem->remove($this->outDirFinal);
            $this->fxOK("The old folder ##" . $this->outDirFinal . "## was deleted successfully");
        }

        if( $this->isNotDryRun() ) {

            $this->filesystem->rename($this->outDir, $this->outDirFinal);
            $this->fxOK("The new folder was moved to its final destination: ##" . $this->outDirFinal . "##");
        }

        return $this;
    }
}
