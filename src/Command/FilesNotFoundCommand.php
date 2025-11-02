<?php
namespace App\Command;

use App\Repository\Cms\ArticleFileRepository;
use App\Service\Cms\File;
use App\Service\Entity\Article as ArticleEntity;
use App\Service\Entity\File as FileEntity;
use App\Service\HtmlProcessorForDisplay;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\FileCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use TurboLabIt\BaseCommand\Service\ProjectDir;


#[AsCommand(name: 'FilesNotFound', description: 'Check files availability')]
class FilesNotFoundCommand extends AbstractBaseCommand
{
    protected string $filesUploadDir                = '';
    protected array $arrFilesOnFilesystem           = [];
    protected array $arrMissingOnFilesystem         = [];
    protected string $filePathMissingOnFilesystem   = '';
    protected string $filePathMissingOnDatabase     = '';


    public function __construct(
        protected ArticleCollection $articles, protected FileCollection $files,
        protected ArticleFileRepository $articleFileRepository, protected HtmlProcessorForDisplay $htmlProcessor,
        protected EntityManagerInterface $entityManager, protected ProjectDir $projectDir,
        protected UrlGeneratorInterface $urlGenerator
    )
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        parent::execute($input, $output);

        $this
            ->fxTitle("🚚 Loading files from database...")
            ->files->loadAll();

        $filesNum = $this->files->count();
        $this->fxOK("##$filesNum## file(s) loaded");


        $this->fxTitle('🔍 Scanning the file system...');
        $this->filesUploadDir = $this->projectDir->getVarDir(File::UPLOADED_FILES_FOLDER_NAME);
        $this
            ->fxInfo($this->filesUploadDir)
            ->scanFilesFolder();

        $scanFilesNum = count($this->arrFilesOnFilesystem);
        $this->fxOK("##$scanFilesNum## file(s) found on disk");


        $this->fxTitle("🔬 Matching each database entity with its file....");
        $this->processItems($this->files, [$this, 'matchEntitiesToFilesystem']);

        $missingOnFilesystemNum = count($this->arrMissingOnFilesystem);
        $this->fxWarning("##$missingOnFilesystemNum## file(s) missing on disk");

        $this->fxTitle("📄 Storing missing file to json....");
        $this->filePathMissingOnFilesystem = $this->projectDir->getVarDirFromFilePath(File::MISSING_ON_FILESYSTEM_FILE_NAME);
        $this
            ->storeMissingOnFilesystem()
            ->fxOK('OK! ##' . $this->filePathMissingOnFilesystem . '##');


        $this->fxTitle("🔬 Matching each file with its database entity....");
        $this->processItems($this->articles, [$this, 'matchFilesystemToEntities']);

        $this->fxTitle("📄 Storing missing file to json....");
        $this->filePathMissingOnDatabase = $this->projectDir->getVarDirFromFilePath(File::MISSING_ON_DATABASE_FILE_NAME);
        $this
            ->storeMissingOnDatabase()
            ->fxOK('OK! ##' . $this->filePathMissingOnDatabase . '##');


        $this
            ->fxTitle("Web URL")
            ->fxOK( $this->urlGenerator->generate('app_file_need-fixing', [], UrlGeneratorInterface::ABSOLUTE_URL) );

        return $this->endWithSuccess();
    }


    protected function buildItemTitle($key, $item) : string
    {
        if( is_array($item) ) {
            return '[' . $item['articleId'] . ']';
        }

        $title = $item->getTitle();
        if( mb_strlen($title) > 60 ) {
            $title = mb_substr($title, 0, 60) . '...';
        }

        $title = str_ireplace(['<', '>'], '', $title);

        return '[' . $item->getId() . '] ' . $title;
    }


    protected function scanFilesFolder() : static
    {
        $this->arrFilesOnFilesystem =
            array_filter(
                array_map( fn($item) => $this->filesUploadDir . $item,  scandir($this->filesUploadDir) ),
                fn($path) => is_file($path)
            );

        //reindex
        $this->arrFilesOnFilesystem = array_values($this->arrFilesOnFilesystem);

        return $this;
    }


    protected function matchEntitiesToFilesystem($key, File $file) : static
    {
        $filePath = $file->getOriginalFilePath();

        if( !in_array($filePath, $this->arrFilesOnFilesystem) ) {

            $fileId = $file->getId();
            $this->arrMissingOnFilesystem[$fileId] = $file;
        }

        return $this;
    }


    protected function storeMissingOnFilesystem() : static
    {
        usort($this->arrMissingOnFilesystem, function(File $file1, File $file2) {
            return $file2->getUpdatedAt() <=>  $file1->getUpdatedAt();
        });

        $arrDataSource = [];
        foreach($this->arrMissingOnFilesystem as $file) {

            $fileId = (string)$file->getId();
            $arrDataSource[$fileId] = [
                'title'     => $file->getTitle(),
                'url'       => $file->getUrl(),
                'Authors'   => [],
                'Articles'  => [],
            ];

            $arrAuthors = $file->getAuthors();
            foreach($arrAuthors as $author) {

                $authorId = $author->getId();
                $arrDataSource[$fileId]['Authors'][$authorId] = [
                    'username'  => $author->getUsername(),
                    'url'       => $author->getUrl(),
                ];
            }

            $arrArticles = $file->getArticles();
            foreach($arrArticles as $article) {

                $articleId = $article->getId();
                $arrDataSource[$fileId]['Articles'][$articleId] = [
                    'title' => $article->getTitle(),
                    'url'   => $article->getUrl()
                ];

                $arrAuthors = $article->getAuthors();
                foreach($arrAuthors as $author) {

                    $authorId = $author->getId();
                    $arrDataSource[$fileId]['Authors'][$authorId] = [
                        'username'  => $author->getUsername(),
                        'url'       => $author->getUrl(),
                    ];
                }
            }
        }


        file_put_contents($this->filePathMissingOnFilesystem, json_encode($arrDataSource, JSON_PRETTY_PRINT) );

        return $this;
    }


    protected function matchFilesystemToEntities() : static
    {
        return $this;
    }


    protected function storeMissingOnDatabase() : static
    {
        return $this;
    }
}
