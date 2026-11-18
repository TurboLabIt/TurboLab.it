<?php
namespace App\Service;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;


// 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md
abstract class HtmlProcessorBase
{
    /**
     * The elements a <p> can't contain. At the top level of a body, what lies between two of them (text, inline
     * elements) is a run of inline content, which parseHTML() puts in a <p> when it holds text: see implyParagraphs()
     */
    const array BLOCK_TAGS = [
        'address', 'article', 'aside', 'blockquote', 'caption', 'center', 'col', 'colgroup', 'dd', 'details', 'dialog',
        'dir', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'header', 'hgroup', 'hr', 'li', 'listing', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'search', 'section',
        'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul', 'xmp',
    ];


    public function __construct(protected Factory $factory) {}


    public static function decode(?string $text) : ?string
    {
        if( empty($text) ) { return $text; }

        // the most aggressive version I can concoct
        $cleanText = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($cleanText);
    }


    public static function encode(?string $text) : ?string
    {
        // prevent double-encoding
        $decodedText = static::decode($text);

        if( empty($text) ) { return $text; }

        $encodedText = htmlentities($decodedText, ENT_QUOTES, 'UTF-8');
        return trim($encodedText);
    }


    public function getLegacyEntities() : array
    {
        $arrAllEntities = get_html_translation_table(HTML_ENTITIES);
        return array_diff_key($arrAllEntities, array_flip(Dictionary::HTML_SPECIAL_CHARS) );
    }


    public function convertLegacyEntitiesToUtf8Chars(?string $text) : ?string
    {
        if( empty($text) ) {
            return $text;
        }

        $arrLegacyEntities = $this->getLegacyEntities();
        return str_replace($arrLegacyEntities, array_keys($arrLegacyEntities), $text);
    }


