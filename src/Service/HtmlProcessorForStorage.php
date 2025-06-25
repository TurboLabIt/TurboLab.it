<?php
namespace App\Service;

use App\Service\Cms\UrlGenerator;
use DOMDocument;
use DOMElement;


// 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md
class HtmlProcessorForStorage extends HtmlProcessorBase
{
    protected UrlGenerator $urlGenerator;
    protected ?int $spotlightId = null;
    protected ?string $abstract = null;
    protected \HTMLPurifier $htmlPurifier;


    public function purify(?string $text) : string
    {
        if( empty($text) ) {
            return $text;
        }

        if( empty($this->htmlPurifier) ) {

            $tliPurifierConfig = \HTMLPurifier_Config::createDefault();
            $tliPurifierConfig->set('Core.Encoding', 'UTF-8');
            $tliPurifierConfig->set('HTML.Allowed', 'p,a[href],strong,em,ol,ul,li,h2,h3,code,ins,img[src],iframe[src]');

            // When enabled, HTML Purifier will treat any elements that contain only non-breaking spaces as well as
            //   regular whitespace as empty, and remove them when %AutoForamt.RemoveEmpty is enabled.
            $tliPurifierConfig->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);

            //When enabled, HTML Purifier will attempt to remove empty elements that contribute no semantic information to the document
            $tliPurifierConfig->set('AutoFormat.RemoveEmpty', true);

            // This is the content of the alt tag of an image if the user had not previously specified an alt attribute. This applies to all images without a valid alt attribute
            $tliPurifierConfig->set('Attr.DefaultImageAlt', '');

            $tliPurifierConfig->set('HTML.SafeIframe', true);
            $tliPurifierConfig->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

            $this->htmlPurifier = new \HTMLPurifier($tliPurifierConfig);
        }

