<?php
namespace App\Service;


class TextProcessor
{
    protected ?int $spotlightId = null;
    protected ?string $abstract = null;
    protected array $fileIds = [];


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
        return HtmlProcessorBase::decode($processing);
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

        // No alt-stripping step here: processArticleBody() rebuilds every <img> as a placeholder
        // without an alt (HTMLPurifier only ever emits an empty alt="" on images, and it is
        // discarded on rebuild). Never regex-rewrite the purified HTML to remove alt — that was the
        // root cause of security-audit finding #26 (it deleted across tag boundaries).
        $processing = $this->htmlProcessor->processArticleBody($processing);

        $finalHtml  = $this->cleanTextBeforeStorage($processing);

        $this->spotlightId  = $this->htmlProcessor->getSpotlightId();
        $this->abstract     = $this->htmlProcessor->getAbstract();
        $this->fileIds      = $this->htmlProcessor->getFileIds();

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

    public function getFileIds() : array { return $this->fileIds; }
}
