<?php
namespace App\Tests\Editor;

use App\Service\Cms\ArticleEditor;
use App\Service\Cms\FileUrlGenerator;
use App\Service\Factory;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * On save, a body link to /scarica/{id} becomes a ==###file::id::{id}###== placeholder, and the file gets attached
 * to the article. The id extraction had its quantifier outside the capture group, ([1-9]+[0-9])*: ids 1-9 and
 * any id with a zero after its first digit run (100, 105, 1001, ...) were never recognized, and 1050 came out as 50,
 * a link silently re-pointed to a different file.
 *
 * @see \App\Service\Cms\FileUrlGenerator::extractIdFromUrl()
 * @see \App\Service\HtmlProcessorForStorage::internalLinksFromUrlToPlaceholder()
 */
class FileLinkStorageTest extends BaseT
{
    public static function fileUrlProvider() : array
    {
        return [
            ['https://turbolab.it/scarica/1', 1],
            ['https://turbolab.it/scarica/5', 5],
            ['https://turbolab.it/scarica/10', 10],
            ['https://turbolab.it/scarica/100', 100],
            ['https://turbolab.it/scarica/105', 105],
            ['https://turbolab.it/scarica/557', 557],
            ['https://turbolab.it/scarica/1001', 1001],
            ['https://turbolab.it/scarica/1050', 1050],
            ['https://turbolab.it/scarica/20500', 20500],
            ['http://turbolab.it/scarica/7', 7],
            ['https://dev0.turbolab.it/scarica/101', 101],
            ['/scarica/105', 105],
        ];
    }


    #[DataProvider('fileUrlProvider')]
    public function testEveryFileIdIsExtracted(string $url, int $expectedId) : void
    {
        /** @var FileUrlGenerator $fileUrlGenerator */
        $fileUrlGenerator = static::getService(FileUrlGenerator::class);

        $this->assertSame($expectedId, $fileUrlGenerator->extractIdFromUrl($url), "Wrong file id extracted from $url");
        $this->assertTrue($fileUrlGenerator->isUrl($url), "isUrl() and extractIdFromUrl() disagree on $url");
    }


    public static function notAFileUrlProvider() : array
    {
        return [
            ['https://turbolab.it/scarica/'],
            ['https://turbolab.it/scarica/0'],
            ['https://turbolab.it/scarica/05'],
            ['https://turbolab.it/scarica/12abc'],
            ['https://turbolab.it/scarica/12/altro'],
            ['https://turbolab.it/scarica/da-controllare'],
            ['https://example.com/scarica/12'],
        ];
    }


    #[DataProvider('notAFileUrlProvider')]
    public function testNonFileUrlsAreIgnored(string $url) : void
    {
        /** @var FileUrlGenerator $fileUrlGenerator */
        $fileUrlGenerator = static::getService(FileUrlGenerator::class);

        $this->assertNull($fileUrlGenerator->extractIdFromUrl($url), "$url is not a link to a file");
        $this->assertFalse($fileUrlGenerator->isUrl($url), "isUrl() and extractIdFromUrl() disagree on $url");
    }


    public function testBodyLinksBecomeFilePlaceholdersWhateverTheId() : void
    {
        // setBody() attaches the linked files to the article, as the current user
        static::loginAsSystem();

        /** @var ArticleEditor $editor */
        $editor = static::getService(Factory::class)->createArticleEditor();

        $editor
            ->setTitle('File link probe ' . bin2hex(random_bytes(4)))
            ->setBody(
                '<p><strong>» Download:</strong> <a href="https://turbolab.it/scarica/5">uno</a></p>' .
                '<p><strong>» Download:</strong> <a href="https://turbolab.it/scarica/105">due</a></p>' .
                '<p><strong>» Download:</strong> <a href="https://turbolab.it/scarica/1050">tre</a></p>'
            );

        $stored = (string)$editor->getBody();

        foreach([5, 105, 1050] as $fileId) {
            $this->assertStringContainsString('href="==###file::id::' . $fileId . '###=="', $stored);
        }

        $this->assertStringNotContainsString('/scarica/', $stored, 'A file link was stored as a raw URL');
        $this->assertStringNotContainsString('==###file::id::50###==', $stored, 'The link to file 1050 was re-pointed to file 50');
    }
}
