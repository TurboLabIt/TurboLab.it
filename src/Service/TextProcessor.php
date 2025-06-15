<?php
namespace App\Service;


class TextProcessor
{
    public function __construct(protected HtmlProcessorForStorage $htmlProcessor) {}


    /**
     * Don't invoke this method directly! Use `$articleEditor->setTitle($title)` if possible
     *
     * @see ArticleEditor
     * @see ArticleEditorTest
     */
    public function processRawInputTitleForStorage(string $title) : string
    {
        $normalized = $this->cleanTextBeforeStorage($title);

        // convert back as many &entities; as possible into their corresponding chars
        $normalized = html_entity_decode($normalized, ENT_QUOTES  | ENT_HTML5, 'UTF-8');

        $normalized = $this->removeDoubleChars($normalized);

        return htmlspecialchars($normalized, ENT_QUOTES  | ENT_HTML5, 'UTF-8');
    }


    /**
     * Don't invoke this method directly! Use `$articleEditor->setBody($body)` if possible
     *
     * @see ArticleEditor
     * @see ArticleEditorTest
     */
    public function processRawInputBodyForStorage(string $body) : string
    {
        $normalized = $this->cleanTextBeforeStorage($body);

        $normalized = $this->htmlProcessor->convertLegacyEntitiesToUtf8Chars($normalized);
        $normalized = $this->htmlProcessor->removeExternalImages($normalized);
        $normalized = $this->htmlProcessor->purify($normalized);
        $normalized = $this->htmlProcessor->removeAltAttribute($normalized);
        $normalized = $this->removeDoubleChars($normalized);

        $finalHtml  = $this->htmlProcessor->processArticleBody($normalized);

        return trim($finalHtml);
    }


    protected function cleanTextBeforeStorage(string $text) : string
    {
        // replace U+00A0 : NO-BREAK SPACE [NBSP] with an actual goddamn space
        $normalized = preg_replace('/\xc2\xa0/', ' ', $text);

        // replace "fine typography" with their corresponding base equivalents
        $normalized = $this->htmlProcessor->convertFineTypographyEntitiesToStandardHtmlEntities($normalized);

        return trim($normalized);
    }


    protected function removeDoubleChars(string $text, string $char = ' ') : string
    {
        return preg_replace("/$char{2,}/", ' ', $text);
    }
}
