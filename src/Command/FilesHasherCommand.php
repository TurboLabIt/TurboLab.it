<?php
namespace App\Command;

use App\Service\Entity\File as FileEntity;
use App\Service\Cms\FileEditor;
use App\ServiceCollection\Cms\FileEditorCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;


/**
 * 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/files-articles.md
 */
#[AsCommand(name: 'FilesHasher')]
class FilesHasherCommand extends AbstractBaseCommand
{
    protected bool $allowDryRunOpt  = true;
    protected array $arrHashedFiles = [];

    public function __construct(protected FileEditorCollection $files, protected EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        parent::execute($input, $output);

        $this->fxTitle("🚚 Loading local files...");
        $this->files->loadAll();
        $count = $this->files->count();
        $this->fxOK("##$count## file(s) loaded");

        if($count == 0) { return $this->endWithSuccess(); }

        $this->fxTitle("#️⃣ Hashing...");
        $this->processItems($this->files, [$this, 'hashOneFile'], null, [$this, 'buildItemTitle']);

        $this->fxTitle("💾 Persisting....");
        if( $this->isNotDryRun() ) {
            $this->entityManager->flush();
        }

        $this->fxTitle("📊 Hashed file(s)...");
        if( !empty($this->arrHashedFiles) ) {

            (new Table($output))
                ->setHeaders(['ID', 'Title'])
                ->setRows($this->arrHashedFiles)
                ->render();
        }

        $hashedFilesNum = count($this->arrHashedFiles);
        $this->fxOK("##$hashedFilesNum## files(s) hashed");

        $this->io->newLine();

        return $this->endWithSuccess();
    }


    protected function buildItemTitle($key, $item) : string
    {
        return '[' . $item->getId() . '] ' . $item->getTitle();
    }


    protected function hashOneFile($key, FileEditor $file) : static
    {
        $oldHash    = $file->getHash();
        $url        = $file->getEntity()->getUrl();
        $path       = $file->getOriginalFilePath();

        if( !empty($url) ) {

            $newHash = md5($url);

        } elseif( !is_readable($path) ) {

            return $this->fxWarning("File ##" . $file->getId() . "## (" . $file->getTitle() . ") not found in ##$path##");

        } else {

            $newHash = md5_file($path);
        }

        if( $oldHash == $newHash ) {
            return $this;
        }

        $file->setHash($newHash);

        $this->arrHashedFiles[] = [
            'id'    => $file->getId(),
            'title' => $file->getTitle(),
        ];

        return $this;
    }
}
