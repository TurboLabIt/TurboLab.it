<?php
namespace App\Service;

use DOMDocument;
use DOMNode;


// 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md
abstract class HtmlProcessorBase
{
    public function __construct(protected Factory $factory) {}


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


    public function replaceUndesiredHtmlEntities(?string $text) : ?string
    {
        if( empty($text) ) {
            return $text;
        }

        $arrEntities = [
            // convert single-quote entity from HTML4 to HTML5;
            '&#039;' => '&apos;',
        ];

        foreach(Dictionary::FINE_TYPOGRAPHY_CHARS as $fineChar => $standardChar) {

            $fineEntityHtml5    = htmlentities($fineChar, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $fineEntityHtml4    = htmlentities($fineChar, ENT_QUOTES | ENT_HTML401, 'UTF-8');
            $standardChar       = htmlentities($standardChar, ENT_QUOTES | ENT_HTML5 , 'UTF-8');

            $arrEntities[$fineEntityHtml5]  = $standardChar;
            $arrEntities[$fineEntityHtml4]  = $standardChar;
            $arrEntities[$fineChar]         = $standardChar;
        }

        return str_replace( array_keys($arrEntities), $arrEntities, $text);
    }


    protected function parseHTML(string $text) : DOMDocument|bool
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
         */
        $result = $domDoc->loadHTML('<?xml encoding="utf-8" ?>' . $text, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        if( $result === false ) {
            return false;
        }

        // dirty fix
        // https://www.php.net/manual/en/domdocument.loadhtml.php#95251
        foreach ($domDoc->childNodes as $item) {
            if ($item->nodeType == XML_PI_NODE) {
                $domDoc->removeChild($item); // remove hack
            }
        }

        $domDoc->encoding = 'UTF-8';
        return $domDoc;
    }


    protected function renderDomDocAsHTML(DOMDocument $domDoc, ?DOMNode $singleNodeOnly = null) : string
    {
        // pretty output https://www.php.net/manual/en/class.domdocument.php
        // doesn't work, likely due to "whitespace nodes being created by the load
        // https://www.php.net/manual/en/domdocument.savexml.php#76867
        //$domDoc->formatOutput = true;

        $text = empty($singleNodeOnly) ? $domDoc->saveHTML() : $domDoc->saveHTML($singleNodeOnly);
        return $this->convertLegacyEntitiesToUtf8Chars($text);
    }
}
