<?php
namespace App\Tests\Editor;

use App\Controller\Editor\FileEditController;
use App\Entity\Cms\File as FileEntity;
use App\Service\Cms\FileEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;


/**
 * A Download is permanent (docs/downloadable-files.md): "Sostituisci" swaps its file or URL and keeps its ID, so
 * every link follows. The old local file goes away, the date shown to readers moves, and a source that is already
 * another Download is refused (one item, one Download). A rename is not a new version: the date stays.
 *
 * @see \App\Service\Cms\FileEditor::replaceWithUploadedFile()
 * @see \App\Service\Cms\FileEditor::replaceWithUrl()
 * @see \App\Entity\Cms\File::setHash()
 */
class FileReplaceTest extends BaseT
{
    private const string FIXTURE_TITLE_PREFIX = 'tli-test-file-replace-';
    private const string OLD_DATE = '2020-01-01 00:00:00';

    private array $arrFileIds = [];


    public function testLocalFileReplacedByANewFile() : void
    {
        static::loginAsSystem();

        $file       = $this->createLocalFile('v1', 'txt');
        $fileId     = $file->getId();
        $title      = $file->getTitle();
        $oldPath    = $file->getOriginalFilePath();

        $newContent = uniqid('v2-');
        $this->reload($fileId)->replaceWithUploadedFile( $this->uploadedFile($newContent, 'zip') );

        $replaced = $this->reload($fileId);

        $this->assertSame($title, $replaced->getTitle(), "Replacing must keep the title");
        $this->assertTrue( $replaced->isLocal() );
        $this->assertSame( md5($newContent), $replaced->getHash() );
        $this->assertSame( $newContent, file_get_contents( $replaced->getOriginalFilePath() ) );
        $this->assertFileDoesNotExist($oldPath, "The old version must be deleted");
        $this->assertSourceDateMoved($replaced);
    }


    public function testLocalFileReplacedByAUrl() : void
    {
        static::loginAsSystem();

        $file       = $this->createLocalFile('v1', 'zip');
        $fileId     = $file->getId();
        $oldPath    = $file->getOriginalFilePath();
        $url        = 'https://example.com/' . uniqid(self::FIXTURE_TITLE_PREFIX) . '.zip';

        // a new format too: the old file's path ("{id}.zip") must be worked out before it changes
        $this->reload($fileId)->replaceWithUrl($url, 'google play (app store)');

        $replaced = $this->reload($fileId);

        $this->assertTrue( $replaced->isExternal() );
        $this->assertSame( 'google play (app store)', $replaced->getFormat() );
        $this->assertSame( $url, $replaced->getExternalDownloadUrl() );
        $this->assertSame( md5($url), $replaced->getHash() );
        $this->assertFileDoesNotExist($oldPath, "Becoming external, the local file must be deleted");
        $this->assertSourceDateMoved($replaced);
    }


    public function testUrlReplacedByAFile() : void
    {
        static::loginAsSystem();

        $file   = $this->createExternalFile();
        $fileId = $file->getId();

        $newContent = uniqid('v2-');
        $this->reload($fileId)->replaceWithUploadedFile( $this->uploadedFile($newContent, 'txt') );

        $replaced = $this->reload($fileId);

        $this->assertTrue( $replaced->isLocal() );
        $this->assertSame( 'txt', $replaced->getFormat() );
        $this->assertSame( $newContent, file_get_contents( $replaced->getOriginalFilePath() ) );
        $this->assertSourceDateMoved($replaced);
    }


    public function testSourceOfAnotherDownloadIsRefused() : void
    {
        static::loginAsSystem();

        $other  = $this->createExternalFile();
        $file   = $this->createExternalFile();
        $fileId = $file->getId();
        $oldUrl = $file->getExternalDownloadUrl();

        // the same item as the other Download: only tracking parameters differ
        $sameItemUrl = $other->getExternalDownloadUrl() . '?utm_source=newsletter';

        try {
            $this->reload($fileId)->replaceWithUrl($sameItemUrl);
            $this->fail("Replacing with another Download's URL must be refused");

        } catch(ConflictHttpException) {}

        static::ensureKernelShutdown();
        static::loginAsSystem();
        $this->assertSame( $oldUrl, $this->reload($fileId)->getExternalDownloadUrl() );
    }


