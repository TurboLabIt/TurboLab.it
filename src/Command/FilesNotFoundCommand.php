<?php
namespace App\Command;

use App\Repository\Cms\ArticleFileRepository;
use App\Service\Cms\File;
use App\Service\Entity\Article as ArticleEntity;
use App\Service\Entity\File as FileEntity;
use App\Service\HtmlProcessorForDisplay;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\FileCollection;
use App\Trait\CommandTrait;
use DateTime;
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
    protected array $arrFilesByPath                 = [];
    protected array $arrMissingOnFilesystem         = [];
    protected array $arrMissingOnDatabase           = [];
    protected string $filePathMissingOnFilesystem   = '';
    protected string $filePathMissingOnDatabase     = '';

    use CommandTrait;


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

        $this->loadAllFiles();

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


        $this
            ->fxTitle("🗺️ Indexing database entities by file path....")
            ->indexFilesByPath();

        $this->fxTitle("🔬 Matching each file with its database entity....");
        $this->processItems($this->arrFilesOnFilesystem, [$this, 'matchFilesystemToEntities']);

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
        if( !$file->isLocal() ) {
            return $this;
        }

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


    protected function indexFilesByPath() : static
    {
        foreach($this->files as $file) {

            $filePath = $file->getOriginalFilePath();
            $this->arrFilesByPath[$filePath] = null;
        }

        return $this;
    }


    protected function matchFilesystemToEntities($key, string $filepath) : static
    {
        if( !array_key_exists($filepath, $this->arrFilesByPath) ) {
            $this->arrMissingOnDatabase[] = $filepath;
        }

        return $this;
    }


    protected function storeMissingOnDatabase() : static
    {
        usort($this->arrMissingOnDatabase, function(string $filepath1, string $filepath2) {
            return filemtime($filepath2) <=>  filemtime($filepath1);
        });

        $arrDataSource = [];
        foreach($this->arrMissingOnDatabase as $filepath) {

            $arrDataSource[] = [
                'filepath'      => $filepath,
                'filename'      => basename($filepath),
                'lastModified'  => DateTime::createFromFormat('U', filemtime($filepath))->format('Y-m-d H:i:s'),
            ];
        }

        file_put_contents($this->filePathMissingOnDatabase, json_encode($arrDataSource, JSON_PRETTY_PRINT) );

        return $this;
    }
}