    /**
     * $entityEncodeReplacements: when the input is raw HTML still to be purified, the replacement for a
     * quote-family char must be its entity (&apos;/&quot;) — a raw " dropped inside a double-quoted
     * attribute would break the tag at string level. HTMLPurifier then decodes those entities wherever
     * they sit in text. On plain text (titles) or on already-purified HTML, pass false: the DB must
     * store the raw char, never the entity (see docs/encoding.md).
     */
    public function replaceUndesiredHtmlEntities(?string $text, bool $entityEncodeReplacements = true) : ?string
    {
        if( empty($text) ) {
            return $text;
        }

        $arrEntities = [
            // convert single-quote entity from HTML4 to HTML5;
            '&#039;' => $entityEncodeReplacements ? '&apos;' : "'",
        ];

        foreach(Dictionary::FINE_TYPOGRAPHY_CHARS as $fineChar => $standardChar) {

            $fineEntityHtml5    = htmlentities($fineChar, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $fineEntityHtml4    = htmlentities($fineChar, ENT_QUOTES | ENT_HTML401, 'UTF-8');

            if($entityEncodeReplacements) {
                $standardChar = htmlentities($standardChar, ENT_QUOTES | ENT_HTML5 , 'UTF-8');
            }

            $arrEntities[$fineEntityHtml5]  = $standardChar;
            $arrEntities[$fineEntityHtml4]  = $standardChar;
            $arrEntities[$fineChar]         = $standardChar;
        }

        return str_replace( array_keys($arrEntities), $arrEntities, $text);
    }


    /**
     * Runs $fxProcessChunk on everything BUT the <pre>…</pre> blocks: code is whitespace- and
     * character-significant, so the text fixers (double-space collapse, accent fixes, legacy
     * formatting repairs) must leave it byte-identical. HTMLPurifier is NOT one of these callers:
     * sanitization always sees the whole document, or <pre> content would bypass it.
     */
    public function applyOutsidePreBlocks(string $text, callable $fxProcessChunk) : string
    {
        if( stripos($text, '<pre') === false ) {
            return $fxProcessChunk($text);
        }

        $arrChunks = preg_split('~(<pre\b.*?</pre>)~is', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if( $arrChunks === false ) {
            return $fxProcessChunk($text);
        }

        foreach($arrChunks as $i => $chunk) {

            if( preg_match('~^<pre\b~i', $chunk) === 1 ) {
                continue;
            }

            $arrChunks[$i] = $fxProcessChunk($chunk);
        }

        return implode('', $arrChunks);
    }


    public function parseHTML(string $text) : DOMDocument|bool
    {
        $domDoc = new DOMDocument();

        // pretty output https://www.php.net/manual/en/class.domdocument.php
        // doesn't work
        // $domDoc->preserveWhiteSpace = false;

        /**
         * Workaround for unescaped &, as in HTML5, break the parser
         * DOMDocument::loadHTML(): htmlParseEntityRef: no name in Entity
         * https://stackoverflow.com/a/26853864/1204976
         */
        libxml_use_internal_errors(true);

        /**
         * xml encoding="utf-8":
         *     UTF8 corruption
         *     https://stackoverflow.com/a/8218649/1204976
         *
         * LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD:
         *     Prevent <html><body> wrapper in output
         *     https://stackoverflow.com/a/22490902/1204976
         *
         * <body>…</body>:
         *     libxml 2.14+ (its rewritten HTML5 parser) drops every text node that has no parent element: with
         *     NOIMPLIED, the text outside the top-level elements, which the bodies imported from TLI1 have plenty
         *     of. The old HTML4 parser put that text in an implied <p>. From June to November 2026 the article
         *     pages hid it, and so did the editor, whose save then deleted it. The explicit <body> gives it a
         *     parent, implyParagraphs() does what the old parser did, and the body's children go back to the top
         *     level, where every caller expects them. A </body> or </html> in the text would close it early:
         *     neither means anything in an article body, so they go
         */
        $text   = preg_replace('~</(?:body|html)\s*>~i', '', $text) ?? $text;
        $result = $domDoc->loadHTML('<?xml encoding="utf-8" ?><body>' . $text . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        if( $result === false ) {
            return false;
        }

        // Remove the leading xml-encoding prefix node injected above.
        // Old libxml parsed it as a processing instruction; libxml 2.14+ (the new HTML5 parser)
        // parses it as a comment instead, so we must strip both forms or it leaks into the output.
        // https://www.php.net/manual/en/domdocument.loadhtml.php#95251
        foreach( iterator_to_array($domDoc->childNodes) as $item ) {

            $isXmlPrefixHack =
                $item->nodeType === XML_PI_NODE ||
                ( $item->nodeType === XML_COMMENT_NODE && str_starts_with((string)$item->nodeValue, '?xml') );

            if($isXmlPrefixHack) {
                $domDoc->removeChild($item); // remove hack
            }
        }

        foreach( iterator_to_array($domDoc->childNodes) as $item ) {

            if( !($item instanceof DOMElement) || $item->tagName != 'body' ) {
                continue;
            }

            $this->implyParagraphs($domDoc, $item);

            foreach( iterator_to_array($item->childNodes) as $child ) {
                $domDoc->insertBefore($child, $item);
            }

            $domDoc->removeChild($item);
            break;
        }

        $domDoc->encoding = 'UTF-8';
        return $domDoc;
    }


    /**
     * What libxml's old HTML4 parser did by itself: the text between the top-level blocks goes in a <p>, with the
     * inline elements next to it (a run of inline content that holds text). A run with no text, such as an image
     * between two paragraphs, stays as it is, and the whitespace around it goes, as it always did.
     */
    protected function implyParagraphs(DOMDocument $domDoc, DOMElement $parent) : void
    {
        $arrRun = [];
        foreach( iterator_to_array($parent->childNodes) as $node ) {

            if( $node instanceof DOMElement && in_array(strtolower($node->tagName), static::BLOCK_TAGS) ) {

                $this->wrapRunInParagraph($domDoc, $parent, $arrRun);
                $arrRun = [];
                continue;
            }

            $arrRun[] = $node;
        }

        $this->wrapRunInParagraph($domDoc, $parent, $arrRun);
    }


    /**
     * @param DOMNode[] $arrRun consecutive children of $parent, none of them a block
     */
    protected function wrapRunInParagraph(DOMDocument $domDoc, DOMElement $parent, array $arrRun) : void
    {
        // HTML's whitespace, without the non-breaking space: a run holding one alone is text, as it was for libxml
        $fxIsBlank = fn(DOMNode $node) : bool => preg_match('/[^ \t\n\r\f]/', $node->textContent) !== 1;

        $hasText = false;
        foreach($arrRun as $node) {

            if( ($node instanceof DOMText || $node instanceof DOMElement) && !$fxIsBlank($node) ) {
                $hasText = true;
                break;
            }
        }

        if( !$hasText ) {

            foreach($arrRun as $node) {

                if( $node instanceof DOMText ) {
                    $parent->removeChild($node);
                }
            }

            return;
        }

        // the new paragraph starts and ends with its first and last word
        while( reset($arrRun) instanceof DOMText && $fxIsBlank(reset($arrRun)) ) {
            $parent->removeChild( array_shift($arrRun) );
        }

        while( end($arrRun) instanceof DOMText && $fxIsBlank(end($arrRun)) ) {
            $parent->removeChild( array_pop($arrRun) );
        }

        $first = reset($arrRun);
        if( $first instanceof DOMText ) {
            $first->data = ltrim($first->data, " \t\n\r\f");
        }

        $last = end($arrRun);
        if( $last instanceof DOMText ) {
            $last->data = rtrim($last->data, " \t\n\r\f");
        }

        $paragraph = $domDoc->createElement('p');
        $parent->insertBefore($paragraph, $first);

        foreach($arrRun as $node) {
            $paragraph->appendChild($node);
        }
    }


    protected function renderDomDocAsHTML(DOMDocument $domDoc, ?DOMNode $singleNodeOnly = null) : string
    {
        // pretty output https://www.php.net/manual/en/class.domdocument.php
        // doesn't work, likely due to "whitespace nodes being created by the load
        // https://www.php.net/manual/en/domdocument.savexml.php#76867
        //$domDoc->formatOutput = true;

        if( empty($singleNodeOnly) ) {

            // Why does DOMDocument::saveHTML()'s behavior differ in encoding UTF-8 as entities in style & script elements?
            // https://stackoverflow.com/q/51660286/1204976
            // https://stackoverflow.com/questions/59938856/why-do-these-two-domdocument-functions-behave-differently/59940487#comment106002356_59940487
            $html = $domDoc->saveHTML($domDoc);

        } else {

            $html = $domDoc->saveHTML($singleNodeOnly);
        }

        return $this->convertLegacyEntitiesToUtf8Chars($html);
    }
}
