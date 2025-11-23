<?php
namespace App\Service\Cms;

use App\Entity\Cms\File as FileEntity;
use App\Entity\Cms\FileAuthor;
use App\Service\Factory;
use App\Service\TextProcessor;
use App\Service\User;
use App\Trait\SaveableTrait;
use App\Trait\UploadableFileTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;


class FileEditor extends File
{
    use UploadableFileTrait, SaveableTrait { save as protected traitSave; }

    protected ?string $previousFilePath = null;


    public function __construct(Factory $factory, protected TextProcessor $textProcessor)
    {
        parent::__construct($factory);
    }


    public function createFromUploadedFile(UploadedFile $file) : FileEditor
    {
        // general validation (from UploadableFileTrait)
        $this->validateUploadedFile($file);

        $originalFilename           = $file->getClientOriginalName();
        $filenameWithoutExtension   = pathinfo($originalFilename, PATHINFO_FILENAME);


        $extension = $file->guessExtension();
        // some file types, such as .ps1, have no "official" mime-type, thus are recognized as "text/plain" =>
        // this would screw the extension ==> falling back to client-provided extension
        if( empty($extension) || $extension == 'txt' ) {
            $extension = $file->getClientOriginalExtension();
        }


        $hash = hash_file('md5', $file->getPathname() );

        $this
            ->setTitle($filenameWithoutExtension)
            ->entity
                ->setFormat($extension)
                ->setHash($hash);

        $this->save();

        $destinationFullPath = $this->getOriginalFilePath();

        $file->move( dirname($destinationFullPath), basename($destinationFullPath) );

        return $this;
    }


    public function setAutoHash() : static
    {
        $localFilePath = $this->previousFilePath ?? $this->getOriginalFilePath();

        $hash = $this->isLocal() ? hash_file('md5', $localFilePath) : md5( $this->getUrl() );

        $this->entity->setHash($hash);
        return $this;
    }


    public function addAuthor(User $author) : static
    {
        $this->entity->addAuthor(
            (new FileAuthor())
                ->setUser( $author->getEntity() )
        );

        return $this;
    }


    public function delete(bool $persist = true) : void
    {
        // TODO FileEditor::delete()
        foreach(static::SIZES as $size) {

            $filePath = $this->getBuiltFilePath($size, false);
            if( file_exists($filePath) ) {
                unlink($filePath);
            }
        }


        $filePath = $this->getOriginalFilePath();
        unlink($filePath);


        $em = $this->factory->getEntityManager();
        $em->remove($this->entity);

        if($persist) {
            $em->flush();
        }

        $this->clear();
    }


    public function setTitle(string $newTitle) : static
    {
        $cleanTitle = $this->textProcessor->processRawInputTitleForStorage($newTitle);
        $this->entity->setTitle($cleanTitle);
        return $this;
    }


    public function setHash(string $hash) : static
    {
        $this->entity->setHash($hash);
        return $this;
    }


    public function setFormat(string $newFormat) : static
    {
        $cleanFormat = $this->textProcessor->processRawInputTitleForStorage($newFormat);
        $cleanFormat = mb_strtolower($cleanFormat);
        $this->entity->setFormat($cleanFormat);
        return $this;
    }


    public function setExternalDownloadUrl(?string $url) : static
    {
        $this->entity->setUrl($url);
        return $this;
    }


    public function clear() : static
    {
        $this->previousFilePath = null;
        return parent::clear();
    }


    public function setEntity(?FileEntity $entity = null): static
    {
        parent::setEntity($entity);
        $this->previousFilePath = empty( $entity->getId() ) ? null : $this->getOriginalFilePath();
        return $this;
    }


    public function save(bool $persist = true) : static
    {
        $this->traitSave($persist);

        if( $this->isExternal() ) {
            return $this;
        }

        $previousFilePath   = $this->previousFilePath;
        $currentFilePath    = $this->getOriginalFilePath();

        if( !empty($previousFilePath) && $previousFilePath != $currentFilePath ) {

            rename($previousFilePath, $currentFilePath);
            $this->previousFilePath = null;
        }

        return $this;
    }
}
