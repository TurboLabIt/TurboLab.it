<?php
namespace App\Service\Cms;

use DOMDocument;
use DOMElement;


class HtmlProcessorReverse extends HtmlProcessorBase
{
    protected UrlGenerator $urlGenerator;
    protected ?int $spotlightId = null;
    protected ?string $abstract = null;


    public function processArticleBodyForStorage(string $body) : string
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

        $arrNodesToReplace = [];
        $arrNodes = $domDoc->getElementsByTagName('a');

        /** @var DOMElement $a */
        foreach($arrNodes as $a) {

            $safeLinkNode = $domDoc->createElement('a');
            $safeLinkNode->nodeValue = $a->nodeValue;

            $href = $a->getAttribute("href");

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
