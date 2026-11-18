<?php
namespace App\Tests\Editor;

use App\Entity\Cms\File as FileEntity;
use App\Service\Cms\FileEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;


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

    private array $arrFileIds = [];


    public static function formatProvider() : array
    {
        return [
            'troubleshooting pack'      => ['cab', 'pack.diagcab',  'diagcab'],
            'Windows update package'    => ['cab', 'kb123.msu',     'msu'],
            'upper case'                => ['cab', 'PACK.DIAGCAB',  'diagcab'],

            // ...a plain CAB stays .cab, and only CAB content gets its name's extension
            'plain cabinet'             => ['cab', 'archive.cab',   'cab'],
            'an image named .diagcab'   => ['png', 'image.diagcab', 'png'],
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

        $this->arrFileIds[] = $file->getId();

        $this->assertSame($expected, $file->getFormat());
        $this->assertStringEndsWith(".$expected", $file->getUserPerceivedFileName(), "The name readers download");
        $this->assertStringEndsWith($file->getId() . ".$expected", $file->getOriginalFilePath());
        $this->assertFileExists( $file->getOriginalFilePath() );
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

        $this->arrFileIds[] = $fileId = $file->getId();

        $this->reload($fileId)->replaceWithUploadedFile(
            $this->uploadedFile('cab', 'WindowsUpdateDiagnostic.diagcab')
        );

        $replaced = $this->reload($fileId);

        $this->assertTrue( $replaced->isLocal() );
        $this->assertSame( 'diagcab', $replaced->getFormat() );
        $this->assertStringEndsWith( '.diagcab', $replaced->getUserPerceivedFileName() );
        $this->assertFileExists( $replaced->getOriginalFilePath() );
    }


    protected function tearDown() : void
    {
        static::ensureKernelShutdown();
        static::loginAsSystem();

        foreach($this->arrFileIds as $fileId) {

            if( static::getEntityManager()->find(FileEntity::class, $fileId) ) {
                $this->reload($fileId)->delete();
            }
        }

        static::getEntityManager()->flush();
        $this->arrFileIds = [];

        parent::tearDown();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 fixtures ***">
    /**
     * @param string $content "cab" (a valid cabinet, one stored file) or "png" (a 1x1 image); unique each time, since
     *                        the source of a Download can't be another one's
     */
    private function uploadedFile(string $content, string $clientName) : UploadedFile
    {
        $unique = uniqid(self::FIXTURE_TITLE_PREFIX);

        $bytes = match($content) {
            'cab' => $this->cabinet('readme.txt', $unique),
            'png' => base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
            ) . $unique,
            default => throw new InvalidArgumentException("No fixture for «{$content}»: cab or png"),
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
