<?php
namespace App\Service;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\Article;
use App\Service\Cms\Image;
use DOMDocument;
use DOMXPath;


// 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md
class HtmlProcessorForDisplay extends HtmlProcessorBase
{
    const string REGEX_IMAGE_PLACEHOLDER    = '/(?<=(==###immagine::id::))[1-9]+[0-9]*(?=(###==))/';
    const string REGEX_IMAGE_SHORTURL       = '/(?<=(\/immagini\/))[1-9]+[0-9]*(?=(\/))/';

    const string REGEX_FILE_PLACEHOLDER     = '/(?<=(==###file::id::))[1-9]+[0-9]*(?=(###==))/';
    const string REGEX_FILE_URL             = '/(?<=(\/scarica\/))[1-9]+[0-9]*/';


    public function processArticleBody(Article $article) : string
    {
        $text = $article->getBody();

        if( empty($text) ) {
            return '';
        }

        $domDoc = $this->parseHTML($text);
        if( $domDoc === false ) {
            return $text;
        }

        $processing =
            $this
                ->imagesFromPlaceholderToUrl($domDoc, $article)
                ->articleLinksFromPlaceholderToUrl($domDoc)
                ->tagLinksFromPlaceholderToUrl($domDoc)
                ->fileLinksFromPlaceholderToUrl($domDoc)
                ->YouTubeIframesFromPlaceholderToUrl($domDoc)
                ->renderDomDocAsHTML($domDoc);

        return trim($processing);
    }


    protected function imagesFromPlaceholderToUrl(DOMDocument $domDoc, Article $article) : static
    {
        $arrImgNodes = $this->extractNodes($domDoc, 'img', 'src', static::REGEX_IMAGE_PLACEHOLDER);

        $imageCollection = $this->factory->createImageCollection();
        $imageCollection->load( array_keys($arrImgNodes) );

        $index = 1;
        foreach($arrImgNodes as $imgId => $arrThisImgDomOccurrences) {

            foreach($arrThisImgDomOccurrences as $oneImgNode) {

                /** @var ?Image $srvImage */
                $srvImage = $imageCollection->get($imgId);

                if( empty($srvImage) ) {

                    // this will display an error, but we want to keep the original image URL
                    $fakeImageEntity    = (new ImageEntity())->setId($imgId);
                    $imgUrl             = $this->factory->createImage($fakeImageEntity)->getUrl(Image::SIZE_REG);
                    $imageAltText       = 'Immagine non trovata';

                } else {

                    $imgUrl         = $srvImage->getUrl(Image::SIZE_REG);
                    $imageAltText   = "Immagine " . $index . " " . $srvImage->getTitle();
                }

                $oneImgNode->setAttribute('src', $imgUrl);
                $oneImgNode->setAttribute('title', $imageAltText);
                $oneImgNode->setAttribute('alt', $imageAltText);

                $index++;
            }
        }

        return $this;
    }


    protected function articleLinksFromPlaceholderToUrl(DOMDocument $domDoc) : static
    {
        $artRegEx       = '/(?<=(==###contenuto::id::))[1-9]+[0-9]*(?=(###==))/';
        $arrLinkNodes   = $this->extractNodes($domDoc, 'a', 'href', $artRegEx);

        $articleCollection = $this->factory->createArticleCollection();
        $articleCollection->load( array_keys($arrLinkNodes) );

        foreach($arrLinkNodes as $artId => $arrLinksToThisId) {

            foreach($arrLinksToThisId as $oneLinkNode) {

                $srvArticle = $articleCollection->get($artId);
                if( empty($srvArticle) ) {

                    $artUrl         = '#';
                    $articleAltText = '';

                }  else {

                    $artUrl         = $srvArticle->getUrl();
                    $articleAltText = $srvArticle->getTitle();
                }

                $oneLinkNode->setAttribute('href', $artUrl);
                $oneLinkNode->setAttribute('title', $articleAltText);
            }
        }

        return $this;
    }


    protected function tagLinksFromPlaceholderToUrl(DOMDocument $domDoc)
    {
        $tagRegEx       = '/(?<=(==###tag::id::))[1-9]+[0-9]*(?=(###==))/';
        $arrLinkNodes   = $this->extractNodes($domDoc, 'a', 'href', $tagRegEx);

        $tagCollection = $this->factory->createTagCollection();
        $tagCollection->load( array_keys($arrLinkNodes) );

        foreach($arrLinkNodes as $tagId => $arrLinksToThisId) {

            foreach($arrLinksToThisId as $oneLinkNode) {

                $srvTag = $tagCollection->get($tagId);
                if( empty($srvTag) ) {

                    $tagUrl     = '#';
                    $tagAltText = '';

                }  else {

                    $tagUrl     = $srvTag->getUrl();
                    $tagAltText = $srvTag->getTitle() . ": guide e articoli";
                }

                $oneLinkNode->setAttribute('href', $tagUrl);
                $oneLinkNode->setAttribute('title', $tagAltText);
            }
        }

        return $this;
    }


    protected function fileLinksFromPlaceholderToUrl(DOMDocument $domDoc)
    {
        $fileRegEx      = static::REGEX_FILE_PLACEHOLDER;
        $arrLinkNodes   = $this->extractNodes($domDoc, 'a', 'href', $fileRegEx);

        $fileCollection = $this->factory->createFileCollection();
        $fileCollection->load( array_keys($arrLinkNodes) );

        foreach($arrLinkNodes as $fileId => $arrLinksToThisId) {

            foreach($arrLinksToThisId as $oneLinkNode) {

                $srvFile = $fileCollection->get($fileId);
                if( empty($srvFile) ) {

                    $fileUrl        = '#';
                    $fileAltText    = '';

                }  else {

                    $fileUrl        = $srvFile->getUrl();
                    $fileAltText    = "Scarica " . $srvFile->getTitle();
                }

                $oneLinkNode->setAttribute('href', $fileUrl);
                $oneLinkNode->setAttribute('title', $fileAltText);
            }
        }

        return $this;
    }


    protected function YouTubeIframesFromPlaceholderToUrl(DOMDocument $domDoc) : static
    {
        $regex = '/^==###youtube::code::([a-z0-9-_]+)###==$/i';

        $xpath = new DOMXPath($domDoc);
        $textNodes = $xpath->query('//text()');

        $nodesToReplace = [];

        foreach ($textNodes as $textNode) {

            $nodeValue = trim($textNode->nodeValue);

            if( preg_match($regex, $nodeValue, $matches) ) {
                $nodesToReplace[] = [
                    'node' => $textNode,
                    'code' => $matches[1]
                ];
            }
        }

        foreach ($nodesToReplace as $item) {

            $textNode   = $item['node'];
            $videoCode  = $item['code'];

            $iframe = $domDoc->createElement('iframe');

            $url = 'https://www.youtube-nocookie.com/embed/' . $videoCode . '?rel=0';
            $iframe->setAttribute('src', $url);
            $iframe->setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
            $iframe->setAttribute('allowfullscreen', 'allowfullscreen');

            $parentNode = $textNode->parentNode;
            if($parentNode) {
                $parentNode->replaceChild($iframe, $textNode);
            }
        }

        return $this;
    }


    public function extractNodes(DOMDocument $domDoc, $tagName, $attributeToCheck, $attributeRegEx) : array
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
}
