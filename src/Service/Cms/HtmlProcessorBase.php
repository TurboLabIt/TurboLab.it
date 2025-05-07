<?php
namespace App\Service\Cms;

use App\Service\Factory;
use DOMDocument;


abstract class HtmlProcessorBase
{
    public function __construct(protected Factory $factory) {}


    public function convertEntitiesToUtf8Chars(?string $text) : ?string
    {
        // 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md

        if( empty($text) ) {
            return $text;
        }

        // replace U+00A0 : NO-BREAK SPACE [NBSP] with an actual goddamn space
        $text = preg_replace('/\xc2\xa0/', ' ', $text);

        //
        $entityToKeep = [
            htmlentities('<')     => '==##LT###==',
            htmlentities('>')     => '==##GT###==',
            htmlentities('&')     => '==##AND###==',
            htmlentities('"')     => '==##DQUOTE###==',
            htmlentities("'")     => '==##SQUOTE###=='
        ];

        $processedText = str_ireplace( array_keys($entityToKeep), $entityToKeep, $text);
        $processedText = html_entity_decode($processedText, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
        return str_ireplace( $entityToKeep, array_keys($entityToKeep), $processedText);
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

        $domDoc->encoding = 'UTF-8'; // insert proper
        return $domDoc;
    }


    protected function renderDomDocAsHTML(DOMDocument $domDoc) : string
    {
        // pretty output https://www.php.net/manual/en/class.domdocument.php
        // doesn't work, likely due to "whitespace nodes being created by the load
        // https://www.php.net/manual/en/domdocument.savexml.php#76867
        //$domDoc->formatOutput = true;

        $text = $domDoc->saveHTML();
        return $this->convertEntitiesToUtf8Chars($text);
    }
}
