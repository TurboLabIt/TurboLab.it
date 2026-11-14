<?php
namespace App\Tests\Editor;

use App\Service\Cms\File;
use App\Service\Factory;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * One item, one Download (docs/downloadable-files.md): a URL differing from an existing Download's only by
 * http/https, "www.", a trailing "/", the #fragment or tracking parameters is the same item, and the existing
 * Download is reused, its own URL untouched. Any other difference is a different item.
 *
 * @see \App\Service\Cms\File::normalizeUrlForMatching()
 * @see \App\ServiceCollection\Cms\FileCollection::loadByEquivalentUrl()
 */
class FileUrlEquivalenceTest extends BaseT
{
    public static function equivalentUrlProvider() : array
    {
        return [
            ['https://github.com/pbatard/rufus/releases/latest', 'http://github.com/pbatard/rufus/releases/latest'],
            ['https://github.com/pbatard/rufus/releases/latest', 'https://www.github.com/pbatard/rufus/releases/latest/'],
            ['https://github.com/pbatard/rufus/releases/latest', 'https://GitHub.com/pbatard/rufus/releases/latest#readme'],
            ['https://example.com/get?id=5', 'https://example.com/get?utm_source=x&id=5&utm_campaign=y'],
            ['https://example.com/get?id=5', 'https://example.com/get?id=5&fbclid=abc&gclid=def'],
            ['https://example.com/file.zip', 'https://example.com/file.zip?utm_medium=social'],
        ];
    }


    #[DataProvider('equivalentUrlProvider')]
    public function testEquivalentUrls(string $url1, string $url2)
    {
        $this->assertSame( File::normalizeUrlForMatching($url1), File::normalizeUrlForMatching($url2) );
    }


    public static function differentUrlProvider() : array
    {
        return [
            ['https://example.com/get?id=5', 'https://example.com/get?id=6'],
            ['https://example.com/file.zip', 'https://example.com/File.zip'],
            ['https://example.com/a/file.zip', 'https://example.com/b/file.zip'],
            ['https://example.com/file.zip', 'https://example.org/file.zip'],
            ['https://example.com/get?ref=a', 'https://example.com/get?ref=b'],
        ];
    }


    #[DataProvider('differentUrlProvider')]
    public function testDifferentUrls(string $url1, string $url2)
    {
        $this->assertNotSame( File::normalizeUrlForMatching($url1), File::normalizeUrlForMatching($url2) );
    }


    public function testNonHttpUrlsAreOnlyTrimmed()
    {
        $magnet = 'magnet:?xt=urn:btih:c7f67b2aa427fb27d8869750d1e12b6858aaaf61';
        $this->assertSame($magnet, File::normalizeUrlForMatching(" $magnet "));
    }


    public static function validExternalUrlProvider() : array
    {
        return [
            ['https://github.com/pbatard/rufus/releases/latest'],
            ['http://example.com/file.zip?utm_source=x'],
            ['magnet:?xt=urn:btih:c7f67b2aa427fb27d8869750d1e12b6858aaaf61&dn=PhoenixPE_it-IT_x64.zip'],
            ['magnet:?dn=file.zip&xt=urn:btmh:12202256d299385875b29ebe610ddf4f6d2a91ca9b8a15163afc9a765629c9480bcc'],
            ['ed2k://|file|ubuntu-20.04.1-desktop-amd64.iso|2785017856|C82633642AF72E8426A954F613794846|h=6BESAJRO4DSANXZKWSWEHY44ANBHDPYN|/'],
            ['ed2k://|serverlist|http://upd.emule-security.org/server.met|/'],
        ];
    }


    #[DataProvider('validExternalUrlProvider')]
    public function testValidExternalUrls(string $url)
    {
        $this->assertTrue( File::isValidExternalUrl($url) );
    }


    public static function invalidExternalUrlProvider() : array
    {
        return [
            ['javascript:alert(1)'],
            ['ftp://example.com/file.zip'],
            ['https://'],
            ['https://example.com/a file.zip'],
            ['magnet:?dn=file.zip'],
            ['magnet:xt=urn:btih:c7f67b2aa427fb27d8869750d1e12b6858aaaf61'],
            ['ed2k://file|name|'],
            ['example.com/file.zip'],
        ];
    }


    #[DataProvider('invalidExternalUrlProvider')]
    public function testInvalidExternalUrls(string $url)
    {
        $this->assertFalse( File::isValidExternalUrl($url) );
    }


    public function testExistingDownloadFoundByEquivalentUrl()
    {
        $factory = static::getService(Factory::class);

        $external = null;
        foreach( $factory->createFileCollection()->getRepository()->getExternal() as $entity ) {
            if( str_starts_with($entity->getUrl(), 'http') ) {
                $external = $entity;
                break;
            }
        }

        if( empty($external) ) {
            $this->markTestSkipped('No external Download with an http(s) URL');
        }

        $url        = $external->getUrl();
        $separator  = str_contains($url, '?') ? '&' : '?';
        $variant    = preg_replace('#^https?://(www\.)?#', 'http://www.', $url) . $separator . 'utm_source=newsletter';

        $matches = $factory->createFileCollection()->loadByEquivalentUrl($variant);

        $arrIds = [];
        foreach($matches as $file) {
            $arrIds[] = $file->getId();
        }

        $this->assertContains($external->getId(), $arrIds);
    }
}
