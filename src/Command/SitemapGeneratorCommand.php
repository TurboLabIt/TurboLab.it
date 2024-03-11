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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        protected ArticleCollection $articleCollection, protected TagCollection $tagCollection,
        protected TopicCollection $topicCollection,
        protected Environment $twig, protected ProjectDir $projectDir,
        protected Filesystem $filesystem,
        protected ForumUrlGenerator $forumUrlGenerator, protected UrlGeneratorInterface $symfonyUrlGenerator
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

        // site
        $this
            ->loadArticles()
            ->addHomePage()
            ->addArticles()
            ->addTags();

        // forum
        $this
            ->addForumIndexes()
            ->addForumTopics();

        //
        $this
            ->writeXMLs()
            ->writeIndex();

        $this
            ->fxTitle("Move new directory to final, public path...")
            ->moveDirectory();

        $this->fxTitle("The sitemap is ready!");
        $finalUrlSitemapIndex = $this->symfonyUrlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'sitemap/sitemap.xml';
        $this->fxOK("\t 👉 $finalUrlSitemapIndex 👈");

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


    public function loadArticles() :static
    {
        $this->fxTitle("Selecting Articles...");
        $this->articleCollection->loadAllPublished();
        $countArticles =  $this->articleCollection->count();
        return $this->fxOK( number_format($countArticles, 0, ',', '.') . " article(s) loaded");
    }


    protected function addHomePage() : static
    {
        $this->fxTitle("Adding the Home Page...");
        $this->arrSections["site"][0][] = [
            "url" => $this->symfonyUrlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            "lastmod"       => (new \DateTime())->format(DATE_W3C),
            "changefreq"    => 'hourly',
            "priority"      => 0.9
        ];

        $countArticles =  $this->articleCollection->count();

        $this->fxTitle("Adding the Home Page (paginated)...");
        $homePages = ceil($countArticles / Paginator::ITEMS_PER_PAGE);
        $this->fxInfo("There are " . $homePages . " pages");

        if(false) {
            for($i=2; $i<=$homePages; $i++) {
                $this->arrSections["site"][0][] = [
                    "url"           => $this->symfonyUrlGenerator->generate('app_home_paginated', ["page" => $i], UrlGeneratorInterface::ABSOLUTE_URL),
                    "lastmod"       => (new \DateTime())->format(DATE_W3C),
                    "changefreq"    => 'daily'
                ];
            }
        } else {
            $this->fxWarning('Disabled, as per 📚 https://www.searchenginejournal.com/technical-seo/pagination/');
        }

        $this->fxTitle("Adding the News Page...");
        $this->arrSections["site"][0][] = [
            "url" => $this->symfonyUrlGenerator->generate('app_news', [], UrlGeneratorInterface::ABSOLUTE_URL),
            "lastmod"       => (new \DateTime())->format(DATE_W3C),
            "changefreq"    => 'hourly',
            "priority"      => 0.8
        ];

        return $this;
    }


    protected function addArticles() : static
    {
        $this->fxTitle("Adding Articles...");
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


    protected function addForumIndexes() : static
    {
        $this->fxTitle("Adding /forum...");
        $this->arrSections["forum"][0][] = [
            "url"       => $this->forumUrlGenerator->generateHomeUrl(),
            "lastmod"   => (new \DateTime())->format(DATE_W3C),
            "changefreq"=> 'hourly'
        ];

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

                $txtXml = $this->twig->render('sitemap/sitemap.xml.twig', [
                    "Items" => $arrData
                ]);

                $XMLDoc = new \DOMDocument();
                $XMLDoc->preserveWhiteSpace = false;
                $XMLDoc->formatOutput = true;
                $XMLDoc->loadXML($txtXml);
                $txtXml = $XMLDoc->saveXML();

                $fileName   = "{$filename}_$index.xml";
                $filePath   = $this->outDir . $fileName;
                $this->arrFilesForIndex[] = $fileName;

                $countItems = count($arrData);
                $label = "##$filePath## with " . number_format($countItems, 0, ',', '.') . " items";

                if( $this->isNotDryRun(true) ) {

                    file_put_contents($filePath, $txtXml);
                    $this->fxOK($label . " written");

                } else {

                    $this->fxWarning($label . " wasn't written due to --dry-run");
                }
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


    public function writeIndex()
    {
        $this->fxTitle("Building the Sitemap index...");
        $urlBase = $this->symfonyUrlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'sitemap/';

        $arrItems = [];
        foreach($this->arrFilesForIndex as &$fileName) {

            $url = $urlBase . $fileName;
            $this->fxInfo($url);

            $arrItems[] = [
                "url"       => $url,
                "lastmod"   => (new \DateTime())->format(DATE_W3C)
            ];
        }

        $txtXml = $this->twig->render('sitemap/index.xml.twig', [
            "Items" => $arrItems
        ]);

        $XMLDoc = new \DOMDocument();
        $XMLDoc->preserveWhiteSpace = false;
        $XMLDoc->formatOutput = true;
        $XMLDoc->loadXML($txtXml);
        $txtXml = $XMLDoc->saveXML();

        $filePath = $this->outDir . "sitemap.xml";

        if( $this->isNotDryRun(true) ) {

            file_put_contents($filePath, $txtXml);
            $this->fxOK("##$filePath## written");

        } else {

            $this->fxWarning("##$filePath## wasn't written due to --dry-run");
        }

        return $this;
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