    public function testRenameKeepsTheSourceDate() : void
    {
        static::loginAsSystem();

        $fileId = $this->createExternalFile()->getId();

        $this->reload($fileId)
            ->setTitle( uniqid(self::FIXTURE_TITLE_PREFIX . 'renamed-') )
            ->setFormat('iso')
            ->save();

        $this->assertSame( self::OLD_DATE, $this->reload($fileId)->getSourceUpdatedAt()->format('Y-m-d H:i:s'),
            "A rename is not a new version: the date shown to readers must not move" );
    }


    public function testModificaNoLongerChangesTheUrl() : void
    {
        static::loginAsSystem();

        $file   = $this->createExternalFile();
        $fileId = $file->getId();
        $oldUrl = $file->getExternalDownloadUrl();
        $title  = uniqid(self::FIXTURE_TITLE_PREFIX . 'modifica-');

        $request = Request::create("/ajax/editor/file/update/$fileId/1", 'POST', [
            'title' => $title, 'format' => 'zip', 'remote-url' => 'javascript:alert(1)'
        ]);

        static::getService('request_stack')->push($request);
        $response = static::getService(FileEditController::class)->update($fileId, 1);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        static::ensureKernelShutdown();
        static::loginAsSystem();
        $reloaded = $this->reload($fileId);

        $this->assertSame($title, $reloaded->getTitle());
        $this->assertSame($oldUrl, $reloaded->getExternalDownloadUrl(), "A new URL goes through Sostituisci, with its checks");
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
    private function createLocalFile(string $contentPrefix, string $extension) : FileEditor
    {
        $file =
            static::getService(Factory::class)->createFileEditor()
                ->createFromUploadedFile(
                    $this->uploadedFile( uniqid($contentPrefix . '-'), $extension ), uniqid(self::FIXTURE_TITLE_PREFIX)
                );

        return $this->ageFixture($file);
    }


    private function createExternalFile() : FileEditor
    {
        $file =
            static::getService(Factory::class)->createFileEditor()
                ->createFromUrl(
                    'https://example.com/' . uniqid(self::FIXTURE_TITLE_PREFIX) . '.zip',
                    uniqid(self::FIXTURE_TITLE_PREFIX), 'zip'
                );

        return $this->ageFixture($file);
    }


    // backdated, so a replace made in the same second still shows up as a newer date
    private function ageFixture(FileEditor $file) : FileEditor
    {
        // its author may edit and replace it (FileSentinel)
        $file->addAuthor( static::getService(Factory::class)->getCurrentUserAsAuthor() );
        static::getEntityManager()->flush();

        $this->arrFileIds[] = $fileId = $file->getId();

        static::getEntityManager()->getConnection()->executeStatement(
            'UPDATE file SET source_updated_at = :date WHERE id = :id', ['date' => self::OLD_DATE, 'id' => $fileId]
        );

        static::ensureKernelShutdown();
        static::loginAsSystem();

        return $this->reload($fileId);
    }


    private function uploadedFile(string $content, string $extension) : UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'tli_test_file_replace_');
        file_put_contents($tmpPath, $content);
        return new UploadedFile($tmpPath, "fixture.$extension", null, null, true);
    }


    private function reload(int $fileId) : FileEditor
    {
        static::getEntityManager()->clear();
        return static::getService(Factory::class)->createFileEditor()->load($fileId);
    }


    private function assertSourceDateMoved(FileEditor $file) : void
    {
        $this->assertGreaterThan( self::OLD_DATE, $file->getSourceUpdatedAt()->format('Y-m-d H:i:s'),
            "A new version must move the date shown to readers" );
    }
    //</editor-fold>
}
