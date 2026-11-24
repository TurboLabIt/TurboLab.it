<?php
namespace App\Tests\Editor;

use App\Entity\Cms\File as FileEntity;
use App\Service\Cms\FileEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;


/**
 * An uploaded Download's format is its extension, and the extension comes from what the file holds (its mime-type),
 * not from its name. A .diagcab (a troubleshooting pack) and a .msu (a Windows update package) hold a CAB archive:
 * they became "cab", readers downloaded "title.cab", and Windows opens that as an archive instead of running it.
 * Found on 2026-11-18 with Download #92, Microsoft's Windows Update troubleshooter for Windows 7 and 8.1.
 *
 * @see \App\Service\Cms\FileEditor::getUploadedFileExtension()
 * @see \App\Service\Cms\FileEditor::CAB_FORMATS
 */
class FileUploadFormatTest extends BaseT
{
    private const string FIXTURE_TITLE_PREFIX = 'tli-test-file-upload-format-';


    public static function formatProvider() : array
    {
        return [
            'troubleshooting pack'      => ['cab', 'pack.diagcab',  'diagcab'],
            'Windows update package'    => ['cab', 'kb123.msu',     'msu'],
            'upper case'                => ['cab', 'PACK.DIAGCAB',  'diagcab'],

            // ...a plain CAB stays .cab, and only CAB content gets its name's extension
            'plain cabinet'             => ['cab', 'archive.cab',   'cab'],
            'an image named .diagcab'   => ['png', 'image.diagcab', 'png'],

            // ...and a name without one gets the content's
            'an image, no extension'    => ['png', 'image',         'png'],
        ];
    }


    #[DataProvider('formatProvider')]
    public function testUploadedFormat(string $content, string $clientName, string $expected) : void
    {
        static::loginAsSystem();

        $file =
            static::getService(Factory::class)->createFileEditor()
                ->createFromUploadedFile(
                    $this->uploadedFile($content, $clientName), uniqid(self::FIXTURE_TITLE_PREFIX)
                );

        $this->assertSame($expected, $file->getFormat());
        $this->assertStringEndsWith(".$expected", $file->getUserPerceivedFileName(), "The name readers download");
        $this->assertStringEndsWith($file->getId() . ".$expected", $file->getOriginalFilePath());
        $this->assertFileExists( $file->getOriginalFilePath() );
    }


    /**
     * No extension in the name, and none from the content (text, HTML, a type without one): the Download would have
     * no format, so no name on disk ("{id}.{format}"). It used to be saved all the same and fail right after, with a
     * 500 in English, leaving a Download without format that stopped the file maintenance at every run and made the
     * same file fail again, renamed or not (issue #141)
     */
    public static function withoutExtensionProvider() : array
    {
        return [
            'a licence'             => ['txt',  'LICENSE',  'LICENSE.txt'],
            'Windows hosts file'    => ['txt',  'hosts',    'hosts.txt'],
            'a web page'            => ['html', 'index',    'index.html'],
            'a trailing dot'        => ['txt',  'LICENSE.', 'LICENSE.txt'],

            // a shell script's mime-type has no extension: nothing to suggest
            'a script'              => ['sh',   'install',  null],
        ];
    }


    #[DataProvider('withoutExtensionProvider')]
    public function testUploadWithoutExtensionIsRefused(string $content, string $clientName, ?string $suggestedName) : void
    {
        static::loginAsSystem();

        $upload = $this->uploadedFile($content, $clientName);
        $hash   = md5_file( $upload->getPathname() );

        try {
            static::getService(Factory::class)->createFileEditor()
                ->createFromUploadedFile($upload, uniqid(self::FIXTURE_TITLE_PREFIX));

            $this->fail("A file without extension must be refused: «{$clientName}»");

        } catch(UnprocessableEntityHttpException $ex) {

            $this->assertStringContainsString("«{$clientName}»", $ex->getMessage(), "The message names the file");

            if( $suggestedName !== null ) {
                $this->assertStringContainsString("«{$suggestedName}»", $ex->getMessage(), "...and what to call it");
            }
        }

        $this->assertSame( [],
            static::getEntityManager()->getRepository(FileEntity::class)->findBy(['hash' => $hash]),
            "Nothing may be saved: a Download without format stops the file maintenance"
        );

        if( $suggestedName === null ) {
            return;
        }

        // renamed as suggested, the same file goes in
        $renamed =
            static::getService(Factory::class)->createFileEditor()
                ->createFromUploadedFile(
                    new UploadedFile($upload->getPathname(), $suggestedName, null, null, true),
                    uniqid(self::FIXTURE_TITLE_PREFIX)
                );

        $this->assertSame( pathinfo($suggestedName, PATHINFO_EXTENSION), $renamed->getFormat() );
        $this->assertFileExists( $renamed->getOriginalFilePath() );
    }


