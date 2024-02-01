<?php
namespace App\Service\Cms;

use App\ServiceCollection\Cms\ImageCollection;
use App\Entity\Cms\Image as ImageEntity;


class HtmlProcessor
{
    public function __construct(protected ImageCollection $imageCollection)
    { }


    public function processArticleBodyForDisplay(string $text, Article $article): string
    {
        $domDoc = $this->parseHTML($text);
        if( $domDoc === false ) {
            return $text;
        }

        return
            $this
                ->imagesFromCodeToHtml($domDoc, $article)
                //->articlesFromCodeToHTML($domDoc)
                //->tagsFromCodeToHTML($domDoc)
                //->youtubeFromCodeToHTML($domDoc)
                ->renderDomDocAsHTML($domDoc);
    }


    protected function imagesFromCodeToHtml(\DOMDocument $domDoc, Article $article): static
    {
        $imgRegEx       = '/(?<=(==###immagine::id::))[1-9]+[0-9]*(?=(###==))/';
        $arrImgNodes    = $this->extractNodes($domDoc, 'img', 'src', $imgRegEx);
        $this->imageCollection->loadByIds( array_keys($arrImgNodes) );

        $index = 1;
        foreach($arrImgNodes as $imgId => $arrThisImgDomOccurrences) {

            foreach($arrThisImgDomOccurrences as $oneImgNode) {

                /** @var Image $srvImage */
                $srvImage = $this->imageCollection->get($imgId);

                if( empty($srvImage) ) {

                    // this will display an error, but we want to keep the original image URL
                    $fakeImageEntity =
                        (new ImageEntity())
                            ->setId($imgId);

                    $imgUrl = $this->imageCollection->createService($fakeImageEntity)->getUrl($article, Image::SIZE_MED);
                    $imageAltText = 'Immagine non trovata';

                } else {

                    $imgUrl = $srvImage->getUrl($article, Image::SIZE_MED);
                    $imageAltText = "Immagine " . $index . " " . $srvImage->getTitle();
                }

                $oneImgNode->setAttribute('src', $imgUrl);
                $oneImgNode->setAttribute('title', $imageAltText);
                $oneImgNode->setAttribute('alt', $imageAltText);

                $index++;
            }
        }

        return $this;
    }


    protected function parseHTML(string $text): \DOMDocument|bool
    {
        $domDoc = new \DOMDocument();

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


    protected function extractNodes(\DOMDocument $domDoc, $tagName, $attributeToCheck, $attributeRegEx)
    {
        $arrNodes = $domDoc->getElementsByTagName($tagName);
        $arrMatchingNodes = [];

        foreach($arrNodes as $oneNode) {

            $arrMatches     = [];
            $attribValue    = $oneNode->getAttribute($attributeToCheck);
            $found          = preg_match($attributeRegEx, $attribValue, $arrMatches);

            if($found) {

                $id = $arrMatches[0];
                $arrMatchingNodes[$id][] = $oneNode;
            }
        }

        return $arrMatchingNodes;
    }


    protected function renderDomDocAsHTML(\DOMDocument $domDoc): string
    {
        $text = $domDoc->saveHTML();
        // restore accented chars
        $text = html_entity_decode($text, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
        return $text;
    }
}
