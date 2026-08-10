<?php
namespace App\Tests\Security;

use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Tests\BaseT;


/**
 * The article body allowlist is deliberately minimal (no h3, no tables) so that authors can't rebuild
 * "artistic" layouts — a 1x2 table faking two columns being the classic one. An article with `allowExtendedHtml`
 * is the single exception: it unlocks a handful of extra tags.
 *
 * The gate on obtaining the flag lives in ArticleNewSponsorFormatTest. This file guards what the flag
 * actually buys, and — above all — that it stays confined to the article that owns it.
 *
 * @see \App\Service\HtmlProcessorForStorage::ALLOWED_TAGS
 * @see \App\Service\HtmlProcessorForStorage::ALLOWED_TAGS_EXTENDED
 */
class ArticleBodyExtendedHtmlTest extends BaseT
{
    private const string BODY_H3    = '<h3>Tre</h3><h4>Quattro</h4><h5>Cinque</h5><h6>Sei</h6>';
    private const string BODY_TABLE =
        '<table border="1" class="pricing"><caption>Listino</caption>' .
        '<thead><tr><th>Piano</th><th>Prezzo</th></tr></thead>' .
        '<tbody><tr><td>Base</td><td>10 euro</td></tr>' .
        '<tr><td colspan="2">Offerta</td></tr></tbody></table>';


    public function testOrdinaryArticleCannotUseExtendedTags() : void
    {
        $body = $this->processBody(self::BODY_H3 . self::BODY_TABLE, false);

        foreach(['<h3', '<h4', '<h5', '<h6'] as $heading) {
            $this->assertStringNotContainsString($heading, $body, "An ordinary article kept a $heading>");
        }

        foreach(['<table', '<thead', '<tbody', '<tr', '<th', '<td', '<caption'] as $tag) {
            $this->assertStringNotContainsString(
                $tag, $body,
                "An ordinary article kept a $tag>. The extended allowlist is leaking to articles without " .
                "allowExtendedHtml: every author can now fake multi-column layouts with tables"
            );
        }
    }


    public function testSponsorArticleCanUseExtendedTags() : void
    {
        $body = $this->processBody(self::BODY_H3 . self::BODY_TABLE, true);

        $expectations = [
            '<h3>Tre</h3>', '<h4>Quattro</h4>', '<h5>Cinque</h5>', '<h6>Sei</h6>',
            '<caption>Listino</caption>', '<th>Piano</th>', '<td>Base</td>', 'colspan="2"'
        ];

        foreach($expectations as $expected) {
            $this->assertStringContainsString(
                $expected, $body,
                "A sponsored article lost \"$expected\": the extended allowlist no longer covers the whole " .
                "table markup CKEditor produces, so pasted tables are silently mangled on save"
            );
        }
    }


    /**
     * The extended allowance is a short, fixed list — not "anything goes" on sponsored posts.
     */
    public function testExtendedTagsDoNotUnlockEverythingElse() : void
    {
        $body = $this->processBody(
            '<div>div</div><blockquote>quote</blockquote><h1>h1</h1><u>u</u><script>alert(1)</script>' .
            '<table class="pricing" border="1" onclick="alert(1)"><tr><td style="color:red">cell</td></tr></table>',
            true
        );

        // h1 stays out at both levels: the page already has one, the article title
        foreach(['<div', '<blockquote', '<h1', '<u>', '<script', 'onclick', 'style=', 'class=', 'border='] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden, $body,
                "allowExtendedHtml let \"$forbidden\" through: it must add table markup and h3-h6, nothing else"
            );
        }
    }


    /**
     * Anti-regression for the purifier cache. HTMLPurifier is expensive to build, so it is memoized — but
     * the allowlist is now per-article, so a single memoized instance would let the FIRST body processed
     * dictate the allowlist of every body processed after it in the same PHP process. Silent wrong output,
     * no error. Flipping the flag between two setBody() calls is not a production flow: it is the cheapest
     * way to pin the cache down.
     */
    public function testTheExtendedAllowlistDoesNotLeakThroughTheCachedPurifier() : void
    {
        $editor = static::getService(Factory::class)->createArticleEditor();
        $editor->setTitle('Extended HTML cache probe ' . bin2hex(random_bytes(4)));

        // warm the "extended" purifier first...
        $editor->allowExtendedHtml(true)->setBody(self::BODY_TABLE);
        $this->assertStringContainsString('<table', $editor->getBody());

        // ...then the very same processor must still refuse a table for an ordinary article
        $editor->allowExtendedHtml(false)->setBody(self::BODY_TABLE);
        $this->assertStringNotContainsString(
            '<table', $editor->getBody(),
            'The purifier built for a sponsored article was reused for an ordinary one: HtmlProcessorForStorage ' .
            'is memoizing a single HTMLPurifier again instead of keying it by the allowlist'
        );

        // ...and the opposite order must not lock a sponsored article out of its tables
        $editor->allowExtendedHtml(true)->setBody(self::BODY_TABLE);
        $this->assertStringContainsString(
            '<table', $editor->getBody(),
            'The purifier built for an ordinary article was reused for a sponsored one'
        );
    }


    /**
     * Nothing is persisted: setBody() only rewrites the in-memory entity, save() is never called.
     */
    private function processBody(string $rawBody, bool $allowExtendedHtml) : string
    {
        /** @var ArticleEditor $editor */
        $editor = static::getService(Factory::class)->createArticleEditor();

        $editor
            ->setTitle('Extended HTML probe ' . bin2hex(random_bytes(4)))
            ->allowExtendedHtml($allowExtendedHtml)
            ->setBody($rawBody);

        return (string)$editor->getBody();
    }
}