    /**
     * "Sostituisci" with such a file: refused the same way, before the Download changes. It used to lose its format
     * (and, if external, its URL) on the way, and then answer every reader with a 500
     */
    public function testReplaceWithoutExtensionIsRefused() : void
    {
        static::loginAsSystem();

        $file =
            static::getService(Factory::class)->createFileEditor()
                ->createFromUploadedFile( $this->uploadedFile('txt', 'readme.txt'), uniqid(self::FIXTURE_TITLE_PREFIX) );

        $fileId = $file->getId();
        $path   = $file->getOriginalFilePath();
        $hash   = $file->getHash();

        try {
            $this->reload($fileId)->replaceWithUploadedFile( $this->uploadedFile('txt', 'LICENSE') );
            $this->fail("A new version without extension must be refused");

        } catch(UnprocessableEntityHttpException) {}

        static::ensureKernelShutdown();
        static::loginAsSystem();
        $unchanged = $this->reload($fileId);

        $this->assertSame( 'txt', $unchanged->getFormat() );
        $this->assertSame( $hash, $unchanged->getHash() );
        $this->assertSame( $path, $unchanged->getOriginalFilePath() );
        $this->assertSame( $hash, md5_file($path), "The file on disk must still be the old version" );
    }


    /**
     * What "Sostituisci" does to #92: an external Download, labelled "cab", becomes the troubleshooting pack itself
     */
    public function testUrlReplacedByATroubleshootingPack() : void
    {
        static::loginAsSystem();

        $file =
            static::getService(Factory::class)->createFileEditor()
                ->createFromUrl(
                    'https://example.com/' . uniqid(self::FIXTURE_TITLE_PREFIX) . '.diagcab',
                    uniqid(self::FIXTURE_TITLE_PREFIX), 'cab'
                );

        $fileId = $file->getId();

        $this->reload($fileId)->replaceWithUploadedFile(
            $this->uploadedFile('cab', 'WindowsUpdateDiagnostic.diagcab')
        );

        $replaced = $this->reload($fileId);

        $this->assertTrue( $replaced->isLocal() );
        $this->assertSame( 'diagcab', $replaced->getFormat() );
        $this->assertStringEndsWith( '.diagcab', $replaced->getUserPerceivedFileName() );
        $this->assertFileExists( $replaced->getOriginalFilePath() );
    }


    // every Download of these tests, found by title: a refused upload that saved one anyway left no ID to keep
    protected function tearDown() : void
    {
        static::ensureKernelShutdown();
        static::loginAsSystem();

        $arrFileIds =
            static::getEntityManager()->getConnection()->fetchFirstColumn(
                'SELECT id FROM file WHERE title LIKE :prefix', ['prefix' => self::FIXTURE_TITLE_PREFIX . '%']
            );

        foreach($arrFileIds as $fileId) {

            $entity = static::getEntityManager()->find(FileEntity::class, $fileId);

            // without a format, FileEditor can't even load it, nor tell which file on disk was its own
            if( empty( $entity->getFormat() ) ) {

                static::getEntityManager()->remove($entity);
                static::getEntityManager()->flush();
                continue;
            }

            $this->reload( (int)$fileId )->delete();
        }

        parent::tearDown();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 fixtures ***">
    /**
     * @param string $content "cab" (a valid cabinet, one stored file), "png" (a 1x1 image), "txt" (plain text), "html"
     *                        (a web page) or "sh" (a shell script); unique each time, since the source of a Download
     *                        can't be another one's
     */
    private function uploadedFile(string $content, string $clientName) : UploadedFile
    {
        $unique = uniqid(self::FIXTURE_TITLE_PREFIX);

        $bytes = match($content) {
            'cab'   => $this->cabinet('readme.txt', $unique),
            'png'   => base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
            ) . $unique,
            'txt'   => "MIT License\n\nCopyright (c) $unique\n",
            'html'  => "<!doctype html>\n<html><body>$unique</body></html>\n",
            'sh'    => "#!/bin/bash\necho $unique\n",
            default => throw new InvalidArgumentException("No fixture for «{$content}»: cab, png, txt, html or sh"),
        };

        $tmpPath = tempnam(sys_get_temp_dir(), 'tli_test_file_upload_format_');
        file_put_contents($tmpPath, $bytes);
        return new UploadedFile($tmpPath, $clientName, null, null, true);
    }


    // the smallest valid CAB archive: header, one folder, one file stored as it is (no compression, no checksum)
    private function cabinet(string $fileName, string $fileContent) : string
    {
        $cfFile     = pack('VVvvvv', strlen($fileContent), 0, 0, 0x5A21, 0, 0x20) . $fileName . "\0";
        $filesAt    = 36 + 8;
        $dataAt     = $filesAt + strlen($cfFile);
        $cfData     = pack('Vvv', 0, strlen($fileContent), strlen($fileContent)) . $fileContent;
        $cfHeader   = 'MSCF' . pack('VVVVVCCvvvvv', 0, $dataAt + strlen($cfData), 0, $filesAt, 0, 3, 1, 1, 1, 0, 0, 0);
        $cfFolder   = pack('Vvv', $dataAt, 1, 0);

        return $cfHeader . $cfFolder . $cfFile . $cfData;
    }


    private function reload(int $fileId) : FileEditor
    {
        static::getEntityManager()->clear();
        return static::getService(Factory::class)->createFileEditor()->load($fileId);
    }
    //</editor-fold>
}
