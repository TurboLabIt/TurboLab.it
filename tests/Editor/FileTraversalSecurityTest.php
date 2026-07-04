<?php
namespace App\Tests\Editor;

use App\Entity\Cms\File as FileEntity;
use App\Exception\FileLogicException;
use App\Service\Cms\FileEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use TurboLabIt\BaseCommand\Service\ProjectDir;


/**
 * 🔒 SECURITY — regression tests for docs/security-audit.md finding #1
 * ("path traversal nel campo `format` dei file").
 *
 * `format` becomes an on-disk path component for local files ("{id}.{format}"), so a crafted value such as
 * `x/../../../../public/s.php` could otherwise let FileEditor::save()'s rename() drop the uploaded bytes into
 * the web root → RCE. The fix has two layers, both exercised here:
 *   1. FileEditor::setFormat() allow-lists the value (letters/digits/space/()/-/. only; no "/" "\" or "..") —
 *      while still accepting every real format, incl. the "app store" labels and compound extensions (tar.gz).
 *   2. FileEditor::save()/delete() confine the resolved path to var/uploaded-assets/files/ (assertFilePath…),
 *      a defence-in-depth guard that holds even if setFormat() is bypassed.
 *
 * Driven at the service layer via loginAsSystem() (the phpBB-cookie authenticator can't be exercised over HTTP
 * in tests). All disk/DB side effects are cleaned up.
 */
class FileTraversalSecurityTest extends BaseT
{
    public static function legitimateFormatProvider() : array
    {
        return [
            'plain extension'      => ['zip', 'zip'],
            'uppercase lowercased' => ['EXE', 'exe'],
            'compound tar.gz'      => ['tar.gz', 'tar.gz'],
            'digit-leading 7z'     => ['7z', '7z'],
            'script ps1'           => ['ps1', 'ps1'],
            'app-store label'      => ['google play (app store)', 'google play (app store)'],
            'hyphen label'         => ['f-droid (app store)', 'f-droid (app store)'],
            'empty (external)'     => ['', ''],
        ];
    }


    #[DataProvider('legitimateFormatProvider')]
    public function testSetFormatAcceptsLegitimateFormats(string $format, string $expectedStored) : void
    {
        static::loginAsSystem();

        $fileEditor = static::getService(Factory::class)->createFileEditor();
        $fileEditor->setFormat($format);

        $this->assertSame(
            $expectedStored, (string)$fileEditor->getFormat(),
            "the allow-list must keep accepting the legitimate format “{$format}”"
        );
    }


    public static function maliciousFormatProvider() : array
    {
        return [
            'traversal to public'  => ['x/../../../../public/s.php'],
            'forward slash'        => ['zip/x'],
            'back slash'           => ['zip\\x'],
            'bare dot-dot'         => ['..'],
            'embedded dot-dot'     => ['a..b'],
            'over the length cap'  => [str_repeat('a', FileEntity::FORMAT_MAX_LENGTH + 1)],
        ];
    }


    #[DataProvider('maliciousFormatProvider')]
    public function testSetFormatRejectsPathTraversalFormats(string $format) : void
    {
        static::loginAsSystem();

        $fileEditor = static::getService(Factory::class)->createFileEditor();

        $this->expectException(BadRequestHttpException::class);
        $fileEditor->setFormat($format);
    }


    /**
     * Defence-in-depth: even if the allow-list is bypassed (here by writing straight to the entity), the sink
     * guard in save() must refuse to rename the file outside the uploads dir, so nothing reaches the web root.
     */
    public function testSaveIsBlockedFromWritingOutsideUploadsDir() : void
    {
        static::loginAsSystem();

        $factory      = static::getService(Factory::class);
        $projectDir   = rtrim( static::getService(ProjectDir::class)->getProjectDir(), '/\\' );
        $evilFilename = 'tli-sec-poc.txt';
        $publicTarget = $projectDir . '/public/' . $evilFilename;

        $this->deleteFileIfExists($publicTarget);

        $srcTmp = null; $fileId = null; $cleanPath = null; $pivotDir = null;

        try {
            // upload a normal local file → var/uploaded-assets/files/{id}.txt
            $srcTmp = tempnam(sys_get_temp_dir(), 'tli_poc_');
            file_put_contents($srcTmp, "poc\n");
            $uploaded = new UploadedFile($srcTmp, 'poc.txt', 'text/plain', null, true);
            $created  = $factory->createFileEditor()->createFromUploadedFile($uploaded, 'tli-security-poc-' . uniqid());
            $fileId   = $created->getId();

            /** @var FileEditor $victim */
            $victim    = $factory->createFileEditor()->load($fileId);
            $cleanPath = $victim->getOriginalFilePath();

            // build a traversal path targeting the web root, sized to the real uploads-dir depth
            $filesDir = dirname($cleanPath);
            $relative = trim( substr($filesDir, strlen($projectDir)), '/\\' );
            $climb    = count( explode('/', $relative) ) + 1;
            $pivotDir = $filesDir . '/' . $fileId . '.x';
            $traversalFormat = 'x/' . str_repeat('../', $climb) . 'public/' . $evilFilename;

            // 🔩 bypass setFormat()'s allow-list by writing directly to the entity, to isolate the save() guard
            $victim->getEntity()->setFormat($traversalFormat);

            // save() must abort at the containment check, before the rename. (Swallow the mkdir/rename E_WARNINGs
            // the failing traversal would emit; phpunit runs with failOnWarning=true.)
            $blocked = false;
            set_error_handler(static fn() : bool => true, E_WARNING);
            try {
                $victim->save();
            } catch( FileLogicException ) {
                $blocked = true;
            } finally {
                restore_error_handler();
            }

            $this->assertTrue(
                $blocked,
                'save() must throw FileLogicException rather than rename the file outside the uploads dir (finding #1)'
            );
            $this->assertFileDoesNotExist(
                $publicTarget,
                "SECURITY finding #1 REGRESSION: a file escaped into the web root ($publicTarget)."
            );

        } finally {
            $this->deleteFileIfExists($publicTarget);
            $this->deleteFileIfExists($cleanPath);          // the original {id}.txt (rename never happened)
            $this->deleteFileIfExists($srcTmp);
            if( !empty($pivotDir) && is_dir($pivotDir) ) {
                @rmdir($pivotDir);
            }
            if( !empty($fileId) ) {
                $em     = static::getEntityManager();
                $entity = $em->find(FileEntity::class, $fileId);
                if( $entity !== null ) {
                    $em->remove($entity);
                    $em->flush();
                }
            }
        }
    }


    private function deleteFileIfExists(?string $path) : void
    {
        if( !empty($path) && is_file($path) ) {
            @unlink($path);
        }
    }
}
