<?php
namespace App\Service\Cms;

use App\Entity\Cms\File as FileEntity;
use App\Entity\Cms\FileAuthor;
use App\Exception\FileLogicException;
use App\Service\Factory;
use App\Service\TextProcessor;
use App\Service\User;
use App\Trait\SaveableTrait;
use App\Trait\UploadableFileTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


class FileEditor extends File
{
    use UploadableFileTrait, SaveableTrait { save as protected traitSave; }

    protected ?string $previousFilePath = null;

    // 🔒 security-audit.md finding #1: `format` becomes an on-disk path component ("{id}.{format}") for local
    // files, so it must never carry a directory separator. This allow-list is permissive on purpose — it still
    // has to accept the label-style formats used by external "app store" files (spaces, parentheses, hyphens)
    // and compound extensions like "tar.gz". "/" and "\" are excluded by the class; ".." is rejected separately.
    const string FORMAT_ALLOWED_REGEX = '/^[a-z0-9 ().-]*$/';


    public function __construct(Factory $factory, protected TextProcessor $textProcessor)
    {
        parent::__construct($factory);
    }


    public function createFromUrl(string $url, string $title, string $format) : FileEditor
    {
        $this
            ->setExternalDownloadUrl($url)
            ->setTitle($title)
            ->setFormat($format)
            ->setAutoHash()
            ->save();

        return $this;
    }


    public function createFromUploadedFile(UploadedFile $file, ?string $title = null) : FileEditor
    {
        // general validation (from UploadableFileTrait)
        $this->validateUploadedFile($file);

        $originalFilename           = $file->getClientOriginalName();
        $filenameWithoutExtension   = pathinfo($originalFilename, PATHINFO_FILENAME);
        $clientExtension            = $file->getClientOriginalExtension();
        $extension                  = $file->guessExtension();

        // some file types, such as .ps1, have no "official" mime-type, thus are recognized as "text/plain" =>
        // this would screw the extension ==> falling back to client-provided extension
        if(
            in_array($clientExtension, ['ps1', 'service', 'bat', 'sh']) ||
            empty($extension) || in_array($extension, ['txt', 'html'])
        ) {
            $extension = $clientExtension;
        }


        $hash = hash_file('md5', $file->getPathname() );

        $effectiveTitle = $title !== null && trim($title) !== '' ? $title : $filenameWithoutExtension;

        $this
            ->setTitle($effectiveTitle)
            ->setFormat($extension)
            ->setHash($hash)
            ->save();

        $destinationFullPath = $this->getOriginalFilePath();

        $file->move( dirname($destinationFullPath), basename($destinationFullPath) );

        return $this;
    }


    public function setAutoHash() : static
    {
        $localFilePath = $this->previousFilePath ?? $this->getOriginalFilePath();

        $hash = $this->isLocal() ? hash_file('md5', $localFilePath) : md5( $this->getExternalDownloadUrl() );

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
        // physical file on disk (local files only; external files have no local path ➡ null)
        $filePath = $this->getOriginalFilePath();
        if( !empty($filePath) ) {

            // 🔒 never unlink outside the uploads dir, whatever the stored format (security-audit.md finding #1)
            $this->assertFilePathIsWithinUploadsDir($filePath);

            if( file_exists($filePath) ) {
                unlink($filePath);
            }
        }

        // db entity
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

        // 🔒 reject anything that could turn "{id}.{format}" into a path traversal (security-audit.md finding #1)
        if(
            mb_strlen($cleanFormat) > FileEntity::FORMAT_MAX_LENGTH ||
            str_contains($cleanFormat, '..') ||
            preg_match(static::FORMAT_ALLOWED_REGEX, $cleanFormat) !== 1
        ) {
            throw new BadRequestHttpException(
                "Formato file non valido: “{$cleanFormat}”. Sono ammessi solo lettere, cifre, spazi, " .
                "parentesi tonde, punti e trattini (nessun separatore di percorso)."
            );
        }

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

            // 🔒 never rename the upload outside the uploads dir, whatever the stored format (finding #1)
            $this->assertFilePathIsWithinUploadsDir($currentFilePath);

            rename($previousFilePath, $currentFilePath);
            $this->previousFilePath = null;
        }

        return $this;
    }


    /**
     * 🔒 Guard for the filesystem sinks (rename in save(), unlink in delete()): a last line of defence that
     * confines an on-disk file path to var/uploaded-assets/files/, so a crafted `format` can never make the app
     * write/delete outside it — independent of setFormat()'s allow-list and of PHP's mkdir() behaviour.
     * See docs/security-audit.md finding #1.
     */
    protected function assertFilePathIsWithinUploadsDir(string $filePath) : void
    {
        $uploadsDir = $this->normalizePathLexically(
            $this->projectDir->getVarDir(static::UPLOADED_FILES_FOLDER_NAME)
        );

        $target = $this->normalizePathLexically($filePath);

        if( !str_starts_with($target . DIRECTORY_SEPARATOR, $uploadsDir . DIRECTORY_SEPARATOR) ) {
            throw new FileLogicException("Blocked a file operation outside the uploads directory: $filePath");
        }
    }


    /**
     * Resolve "." and ".." purely lexically (no filesystem access, so it works for a not-yet-existing rename
     * target too). Both sides of the containment check are normalized this way and both derive from the project
     * dir, so symlinked deploy paths compare consistently without realpath().
     */
    protected function normalizePathLexically(string $path) : string
    {
        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR);

        $parts = [];
        foreach( explode(DIRECTORY_SEPARATOR, $path) as $segment ) {

            if( $segment === '' || $segment === '.' ) {
                continue;
            }

            if( $segment === '..' ) {
                array_pop($parts);
                continue;
            }

            $parts[] = $segment;
        }

        return ( $isAbsolute ? DIRECTORY_SEPARATOR : '' ) . implode(DIRECTORY_SEPARATOR, $parts);
    }
}