        // all the quotes in non-attributes will be decoded
        // input:  This is a &quot;Tag&quot;: <img src="image.png">, but 100 &gt; 1
        // output: This is a "Tag": <img src="image.png" alt="" />, but 100 &gt; 1
        return $this->htmlPurifier->purify($text);
    }


    public function fixFormattingErrors(string $text) : string
    {
        // legacy code from https://github.com/TurboLabIt/tli1-sasha-grey/blob/master/website/www/include/func_tli_textprocessor.php

        $processing = preg_replace('/(<img [^>]*>)/i', '</p><p>\\0</p><p>', $text);

        $arrReplace = [
            '<br>'				=> '<p></p>',
            '<br/>'				=> '<p></p>',
            '<br />'			=> '<p></p>',

            '<h2><strong>'		=> '<h2>',
            '</strong></h2>'	=> '</h2>',

            '<p><p>'            => '<p>',
            '</p></p>'          => '</p>',

            '>&gt;&gt;'			=> '>&raquo;',
            '::hamburger::'		=> '≡',
            '::vdots::'			=> '⋮',
            '::hdots::'			=> '⋯'
        ];

        return str_ireplace( array_keys($arrReplace), $arrReplace, $processing);
    }


    public function removeAltAttribute(?string $text) : string
    {
        if( empty($text) ) {
            return $text;
        }

        return preg_replace('/\s*alt\s*=\s*([\'"]).*?\1/i', '', $text);
    }


    public function processArticleBody(string $body) : string
    {
        $domDoc = $this->parseHTML($body);
        if( $domDoc === false ) {
            return $body;
        }

        return
            $this
                ->removeLinksFromImages($domDoc)
                ->removeExternalImages($domDoc)
                ->imagesFromUrlToPlaceholder($domDoc)
                ->internalLinksFromUrlToPlaceholder($domDoc)
                ->YouTubeIframesFromUrlToPlaceholder($domDoc)
                ->extractAbstract($domDoc)
                ->renderDomDocAsHTML($domDoc);
    }


    protected function removeLinksFromImages(DOMDocument $domDoc) : static
    {
        $arrNodesToReplace = [];
        $arrNodes = $domDoc->getElementsByTagName('a');

        /** @var DOMElement $a */
        foreach($arrNodes as $a) {

            $arrImgs = $a->getElementsByTagName('img');
            foreach($arrImgs as $img) {

                $arrNodesToReplace[] = [
                    "replace"   => $a,
                    "with"      => $img
                ];

                break;
            }
        }

        foreach($arrNodesToReplace as $arrMap) {
            $arrMap["replace"]->parentNode->replaceChild($arrMap["with"], $arrMap["replace"]);
        }

        return $this;
    }


    protected function removeExternalImages(DOMDocument $domDoc) : static
    {
        $urlGenerator = $this->factory->getImageUrlGenerator();

        $arrNodesToReplace = [];
        $arrNodes = $domDoc->getElementsByTagName('img');

        /** @var DOMElement $img */
        foreach($arrNodes as $img) {

            $src = $img->getAttribute('src');

            if( $urlGenerator->isInternalUrl($src) ) {
                continue;
            }

            $nodeImageRemovedAlert = $domDoc->createTextNode('*** IMMAGINE ESTERNA RIMOSSA AUTOMATICAMENTE ***');
            $arrNodesToReplace[] = [
                "replace"   => $img,
                "with"      => $nodeImageRemovedAlert
            ];
        }

        foreach($arrNodesToReplace as $arrMap) {
            $arrMap["replace"]->parentNode->replaceChild($arrMap["with"], $arrMap["replace"]);
        }

        return $this;
    }


    protected function imagesFromUrlToPlaceholder(DOMDocument $domDoc) : static
    {
        $arrNodesToReplace = [];
        $arrNodes = $domDoc->getElementsByTagName('img');

        /** @var DOMElement $img */
        foreach($arrNodes as $img) {

            $src = $img->getAttribute('src');

            $arrMatches = [];
            $extractResult = preg_match('/(\d+)(?!.*\d)/', $src, $arrMatches);
            if( !$extractResult ) {

                $nodeImageRemovedAlert = $domDoc->createElement('p');
                $nodeImageRemovedAlert->textContent = '*** IMMAGINE MALFORMATA RIMOSSA AUTOMATICAMENTE ***';
                $arrNodesToReplace[] = [
                    "replace"   => $img,
                    "with"      => $nodeImageRemovedAlert
                ];

                continue;
            }

            $imageId = reset($arrMatches);
            $code = '==###immagine::id::' . $imageId . '###==';
            $safeImageNode = $domDoc->createElement('img');
            $safeImageNode->setAttribute('src', $code);
            $arrNodesToReplace[] = [
                "replace"   => $img,
                "with"      => $safeImageNode
            ];

            if( empty($this->spotlightId) ) {
                $this->spotlightId = (int)$imageId;
            }
        }

        foreach($arrNodesToReplace as $arrMap) {
            $arrMap["replace"]->parentNode->replaceChild($arrMap["with"], $arrMap["replace"]);
        }

        return $this;
    }


    public function getSpotlightId() : ?int { return $this->spotlightId; }


    protected function internalLinksFromUrlToPlaceholder(DOMDocument $domDoc) : static
    {
        $articleUrl = $this->factory->getArticleUrlGenerator();
        $tagUrl     = $this->factory->getTagUrlGenerator();
        $fileUrl    = $this->factory->getFileUrlGenerator();

        $arrNodes = $domDoc->getElementsByTagName('a');

        /** @var DOMElement $a */
        foreach($arrNodes as $a) {

            $href = $a->getAttribute("href");

            $articleId  = $articleUrl->extractIdFromUrl($href);
            if( !empty($articleId) ) {
                $a->setAttribute("href", '==###contenuto::id::' . $articleId . '###==');
                continue;
            }


            $tagId = $tagUrl->extractIdFromUrl($href);
            if( !empty($tagId) ) {
                $a->setAttribute("href", '==###tag::id::' . $tagId . '###==');
                continue;
            }


            $fileId = $fileUrl->extractIdFromUrl($href);
            if( !empty($fileId) ) {
                $a->setAttribute("href", '==###file::id::' . $fileId . '###==');
            }
        }

        return $this;
    }




    protected function YouTubeIframesFromUrlToPlaceholder(DOMDocument $domDoc) : static
    {
        $arrNodes = $domDoc->getElementsByTagName('iframe');

        $iframesToProcess = [];
        foreach ($arrNodes as $iframe) {

            $url = $iframe->getAttribute("src");

            if( !preg_match('/^(https?:)?\/\/(?:www\.)?youtube(?:-nocookie)?\.com\//i', $url) ) {
                continue;
            }

            $iframesToProcess[] = $iframe;
        }

        $arrRegex = [
            '/(?<=(\/embed\/))([a-z0-9_-]+)/i',
            '/(?<=(\/watch\?v=))([a-z0-9_-]+)/i'
        ];

        /** @var DOMElement $iframe */
        foreach($iframesToProcess as $iframe) {

            foreach($arrRegex as $regex) {

                $url = $iframe->getAttribute("src");

                $arrMatches = [];
                preg_match($regex, $url, $arrMatches);

                if( !empty($arrMatches[0]) ) {

                    $placeholderText = '==###youtube::code::' . $arrMatches[0] . '###==';

                    // Create a new text node with the placeholder
                    $placeholderNode = $domDoc->createTextNode($placeholderText);

                    // Get the parent of the iframe
                    $parentNode = $iframe->parentNode;

                    //// Ensure parentNode exists
                    if( !$parentNode ) {
                        continue;
                    }

                    // Replace the iframe with the new text node
                    $parentNode->replaceChild($placeholderNode, $iframe);
                    break;
                }
            }
        }

        return $this;
    }










    protected function YouTubeIframesFromUrlToPlaceholderLEGACY(DOMDocument $domDoc) : static
    {
        $arrNodes = $domDoc->getElementsByTagName('iframe');

        /** @var DOMElement $a */
        foreach($arrNodes as $iframe) {

            $url = $iframe->getAttribute("src");

            if( !preg_match('/^(https?:)?\/\/(?:www\.)?youtube(?:-nocookie)?\.com\//i', $url) ) {
                continue;
            }

            $arrRegex = [
                '/(?<=(\/embed\/))([a-z0-9])*/i',
                '/(?<=(\/watch\?v=))([a-z0-9])*/i'
            ];

            foreach($arrRegex as $regex) {

                $arrMatches = [];
                preg_match($regex, $url, $arrMatches);

                if( !empty($arrMatches[0]) ) {

                    $iframe->setAttribute("src", '==###youtube::code::' . $arrMatches[0] . '###==');
                    break;
                }
            }
        }

        return $this;
    }


    public function extractAbstract(DOMDocument $domDoc) : static
    {
        $arrNodes = $domDoc->getElementsByTagName('p');
        foreach($arrNodes as $node) {

            /**
             * if we access $node->nodeValue directly, all the entities would be decoded, even if the underlying
             * text is actually encoded
             */
            $processing = $this->renderDomDocAsHTML($domDoc, $node);
            $processing = strip_tags($processing, ['em', 'code']);
            $text       = trim($processing);

            if( !empty($text) ) {

                $this->abstract = $text;
                return $this;
            }
        }

        return $this;
    }


    public function getAbstract() : ?string { return $this->abstract; }
}
