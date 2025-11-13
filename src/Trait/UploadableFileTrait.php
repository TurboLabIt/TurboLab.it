<?php
namespace App\Trait;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;


trait UploadableFileTrait
{
    protected UploadedFile $file;


    protected function validateUploadedFile(UploadedFile $file) : static
    {
        if( $file->getSize() == 0 ) {
            throw new UnprocessableEntityHttpException("You cannot upload an empty (zero-bytes) file!");
        }

        if( empty( $file->guessExtension() ) ) {
            throw new UnprocessableEntityHttpException("The system cannot determine the file extension!");
        }

        return $this;
    }
}
