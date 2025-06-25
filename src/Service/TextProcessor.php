<?php
namespace App\Service;


class TextProcessor
{
    protected ?int $spotlightId = null;
    protected ?string $abstract = null;


    public function __construct(protected HtmlProcessorForStorage $htmlProcessor) {}


    /**
     * Don't invoke this method directly! Use `$articleEditor->setTitle($title)` if possible
     *
     * @see ArticleEditor
     * @see ArticleEditorTest
     */
    public function processRawInputTitleForStorage(string $title) : string
    {
        $processing = $this->cleanTextBeforeStorage($title);

        // convert back as many &entities; as possible into their corresponding chars
        return html_entity_decode($processing, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }


    /**
     * Don't invoke this method directly! Use `$articleEditor->setBody($body)` if possible
     *
     * @see ArticleEditor
     * @see ArticleEditorTest
     */
    public function processRawInputBodyForStorage(string $body) : string
    {
        $processing = $this->cleanTextBeforeStorage($body);

        $processing = $this->htmlProcessor->convertLegacyEntitiesToUtf8Chars($processing);
        $processing = $this->htmlProcessor->fixFormattingErrors($processing);
        $processing = $this->htmlProcessor->purify($processing);
        $processing = $this->htmlProcessor->removeAltAttribute($processing);

        $processing = $this->htmlProcessor->processArticleBody($processing);

        $finalHtml  = $this->cleanTextBeforeStorage($processing);

        $this->spotlightId  = $this->htmlProcessor->getSpotlightId();
        $this->abstract     = $this->htmlProcessor->getAbstract();

        return trim($finalHtml);
    }



    public function processTli1BodyForStorage(string $body) : string
    {
        $processing = $this->cleanTextBeforeStorage($body);

        $processing = $this->htmlProcessor->convertLegacyEntitiesToUtf8Chars($processing);
        $processing = $this->htmlProcessor->fixFormattingErrors($processing);

        // can't purify here (YouTube iframe with src="###youtube.." gets removed)
        //$processing = $this->htmlProcessor->purify($processing);

        // TLI1 has <iframe src="==###youtube::code::0k5nmwQx2j8###=="></iframe> ➡ TLI2 requires just the placeholder
        $processing = preg_replace('/<iframe src="(==###youtube::code::[^#]+###==)"><\/iframe>/i', '$1', $processing);

        $finalHtml  = $this->htmlProcessor->removeAltAttribute($processing);

        return trim($finalHtml);
    }


    protected function cleanTextBeforeStorage(string $text) : string
    {
        // Remove null bytes
        $processing = str_replace("\0", "", $text);

        // replace "fine typography" with their corresponding base equivalents
        $processing = $this->htmlProcessor->replaceUndesiredHtmlEntities($processing);

        // no double-spaces
        $processing = $this->removeDoubleChars($processing);

        return trim($processing);
    }


    protected function removeDoubleChars(string $text, string $char = ' ') : string
    {
        return preg_replace("/$char{2,}/", ' ', $text);
    }


    public function getSpotlightId() : ?int { return $this->spotlightId; }

    public function getAbstract() : ?string { return $this->abstract; }
}
