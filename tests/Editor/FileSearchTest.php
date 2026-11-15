<?php
namespace App\Tests\Editor;

use App\Entity\Cms\File as FileEntity;
use App\Service\Cms\FileEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


/**
 * The "Aggiungi un Download" search (docs/downloadable-files.md): text finds a Download by its title or its URL,
 * accented letters included, and a Download's own address ("/scarica/123", its ID, the absolute URL) finds that
 * Download. That address is never an external source: it would make a Download that redirects to another one.
 *
 * @see \App\ServiceCollection\Cms\FileCollection::loadSerp()
 * @see \App\ServiceCollection\Cms\FileCollection::loadByEquivalentUrl()
 * @see \App\Repository\Cms\FileRepository::getSerpByFulltext()
 */
class FileSearchTest extends BaseT
{
    private const string FIXTURE_TITLE_PREFIX = 'tli-test-file-search-';

    private ?int $fileId = null;


    public function testFoundByAWordOfItsUrl() : void
    {
        $urlWord = uniqid('urlword');
        $file    = $this->createExternalFile( uniqid(self::FIXTURE_TITLE_PREFIX), "https://example.com/$urlWord/setup.exe" );

        $this->assertArrayHasKey( $file->getId(), $this->search($urlWord), "A word only in the URL must find it" );
    }


    public function testAccentedLettersAreKept() : void
    {
        // stripping "é" turned the term into "perchtli…", a word nothing contains
        $accentedWord   = uniqid('perchétli');
        $file           = $this->createExternalFile( self::FIXTURE_TITLE_PREFIX . $accentedWord, 'https://example.com/' . uniqid() . '.zip' );

        $this->assertArrayHasKey( $file->getId(), $this->search($accentedWord) );
    }


    public function testPunctuationSplitsWords() : void
    {
        $word1  = uniqid('first');
        $word2  = uniqid('second');
        $file   = $this->createExternalFile( self::FIXTURE_TITLE_PREFIX . "$word1 $word2", 'https://example.com/' . uniqid() . '.zip' );

        // "youtube-dl" used to become "youtubedl"
        $this->assertArrayHasKey( $file->getId(), $this->search("$word1-$word2") );
    }


    public function testFoundByItsIdOrItsAddress() : void
    {
        $file   = $this->createExternalFile( uniqid(self::FIXTURE_TITLE_PREFIX), 'https://example.com/' . uniqid() . '.zip' );
        $fileId = $file->getId();

        foreach([ (string)$fileId, "/scarica/$fileId", $file->getUrl() ] as $term) {

            $results = $this->search($term);
            $this->assertSame( $fileId, array_key_first($results), "«{$term}» must find the Download, first" );
        }

        $matches = static::getService(Factory::class)->createFileCollection()->loadByEquivalentUrl( $file->getUrl() );
        $this->assertSame( [$fileId], array_map(fn($match) => $match->getId(), iterator_to_array($matches, false) ),
            "Its address pasted in the modal must be that Download («Usa questo»), not a new external one" );
    }


    public function testItsAddressIsNeverAnExternalSource() : void
    {
        $file       = $this->createExternalFile( uniqid(self::FIXTURE_TITLE_PREFIX), 'https://example.com/' . uniqid() . '.zip' );
        $ownUrl     = $file->getUrl();
        $missingUrl = str_replace("/scarica/{$file->getId()}", '/scarica/' . PHP_INT_MAX, $ownUrl);

        $this->assertRefused(
            fn() => static::getService(Factory::class)->createFileEditor()
                ->createFromUrl($missingUrl, uniqid(self::FIXTURE_TITLE_PREFIX), 'zip'),
            "A Download's address must not become a new external Download"
        );

        $this->assertRefused(
            fn() => $this->reload( $file->getId() )->replaceWithUrl($ownUrl),
            "A Download replaced with its own address would redirect to itself"
        );
    }


    public function testMagnetAndEd2kAreNotDownloadAddresses() : void
    {
        $generator = static::getService(Factory::class)->getFileUrlGenerator();

        $this->assertNull( $generator->extractIdFromUrl('magnet:?xt=urn:btih:c7f67b2aa427fb27d8869750d1e12b6858aaaf61') );
        $this->assertNull( $generator->extractIdFromUrl('ed2k://|file|ubuntu.iso|2785017856|C82633642AF72E8426A954F613794846|/') );
    }


    protected function tearDown() : void
    {
        if( !empty($this->fileId) ) {

            static::loginAsSystem();
            $em     = static::getEntityManager();
            $entity = $em->find(FileEntity::class, $this->fileId);

            if( $entity !== null ) {
                $em->remove($entity);
                $em->flush();
            }
        }

        $this->fileId = null;
        parent::tearDown();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 fixtures ***">
    private function createExternalFile(string $title, string $url) : FileEditor
    {
        static::loginAsSystem();

        $file = static::getService(Factory::class)->createFileEditor()->createFromUrl($url, $title, 'zip');
        $this->fileId = $file->getId();

        return $this->reload($this->fileId);
    }


    private function assertRefused(callable $fxAction, string $message) : void
    {
        $exception = null;
        try { $fxAction(); } catch(BadRequestHttpException $ex) { $exception = $ex; }

        $this->assertInstanceOf(BadRequestHttpException::class, $exception, $message);
    }


    /** The IDs found, in the order shown */
    private function search(string $term) : array
    {
        $arrIds = [];
        foreach( static::getService(Factory::class)->createFileCollection()->loadSerp($term) as $file ) {
            $arrIds[ $file->getId() ] = $file->getId();
        }

        return $arrIds;
    }


    private function reload(int $fileId) : FileEditor
    {
        static::getEntityManager()->clear();
        return static::getService(Factory::class)->createFileEditor()->load($fileId);
    }
    //</editor-fold>
}
