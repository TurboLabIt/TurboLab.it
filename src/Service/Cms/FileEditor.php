<?php
namespace App\Service\Cms;

use App\Entity\Cms\FileAuthor;
use App\Service\Factory;
use App\Service\TextProcessor;
use App\Service\User;
use App\Trait\SaveableTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;


class FileEditor extends File
{
    use SaveableTrait;

    protected UploadedFile $file;


    public function __construct(Factory $factory, protected TextProcessor $textProcessor)
    {
        parent::__construct($factory);
    }


    public function setTitle(string $newTitle) : static
    {
        $cleanTitle = $this->textProcessor->processRawInputTitleForStorage($newTitle);
        $this->entity->setTitle($cleanTitle);
        return $this;
    }


    public function createFromUploadedFile(UploadedFile $file) : FileEditor
    {
        if( $file->getSize() == 0 ) {
            throw new UnprocessableEntityHttpException("You cannot upload an empty (zero-bytes) file!");
        }

        $extension = $file->guessExtension();

        if( empty($extension) ) {
            throw new UnprocessableEntityHttpException("The system cannot determine the file extension!");
        }

        $hash = hash_file('md5', $file->getPathname() );

        $originalFilename = $file->getClientOriginalName();
        $filenameWithoutExtension = pathinfo($originalFilename, PATHINFO_FILENAME);

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


    public function setHash(string $hash) : static
    {
        $this->entity->setHash($hash);
        return $this;
    }
}
