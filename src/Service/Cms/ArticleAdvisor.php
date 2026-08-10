<?php
namespace App\Service\Cms;

use App\Service\HtmlProcessorBase;
use App\Service\HtmlProcessorForDisplay;
use DOMDocument;
use DOMElement;
use DOMXPath;


/**
 * Pre-publication advisor ("Verifica" toolbar button, app_editor_article_advise).
 * Runs soft checks on a saved article and reports anything unusual to the author.
 * Advice is a hint to a human, never an error: the article stays saveable and publishable as-is.
 */
class ArticleAdvisor
{
    const string CHECK_GOOGLE_DOCS  = 'google-docs-reference';

    const string LEVEL_WARNING      = 'warning';

    const string GOOGLE_DOCS_NEEDLE = 'docs.google.com/document/';

    // plain-text chars kept on each side of a match when building its excerpt
    const int EXCERPT_RADIUS = 60;


    public function __construct(protected HtmlProcessorForDisplay $htmlProcessor) {}


    /**
     * @return array[] one item per triggered check: {id, level, title, message, arrMatches[]{excerpt, url}}
     */
    public function advise(Article $article) : array
    {
        $arrCheckResults = [
            $this->checkGoogleDocsReferences($article)
        ];

        $arrAdvice = [];
        foreach($arrCheckResults as $advice) {

            if( !empty($advice) ) {
                $arrAdvice[] = $advice;
            }
        }

        return $arrAdvice;
    }


    protected function checkGoogleDocsReferences(Article $article) : ?array
    {
        $body = (string)$article->getBody();
        if( mb_stripos($body, static::GOOGLE_DOCS_NEEDLE) === false ) {
            return null;
        }

        $arrMatches = $this->findInBody($body, static::GOOGLE_DOCS_NEEDLE);

        $num = count($arrMatches);
        $message =
            $num == 1
                ? "Nel testo c'è un riferimento a un documento Google Docs («docs.google.com/document/…»). " .
                  "Non è necessariamente un errore, ma è insolito: spesso è un residuo della bozza di lavoro. " .
                  "Controlla che il collegamento sia davvero voluto e raggiungibile dai lettori."
                : "Nel testo ci sono $num riferimenti a documenti Google Docs («docs.google.com/document/…»). " .
                  "Non è necessariamente un errore, ma è insolito: spesso sono residui della bozza di lavoro. " .
                  "Controlla che i collegamenti siano davvero voluti e raggiungibili dai lettori.";

        return [
            "id"            => static::CHECK_GOOGLE_DOCS,
            "level"         => static::LEVEL_WARNING,
            "title"         => "Riferimenti a Google Docs",
            "message"       => $message,
            "arrMatches"    => $arrMatches
        ];
    }


    /**
     * Locate every occurrence of $needle in the body — in link hrefs as well as in plain text —
     * returning, for each one, a plain-text excerpt the author can use to find it in the editor.
     *
     * @return array[] [ {excerpt: string, url: ?string}, ... ]
     */
    protected function findInBody(string $body, string $needle) : array
    {
        $domDoc = $this->htmlProcessor->parseHTML($body);
        if( !($domDoc instanceof DOMDocument) ) {
            return $this->rawMatches($body, $needle);
        }

        $arrMatches = [];

        // 1) links: the needle hides in the href, the author only sees the label
        $xpath = new DOMXPath($domDoc);
        foreach( iterator_to_array( $xpath->query('//a[@href]') ?: [] ) as $link ) {

            if( !($link instanceof DOMElement) ) {
                continue;
            }

            $href = $link->getAttribute('href');
            if( mb_stripos($href, $needle) === false ) {
                continue;
            }

            $arrMatches[] = [
                "excerpt"   => $this->buildLinkExcerpt($link),
                "url"       => $href
            ];

            // the matched link must not reach the text pass, or the same instance would be
            // reported again (AutoLink stores a pasted URL as <a href="URL">URL</a>: label and
            // href both match). A label free of the needle stays: it's context around the text hits
            $label = $link->textContent;
            if( mb_stripos($label, $needle) === false ) {

                $link->parentNode?->replaceChild($domDoc->createTextNode($label), $link);

            } else {

                $link->parentNode?->removeChild($link);
            }
        }

        // 2) plain text: whatever the link pass didn't consume
        foreach( $this->extractExcerpts( $this->domToPlainText($domDoc), $needle ) as $excerpt ) {
            $arrMatches[] = ["excerpt" => $excerpt, "url" => null];
        }

        if( empty($arrMatches) ) {
            // the caller's fast path saw the needle, but neither DOM pass could place it
            // (e.g. inside an unforeseen attribute): a raw-source excerpt beats staying silent
            return $this->rawMatches($body, $needle);
        }

        return $arrMatches;
    }


    /** @return array[] */
    protected function rawMatches(string $body, string $needle) : array
    {
        return array_map(
            fn(string $excerpt) => ["excerpt" => $excerpt, "url" => null],
            $this->extractExcerpts($body, $needle)
        );
    }


    // the label alone can be too generic ("clicca qui"): pad it with the text around the link
    protected function buildLinkExcerpt(DOMElement $link) : string
    {
        $label = $this->normalizeWhitespace($link->textContent);
        if( $label === '' ) {
            return "(link senza testo)";
        }

        $parentText = $this->normalizeWhitespace($link->parentNode->textContent ?? '');
        $labelPos   = mb_strpos($parentText, $label);

        if( $labelPos === false ) {
            return $label;
        }

        return $this->cutExcerpt($parentText, $labelPos, mb_strlen($label));
    }


    /** @return string[] one excerpt per occurrence of $needle in $text */
    protected function extractExcerpts(string $text, string $needle) : array
    {
        $text = $this->normalizeWhitespace($text);

        $arrExcerpts    = [];
        $offset         = 0;

        while( ($position = mb_stripos($text, $needle, $offset)) !== false ) {

            $arrExcerpts[]  = $this->cutExcerpt($text, $position, mb_strlen($needle));
            $offset         = $position + mb_strlen($needle);
        }

        return $arrExcerpts;
    }


    protected function cutExcerpt(string $text, int $position, int $matchLength) : string
    {
        $start  = max(0, $position - static::EXCERPT_RADIUS);
        $end    = min(mb_strlen($text), $position + $matchLength + static::EXCERPT_RADIUS);

        return
            ( $start > 0 ? '…' : '' ) .
            mb_substr($text, $start, $end - $start) .
            ( $end < mb_strlen($text) ? '…' : '' );
    }


    // without the separator, block boundaries would glue words together ("…fine.Inizio…")
    protected function domToPlainText(DOMDocument $domDoc) : string
    {
        $html = (string)$domDoc->saveHTML();
        $html = (string)preg_replace('~</[^>]+>~', '$0 ', $html);

        return (string)HtmlProcessorBase::decode( strip_tags($html) );
    }


    protected function normalizeWhitespace(string $text) : string
    {
        return trim( (string)preg_replace('~\s+~u', ' ', $text) );
    }
}
