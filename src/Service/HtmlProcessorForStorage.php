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

        return $this->htmlPurifier->purify($text);
    }


    public function removeExternalImages(string $text) : string
    {
        // legacy code from https://github.com/TurboLabIt/tli1-sasha-grey/blob/master/website/www/include/func_tli_textprocessor.php

        $text = preg_replace('/(<img [^>]*>)/i', '</p><p>\\0</p><p>', $text);

        $arrImgSrcCompleti = [];
        preg_match_all('/<img [^>]*>/i', $text,$arrImgSrcCompleti);

        if( empty($arrImgSrcCompleti) ) {
            return $text;
        }

        foreach($arrImgSrcCompleti[0] as $imgSrcCompleto) {

            $imgSrcCompletoElaborato =
                str_ireplace([
                    'src="http://turbolab.it', 'src="https://turbolab.it',
                    'src="https://next.turbolab.it', 'src="https://dev0.turbolab.it'
                ], 'src="', $imgSrcCompleto);

            if( str_contains($imgSrcCompletoElaborato, 'src="/immagini/') ) {

                $text = str_ireplace($imgSrcCompleto, $imgSrcCompletoElaborato, $text);

            } else {

                $text = str_ireplace($imgSrcCompleto, '**** IMMAGINE ESTERNA AL SITO RIMOSSA AUTOMATICAMENTE ****', $text);
            }
        }

        return $text;
    }


    public function fixCodeErrors(string $text) : string
    {
        // legacy code from https://github.com/TurboLabIt/tli1-sasha-grey/blob/master/website/www/include/func_tli_textprocessor.php

        $arrReplace = [
            '<br>'				=> '<p></p>',
            '<br/>'				=> '<p></p>',
            '<br />'			=> '<p></p>',

            '<h2><strong>'		=> '<h2>',
            '</strong></h2>'	=> '</h2>',

            '>&gt;&gt;'			=> '>&raquo;',
            '::hamburger::'		=> '≡',
            '::vdots::'			=> '⋮',
            '::hdots::'			=> '⋯'
        ];

        return str_ireplace( array_keys($arrReplace), $arrReplace, $text);
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
                ->imagesFromUrlToPlaceholder($domDoc)
                ->internalLinksFromUrlToPlaceholder($domDoc)
                ->extractAbstract($domDoc)
                //->YouTubeIframesFromUrlToPlaceholder($domDoc)
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


    protected function imagesFromUrlToPlaceholder(DOMDocument $domDoc) : static
    {
        $urlGenerator = $this->factory->getImageUrlGenerator();

        $arrNodesToReplace = [];
        $arrNodes = $domDoc->getElementsByTagName('img');

        /** @var DOMElement $img */
        foreach($arrNodes as $img) {

            $src = $img->getAttribute('src');
            if( !$urlGenerator->isInternalUrl($src) ) {

                $nodeImageRemovedAlert = $domDoc->createElement('p');
                $nodeImageRemovedAlert->textContent = '*** IMMAGINE ESTERNA RIMOSSA AUTOMATICAMENTE ***';
                $arrNodesToReplace[] = [
                    "replace"   => $img,
                    "with"      => $nodeImageRemovedAlert
                ];

                continue;
            }

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

        $arrNodesToReplace = [];
        $arrNodes = $domDoc->getElementsByTagName('a');

        /** @var DOMElement $a */
        foreach($arrNodes as $a) {

            $safeLinkNode = $domDoc->createElement('a');
            $safeLinkNode->nodeValue = $a->nodeValue;

            $href = $a->getAttribute("href");

            //
            $articleId  = $articleUrl->extractIdFromUrl($href);
            if( !empty($articleId) ) {

                $code = '==###contenuto::id::' . $articleId . '###==';
                $safeLinkNode->setAttribute("href", $code);
                $arrNodesToReplace[] = [
                    "replace"   => $a,
                    "with"      => $safeLinkNode
                ];

                continue;
            }

            //
            $tagId = $tagUrl->extractIdFromUrl($href);
            if( !empty($tagId) ) {

                $code = '==###tag::id::' . $tagId . '###==';
                $safeLinkNode->setAttribute("href", $code);
                $arrNodesToReplace[] = [
                    "replace"   => $a,
                    "with"      => $safeLinkNode
                ];

                continue;
            }

            //
            $fileId = $fileUrl->extractIdFromUrl($href);
            if( !empty($fileId) ) {

                $code = '==###file::id::' . $fileId . '###==';
                $safeLinkNode->setAttribute("href", $code);
                $arrNodesToReplace[] = [
                    "replace"   => $a,
                    "with"      => $safeLinkNode
                ];

                continue;
            }

            $safeLinkNode->setAttribute("href", $href);
            $arrNodesToReplace[] = [
                "replace"   => $a,
                "with"      => $safeLinkNode
            ];
        }

        foreach($arrNodesToReplace as $arrMap) {
            $arrMap["replace"]->parentNode->replaceChild($arrMap["with"], $arrMap["replace"]);
        }

        return $this;
    }


    public function extractAbstract(DOMDocument $domDoc) : static
    {
        $arrNodes = $domDoc->getElementsByTagName('p');
        foreach($arrNodes as $node) {

            $text = $node->nodeValue;
            $text = strip_tags($text, ['em']);
            $text = trim($text);

            if( !empty($text) ) {

                $this->abstract = $text;
                return $this;
            }
        }

        return $this;
    }


    public function getAbstract() : ?string { return $this->abstract; }
}
