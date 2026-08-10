<?php
namespace App\Tests\Editor;

use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * Headings arriving from Word / Google Docs carry two kinds of authoring noise:
 *
 *  - formatting wrapped around the whole heading (`<h2><strong>…`), which says nothing a heading
 *    doesn't already say. This used to be handled by two string replacements in fixFormattingErrors()
 *    that only covered `<h2><strong>`, missed every other level and every other tag, tripped over
 *    attributes, and silently dropped the bold from a *partially* bold heading;
 *  - a numbered heading, which Word exports as a heading inside its own one-item list. A run of them
 *    becomes N separate lists that all restart at "1.".
 *
 * Both are now normalised on the DOM, on the way in.
 *
 * @see \App\Service\HtmlProcessorForStorage::unwrapHeadingFormatting()
 * @see \App\Service\HtmlProcessorForStorage::unwrapSingleHeadingLists()
 */
class HeadingNormalizationTest extends BaseT
{
    public static function headingProvider() : array
    {
        return [
            // bold and italics go, at every level, whole or partial, at any depth
            'h2 bold'               => ['<h2><strong>Titolo</strong></h2>',             '<h2>Titolo</h2>'],
            'h3 bold'               => ['<h3><strong>Titolo</strong></h3>',             '<h3>Titolo</h3>'],
            'h6 bold'               => ['<h6><strong>Titolo</strong></h6>',             '<h6>Titolo</h6>'],
            'attribute on the tag'  => ['<h2 id="x"><strong>Titolo</strong></h2>',      '<h2>Titolo</h2>'],
            'leading bold'          => ['<h2><strong>Grassetto</strong> e resto</h2>',  '<h2>Grassetto e resto</h2>'],
            'trailing bold'         => ['<h2>Resto e <strong>grassetto</strong></h2>',  '<h2>Resto e grassetto</h2>'],
            'bold inside a link'    => ['<h2><a href="https://e.com"><strong>L</strong></a></h2>', '<h2><a href="https://e.com">L</a></h2>'],
            'h2 italic'             => ['<h2><em>Titolo</em></h2>',                     '<h2>Titolo</h2>'],
            'h4 italic'             => ['<h4><em>Titolo</em></h4>',                     '<h4>Titolo</h4>'],
            'partial italic'        => ['<h3>La <em>supply chain</em> oggi</h3>',       '<h3>La supply chain oggi</h3>'],
            'italic inside code'    => ['<h2><code><em>cmd</em></code></h2>',           '<h2><code>cmd</code></h2>'],
            'both, mixed'           => ['<h4><strong>A</strong> b <em>C</em></h4>',     '<h4>A b C</h4>'],
            'nested formatting'     => ['<h2><strong><em>Titolo</em></strong></h2>',    '<h2>Titolo</h2>'],

            // ...strikethrough stays: it is semantic, not decoration
            'h5 strike kept'        => ['<h5><s>Titolo</s></h5>',                       '<h5><s>Titolo</s></h5>'],
            'partial strike kept'   => ['<h3>Prezzo <s>19</s> 9 euro</h3>',             '<h3>Prezzo <s>19</s> 9 euro</h3>'],

            // non-presentational children are never touched
            'link kept'             => ['<h2><a href="https://example.com">L</a></h2>', '<h2><a href="https://example.com">L</a></h2>'],
            'code kept'             => ['<h2><code>cmd</code></h2>',                    '<h2><code>cmd</code></h2>'],

            // and the rule is scoped to headings: body copy keeps its formatting
            'paragraph untouched'   => ['<p><strong>Grassetto</strong> e <em>corsivo</em></p>', '<p><strong>Grassetto</strong> e <em>corsivo</em></p>'],
            'list item untouched'   => ['<ul><li><strong>Voce</strong> in grassetto</li></ul>', '<ul><li><strong>Voce</strong> in grassetto</li></ul>'],

            // a heading alone in a one-item list loses the list
            'ol wrapping a heading' => ['<ol><li><h4>Passo</h4></li></ol>',             '<h4>Passo</h4>'],
            'ul wrapping a heading' => ['<ul><li><h2>Titolo</h2></li></ul>',            '<h2>Titolo</h2>'],

            // the two passes compose: the list goes, and so does the heading's own formatting
            'a run of them'         => [
                '<ol><li><h4><em>Uno</em></h4></li></ol><ul><li>dettagli</li></ul><ol><li><h4><strong>Due</strong></h4></li></ol>',
                '<h4>Uno</h4><ul><li>dettagli</li></ul><h4>Due</h4>'
            ],

            // ...but real lists are left alone
            'real list kept'        => ['<ol><li>uno</li><li>due</li></ol>',            '<ol><li>uno</li><li>due</li></ol>'],
            'one plain item kept'   => ['<ol><li>solo testo</li></ol>',                 '<ol><li>solo testo</li></ol>'],
            'heading plus text kept'=> ['<ol><li><h4>Passo</h4> con coda</li></ol>',    '<ol><li><h4>Passo</h4> con coda</li></ol>'],
        ];
    }


    #[DataProvider('headingProvider')]
    public function testHeadingsAreNormalizedOnStorage(string $input, string $expected) : void
    {
        /** @var ArticleEditor $editor */
        $editor = static::getService(Factory::class)->createArticleEditor();

        // h3-h6 only exist on articles allowed the extended HTML, so exercise the same flag
        $editor
            ->setTitle('Heading normalization probe ' . bin2hex(random_bytes(4)))
            ->allowExtendedHtml(true)
            ->setBody($input);

        $this->assertSame($expected, $editor->getBody());
    }


    /**
     * Editorial decision: no heading may carry <strong> or <em> at any level, whole or partial — a heading
     * is already emphatic. <s> survives untouched: strikethrough is semantic, not decoration.
     */
    public function testEveryHeadingLevelLosesBoldAndItalicButKeepsStrikethrough() : void
    {
        foreach(['h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {

            foreach(['strong', 'em'] as $noise) {

                $editor = static::getService(Factory::class)->createArticleEditor();
                $editor
                    ->setTitle('Heading formatting probe ' . bin2hex(random_bytes(4)))
                    ->allowExtendedHtml(true)
                    ->setBody("<$tag>Prima <$noise>formattato</$noise> dopo</$tag>");

                $this->assertSame(
                    "<$tag>Prima formattato dopo</$tag>", $editor->getBody(),
                    "A <$tag> kept a partial <$noise>: it must go from headings whether or not it covers " .
                    "the whole heading"
                );
            }

            $editor = static::getService(Factory::class)->createArticleEditor();
            $editor
                ->setTitle('Heading formatting probe ' . bin2hex(random_bytes(4)))
                ->allowExtendedHtml(true)
                ->setBody("<$tag>Prima <s>barrato</s> dopo</$tag>");

            $this->assertSame(
                "<$tag>Prima <s>barrato</s> dopo</$tag>", $editor->getBody(),
                "A <$tag> lost its <s>: strikethrough does not belong in HEADING_NOISE_TAGS"
            );
        }
    }
}
