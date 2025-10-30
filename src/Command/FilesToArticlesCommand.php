<?php
namespace App\Command;

use App\Entity\Cms\ArticleFile;
use App\Repository\Cms\ArticleFileRepository;
use App\Repository\Cms\FileRepository;
use App\Service\Cms\Article;
use App\Service\Cms\File;
use App\Service\Entity\Article as ArticleEntity;
use App\Service\Entity\File as FileEntity;
use App\Service\HtmlProcessorForDisplay;
use App\Service\User;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\FileCollection;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;


/**
 * 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/files-articles.md
 */
#[AsCommand(
    name: 'FilesToArticles',
    description: 'Attach (via ArticleFile junction) each file to the article(s) using it, remove unused junctions'
)]
class FilesToArticlesCommand extends AbstractBaseCommand
{
    protected bool $allowDryRunOpt          = true;
    protected array $arrArticleMap          = [];
    protected array $arrDeletedJunctions    = [];
    protected array $arrNewJunctions        = [];
    protected array $arrFilesNotFound       = [];


    public function __construct(
        protected ArticleCollection $articles, protected FileCollection $files,
        protected ArticleFileRepository $articleFileRepository, protected HtmlProcessorForDisplay $htmlProcessor,
        protected EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        parent::execute($input, $output);

        $this
            ->fxTitle("🚚 Loading articles...")
            ->articles->loadAll();

        $articlesNum = $this->articles->count();
        $this->fxOK("##$articlesNum## articles(s) loaded");

        $this->fxTitle("🔬 Scanning each article text to determine which files it actually uses....");
        $this->processItems($this->articles, [$this, 'scanOneArticle']);


        $junctions =
            $this
                ->fxTitle("🚚 Loading file-article junctions...")
                ->articleFileRepository->getAllComplete();

        $junctionsNum = count($junctions);
        $this->fxOK("##$junctionsNum## junctions(s) loaded");


        $this
            ->fxTitle("🚚 Loading files...")
            ->files->loadAll();

        $filesNum = $this->files->count();
        $this->fxOK("##$filesNum## files(s) loaded");


        $this->fxTitle("🚮 Deleting unused junctions....");
        $this->processItems($junctions, [$this, 'deleteOneJunctionIfUnused']);


        $this->fxTitle("🆕 Creating new junctions....");
        $this->processItems($this->arrArticleMap, [$this, 'createNewJunction']);


        $this->fxTitle("💾 Persisting....");
        if( $this->isNotDryRun() ) {
            $this->entityManager->flush();
        }


        $this->fxTitle("📊 Deleted junction(s)...");
        if( !empty($this->arrDeletedJunctions) ) {

            usort($this->arrDeletedJunctions, function(array $arr1, array $arr2) {
                return $arr1["articleId"] <=> $arr2["articleId"];
            });

            $arrDeletedJunctionsForReport =
                array_map(
                    fn($item) => array_intersect_key($item, array_flip(['articleUrl', 'fileUrl', 'authors'])), $this->arrDeletedJunctions
                );

            (new Table($output))
                ->setHeaders(['Art. URL', 'File URL', 'Author(s)'])
                ->setRows($arrDeletedJunctionsForReport)
                ->render();
        }

        $deletedJunctionsNum = count($this->arrDeletedJunctions);
        $this->fxOK("##$deletedJunctionsNum## junctions(s) deleted");


        $this->fxTitle("📊 Created junction(s)...");
        if( !empty($this->arrNewJunctions) ) {

            usort($this->arrNewJunctions, function(array $arr1, array $arr2) {
                return $arr1["articleId"] <=> $arr2["articleId"];
            });

            $arrCreatedJunctionsForReport =
                array_map(
                    fn($item) => array_intersect_key($item, array_flip(['articleUrl', 'fileUrl'])), $this->arrNewJunctions
                );

            (new Table($output))
                ->setHeaders(['Art. URL', 'File URL'])
                ->setRows($arrCreatedJunctionsForReport)
                ->render();
        }

        $createdJunctionsNum = count($this->arrNewJunctions);
        $this->fxOK("##$createdJunctionsNum## junctions(s) created");


        $this->fxTitle("⚠️ Files(s) not found...");
        if( !empty($this->arrFilesNotFound) ) {

            usort($this->arrFilesNotFound, function(array $arr1, array $arr2) {
                return $arr1["fileId"] <=> $arr2["fileId"];
            });

            $arrFilesNotFoundForReport =
                array_map(
                    fn($item) => array_intersect_key($item, array_flip(['articleUrl', 'fileId'])), $this->arrFilesNotFound
                );

            (new Table($output))
                ->setHeaders(['File ID', 'Art. URL'])
                ->setRows($arrFilesNotFoundForReport)
                ->render();
        }

        $createdJunctionsNum = count($this->arrFilesNotFound);
        $this->fxOK("##$createdJunctionsNum## files(s) not found");


        $this->io->newLine();

        return $this->endWithSuccess();
    }


