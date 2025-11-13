<?php
namespace App\Service\Cms;

use App\Entity\Cms\Image as ImageEntity;
use App\Entity\Cms\ImageAuthor;
use App\Service\Factory;
use App\Service\TextProcessor;
use App\Service\User;
use App\Trait\SaveableTrait;
use App\Trait\UploadableFileTrait;
use Imagine\Exception\NotSupportedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;


class ImageEditor extends Image
{
    use SaveableTrait, UploadableFileTrait;


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


    public function createFromUploadedFile(UploadedFile $file) : ImageEditor
    {
        if( !str_starts_with($file->getMimeType(), 'image') ) {
            throw new NotSupportedException('The MIME is not image/*');
        }

        // general validation (from UploadableFileTrait)
        $this->validateUploadedFile($file);

        $hash = hash_file('md5', $file->getPathname() );

        // handle "no-watermark" in filename
        $filename = preg_replace('/no[-\._]?watermark/i', '', $file->getClientOriginalName(), -1, $count);
        $filename = trim($filename);
        $noWatermark = $count > 0;
        if( $noWatermark || stripos($filename, 'logo') !== false || stripos($filename, 'spotlight') !== false ) {
            $this->entity->setWatermarkPosition(ImageEntity::WATERMARK_DISABLED);
        }

        if( empty($filename) ) {
            $filename = 'image-' . (new \DateTime())->format('Y-m-d H:i:s') . '-' . rand(0, PHP_INT_MAX);
        }

        $this
            ->setTitle($filename)
            ->entity
                ->setFormat( $file->guessExtension() )
                ->setHash($hash);

        $this->save();

        $destinationFullPath = $this->getOriginalFilePath();

        $file->move( dirname($destinationFullPath), basename($destinationFullPath) );

        return $this;
    }


    public function addAuthor(User $author) : static
    {
        $this->entity->addAuthor(
            (new ImageAuthor())
                ->setUser( $author->getEntity() )
        );

        return $this;
    }


    public function delete(bool $persist = true) : void
    {
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
}
