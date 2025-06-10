<?php
namespace App\Service;

use App\Service\Cms\HtmlProcessorReverse;


class TextProcessor
{
    public function __construct(protected HtmlProcessorReverse $htmlProcessorReverse) {}


    /**
     * Don't invoke this method directly! Use `$articleEditor->setTitle($title)` if possible
     *
     * Input: `Come mostrare un messaggio con JS: <script>alert("bòòm");</script>` 💡 ENTITY-ENCODED OR NOT doesn't matter
     * Store: `Come mostrare un messaggio con JS: &lt;script&gt;alert(&quot;bòòm&quot;);&lt;/script&gt;`
     *
     * @see ArticleEditor
     */
    public function processRawInputTitleForStorage(string $title) : string
    {
        // convert back as many &entities; as possible into their corresponding chars
        $normalized = html_entity_decode($title, ENT_QUOTES  | ENT_HTML5, 'UTF-8');

        $normalized = $this->cleanTextBeforeStorage($normalized);

        // replace two or more consecutive spaces with one
        $normalized = preg_replace('/ {2,}/', ' ', $normalized);

        return $this->htmlProcessorReverse->convertCharsToHtmlEntities($normalized);
    }


    protected function cleanTextBeforeStorage(string $text) : string
    {
        // replace U+00A0 : NO-BREAK SPACE [NBSP] with an actual goddamn space
        $normalized = preg_replace('/\xc2\xa0/', ' ', $text);

        // replace "fine typography" with their corresponding base equivalents
        $normalized =
            str_ireplace( array_keys(Dictionary::FINE_TYPOGRAPHY_CHARS), Dictionary::FINE_TYPOGRAPHY_CHARS, $normalized);

        return trim($normalized);
    }
}
