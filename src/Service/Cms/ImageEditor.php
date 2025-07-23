<?php
namespace App\Service\Cms;

use App\Entity\Cms\ArticleImage;
use App\Entity\Cms\ImageAuthor;
use App\Service\Factory;
use App\Service\TextProcessor;
use App\Service\User;
use Imagine\Exception\NotSupportedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;


class ImageEditor extends Image
{
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

    /*
    public function loadOrCreateFromUploadedFile(UploadedFile $uploadedFile) : ImageEditor
    {
        if( !str_starts_with($uploadedFile->getMimeType(), 'image') ) {
            throw new NotSupportedException('The MIME is not image/*');
        }

        $fileHash =

        $image = $this->loadByHash();
    }
*/

    public function createFromUploadedFile(UploadedFile $file) : ImageEditor
    {
        if( !str_starts_with($file->getMimeType(), 'image') ) {
            throw new NotSupportedException('The MIME is not image/*');
        }

        $hash = hash_file('md5', $file->getPathname() );

        $this
            ->setTitle( $file->getClientOriginalName() )
            ->entity
                ->setFormat( $file->guessExtension() )
                ->setHash($hash);

        $this->save();

        $destinationFullPath = $this->getOriginalFilePath();

        $file->move( dirname($destinationFullPath), basename($destinationFullPath) );

        return $this;
    }


    public function rehash() : static
    {
        $hash = hash_file('md5', $this->getOriginalFilePath() );
        $this->entity->setHash($hash);
        return $this;
    }


    public function addAuthor(User $author) : static
    {
        $this->entity->addAuthor(
            (new ImageAuthor())
                ->setImage($this->entity)
                ->setUser( $author->getEntity() )
        );

        return $this;
    }


    //<editor-fold defaultstate="collapsed" desc="*** 💾 Save ***">
    public function save(bool $persist = true) : static
    {
        if($persist) {

            $this->factory->getEntityManager()->persist($this->entity);
            $this->factory->getEntityManager()->flush();
        }

        return $this;
    }
    //</editor-fold>
}