    protected function buildItemTitle($key, $item) : string
    {
        if( is_array($item) ) {
            return '[' . $item['articleId'] . ']';
        }

        return '[' . $item->getId() . '] ' . $item->getTitle();
    }


    protected function scanOneArticle($key, Article $article) : static
    {
        $articleId  = $article->getId();
        $articleUrl = $article->getShortUrl();

        $this->arrArticleMap[$articleId] = [
            'articleId' => $articleId,
            'Files'    => []
        ];

        $text = $article->getEntity()->getBody();

        if( empty($text) ) {
            return $this;
        }

        $domDoc = $this->htmlProcessor->parseHTML($text);
        if( $domDoc === false ) {
            $this->endWithError("Parsing of article ## $articleUrl ## failed");
        }

        $aNodes = $domDoc->getElementsByTagName('a');
        if( $aNodes->length == 0 ) {
            return $this;
        }

        foreach($aNodes as $a) {

            $arrMatches = [];
            $src        = $a->getAttribute('href');
            $found      = preg_match(HtmlProcessorForDisplay::REGEX_FILE_PLACEHOLDER, $src, $arrMatches);

            if( !$found ) {
                $found = preg_match(HtmlProcessorForDisplay::REGEX_FILE_URL, $src, $arrMatches);
            }

            if( !$found ) {
                continue;
            }

            $this->arrArticleMap[$articleId]['Files'][] = (int)$arrMatches[0];
        }

        $this->arrArticleMap[$articleId]['Files'] = array_unique($this->arrArticleMap[$articleId]['Files']);

        return $this;
    }


    protected function deleteOneJunctionIfUnused($key, ArticleFile $junction) : static
    {
        $article    = $junction->getArticle();
        $articleId  = $article->getId();
        $fileId     = $junction->getFile()->getId();


        if( $articleId == Article::ID_ABOUT_US && in_array($fileId, [File::ID_LOGO]) ) {

            // special case! these files are attached on the "about us" article, but not used
            return $this->removeFromArticleMap($articleId, $fileId);
        }


        $dateCutoff = (new DateTime())->modify('-' . FileRepository::ORPHANS_AFTER_MONTHS . ' months');
        if( $junction->getUpdatedAt() > $dateCutoff ||  $article->getUpdatedAt() > $dateCutoff ) {

            // don't delete "too new" assoc
            return $this->removeFromArticleMap($articleId, $fileId);
        }


        $articleFiles = $this->arrArticleMap[$articleId]['Files'] ?? [];
        $key = array_search($fileId, $articleFiles);

        if( $key !== false ) {

            // it's already attached
            return $this->removeFromArticleMap($articleId, $fileId);
        }

        /** @var Article $articleService */
        $articleService = $this->articles->get($articleId);

        // it's attached, but not linked
        $junctionId = $junction->getId();
        $this->arrDeletedJunctions[$junctionId] = [
            'articleId'     => $articleId,
            'fileId'        => $fileId,
            'articleUrl'    => $articleService->getShortUrl(),
            'fileUrl'       => $this->files->get($fileId)->getUrl(),
            'authors'       => ''
        ];

        /** @var User $author */
        foreach($articleService->getAuthors() as $author) {

            $this->arrDeletedJunctions[$junctionId]['authors'] .=
                !empty($this->arrDeletedJunctions[$junctionId]['authors']) ? ', ' : '';

            $this->arrDeletedJunctions[$junctionId]['authors'] .= $author->getUsername();
        }

        // temporary disabled https://github.com/TurboLabIt/TurboLab.it/issues/101
        //$this->articleFileRepository->delete($junction, false);

        // fail-safe - this shouldn't happen (the file is not linked)
        return $this->removeFromArticleMap($articleId, $fileId);
    }


    protected function removeFromArticleMap(int $articleId, int $fileId) : static
    {
        $articleFiles = $this->arrArticleMap[$articleId]['Files'] ?? [];
        $key = array_search($fileId, $articleFiles);

        if( $key !== false ) {
            unset($this->arrArticleMap[$articleId]['Files'][$key]);
        }

        return $this;
    }


    protected function createNewJunction($key, array $arrMapItem) : static
    {
        if( empty($arrMapItem["Files"]) ) {
            return $this;
        }

        $articleId  = $arrMapItem['articleId'];
        $article    = $this->articles->get($articleId);

        foreach($arrMapItem["Files"] as $fileId) {

            $file = $this->files->get($fileId);

            $logEntry = [
                'articleId'     => $articleId,
                'fileId'        => $fileId,
                'articleUrl'    => $article->getShortUrl(),
                'fileUrl'       => $file?->getUrl()
            ];

            if( empty($file) ) {

                $this->arrFilesNotFound[] = $logEntry;
                continue;
            }

            $this->arrNewJunctions[] = $logEntry;

            $newJunction =
                (new ArticleFile())
                    ->setArticle($article->getEntity())
                    ->setFile($file->getEntity())
                    ->setRanking(0);

            $this->articleFileRepository->save($newJunction, false);
        }

        return $this;
    }
}
