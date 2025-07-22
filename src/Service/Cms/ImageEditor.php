<?php
namespace App\Service\Cms;

use App\Service\User;
use Imagine\Exception\NotSupportedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;


class ImageEditor extends Image
{
    protected UploadedFile $file;

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

    public function createFromFilePath(UploadedFile $file, User $author, ?string $hash = null) : ImageEditor
    {
        if( !str_starts_with($file->getMimeType(), 'image') ) {
            throw new NotSupportedException('The MIME is not image/*');
        }

        $hash = $hash ?: hash_file('md5', $file->getPathname() );

        $this->entity
            ->setTitle( $file->getClientOriginalName() )
            ->setFormat( $file->guessExtension() )
            ->setHash($hash)
            ->addAuthor($author);

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
