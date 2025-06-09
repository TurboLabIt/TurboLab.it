<?php
namespace App\Service\Cms;

use DOMDocument;
use DOMElement;


// 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md
class HtmlProcessorReverse extends HtmlProcessorBase
{
    protected UrlGenerator $urlGenerator;
    protected ?int $spotlightId = null;
    protected ?string $abstract = null;


    protected function processTextForStorage(string $text) : string
    {
        // replace U+00A0 : NO-BREAK SPACE [NBSP] with an actual goddamn space
        $normalized = preg_replace('/\xc2\xa0/', ' ', $text);

        // replace "fine typography" with their base chars
        $normalized = str_ireplace( array_keys(static::FINE_TYPOGRAPHY_CHARS), static::FINE_TYPOGRAPHY_CHARS, $normalized);

        $normalized = trim($normalized);

        // replace two or more consecutive spaces with one
        $normalized = preg_replace('/ {2,}/', ' ', $normalized);

        return $normalized;
    }


    /**
     * Transform an HTML title for storage. Input title example:
     * `SCRIPT: <script>alert("bòòm");</script>`
     */
    public function processTitleForStorage(string $title) : string
    {
        $normalized = $this->processTextForStorage($title);

        $arrEntities =

        // create the equivalent HTML-encoded &entities; array
        $arrEntities =
            array_map(function($char) {
                return htmlentities($char, ENT_QUOTES, 'UTF-8');
                }, static::ENTITIES);

        array_walk($arrEntities, function(&$entity) {
            // replace ' (encoded as &#039;) with &apos; (HTML5)
            $entity = str_ireplace('&#039;', '&apos;', $entity);
            //
        });

        // replace the only entities that really need to be replaced, as per encoding.md
        $normalized = str_ireplace(static::ENTITIES, $arrEntities, $normalized);

        return $normalized;
    }


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
