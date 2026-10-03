<?php
namespace App\Tests\Editor;

use App\Service\Cms\Article;
use App\Service\Cms\ArticleEditor;
use App\Service\Cms\ArticleUrlGenerator;
use App\Service\Factory;
use App\Tests\BaseT;
use DOMDocument;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * On save, a body link to an article becomes a ==###contenuto::id::{id}###== placeholder, rendered back as the
 * article's current canonical URL. The short-URL branch (turbolab.it/1939) matched without a capture group and
 * cast the whole match, "/1939", to int: always 0, so short links were never recognized and stayed stored as typed.
 *
 * @see \App\Service\Cms\ArticleUrlGenerator::extractIdFromUrl()
 * @see \App\Service\HtmlProcessorForStorage::internalLinksFromUrlToPlaceholder()
 * @see \App\Service\HtmlProcessorForDisplay::articleLinksFromPlaceholderToUrl()
 */
class ArticleLinkStorageTest extends BaseT
{
    public static function articleUrlProvider() : array
    {
        return [
            ['https://turbolab.it/1939', 1939],
            ['http://turbolab.it/26', 26],
            ['https://dev0.turbolab.it/4524', 4524],
            ['https://next.turbolab.it/1', 1],
            ['https://turbolab.it/100', 100],
            ['https://turbolab.it/1050', 1050],
            ['/40', 40],
            ['https://turbolab.it/turbolab.it-1/come-svolgere-test-automatici-turbolab.it-1939', 1939],
            ['/windows-10/una-guida-qualsiasi-1050', 1050],
        ];
    }


    #[DataProvider('articleUrlProvider')]
    public function testEveryArticleIdIsExtracted(string $url, int $expectedId) : void
    {
        /** @var ArticleUrlGenerator $articleUrlGenerator */
        $articleUrlGenerator = static::getService(ArticleUrlGenerator::class);

        $this->assertSame($expectedId, $articleUrlGenerator->extractIdFromUrl($url), "Wrong article id extracted from $url");
        $this->assertTrue($articleUrlGenerator->isUrl($url), "$url not recognized as a link to an article");
    }


    public static function notAnArticleUrlProvider() : array
    {
        return [
            ['https://turbolab.it/'],
            ['https://turbolab.it/0'],
            ['https://turbolab.it/01'],
            ['https://turbolab.it/1939/altro'],
            ['https://turbolab.it/scarica/5'],
            ['https://turbolab.it/immagini/5/med'],
            ['https://turbolab.it/windows-10'],
            ['https://example.com/1939'],
        ];
    }


    #[DataProvider('notAnArticleUrlProvider')]
    public function testNonArticleUrlsAreIgnored(string $url) : void
    {
        /** @var ArticleUrlGenerator $articleUrlGenerator */
        $articleUrlGenerator = static::getService(ArticleUrlGenerator::class);

        $this->assertNull($articleUrlGenerator->extractIdFromUrl($url), "$url is not a link to an article");
        $this->assertFalse($articleUrlGenerator->isUrl($url), "isUrl() and extractIdFromUrl() disagree on $url");
    }


    public function testShortLinksAreStoredAsPlaceholdersAndShownAsCanonicalUrls() : void
    {
        /** @var ArticleEditor $editor */
        $editor = static::getService(Factory::class)->createArticleEditor();

        $editor
            ->setTitle('Article link probe ' . bin2hex(random_bytes(4)))
            ->setBody(
                '<p>Leggi <a href="https://turbolab.it/' . Article::ID_QUALITY_TEST . '">la guida</a> ' .
                'e <a href="/' . Article::ID_FORUM_RULES . '">il regolamento</a>.</p>'
            );

        $stored = (string)$editor->getEntity()->getBody();

        foreach([Article::ID_QUALITY_TEST, Article::ID_FORUM_RULES] as $articleId) {
            $this->assertStringContainsString('href="==###contenuto::id::' . $articleId . '###=="', $stored);
        }

        $this->assertStringNotContainsString('turbolab.it/', $stored, 'A short link was stored as a raw URL');

        // display: each placeholder becomes the current canonical URL of its article
        $domDoc = new DOMDocument();
        $domDoc->loadHTML('<?xml encoding="UTF-8"><body>' . $editor->getBodyForDisplay() . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        $arrHrefs = [];
        foreach( $domDoc->getElementsByTagName('a') as $a ) {
            $arrHrefs[] = $a->getAttribute('href');
        }

        $this->assertSame([
            static::getArticle(Article::ID_QUALITY_TEST)->getUrl(),
            static::getArticle(Article::ID_FORUM_RULES)->getUrl(),
        ], $arrHrefs);
    }
}
