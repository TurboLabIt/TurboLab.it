<?php
namespace App\Service;

use App\Entity\Cms\Image as ImageEntity;
use App\Service\Cms\Article;
use App\Service\Cms\Image;
use DOMDocument;


// 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/encoding.md
class HtmlProcessorForDisplay extends HtmlProcessorBase
{
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
        $imgRegEx       = '/(?<=(==###immagine::id::))[1-9]+[0-9]*(?=(###==))/';
        $arrImgNodes    = $this->extractNodes($domDoc, 'img', 'src', $imgRegEx);

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
                    $imgUrl             = $this->factory->createImage($fakeImageEntity)->getUrl($article, Image::SIZE_MED);
                    $imageAltText       = 'Immagine non trovata';

                } else {

                    $imgUrl         = $srvImage->getUrl($article, Image::SIZE_MED);
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
        $fileRegEx      = '/(?<=(==###file::id::))[1-9]+[0-9]*(?=(###==))/';
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
        $ytRegEx        = '/(?<=(==###youtube::code::))[a-zA-z0-9-_]+(?=(###==))/';
        $arrLinkNodes   = $this->extractNodes($domDoc, 'iframe', 'src', $ytRegEx);

        foreach($arrLinkNodes as $ytVideoCode => $arrIframesWithThisCode) {

            foreach($arrIframesWithThisCode as $oneIframe) {

                $url = '//www.youtube-nocookie.com/embed/' . $ytVideoCode . '?&rel=0';
                $oneIframe->setAttribute('src', $url);
                $oneIframe->setAttribute('frameborder', 0);
                $oneIframe->setAttribute('width', '100%');
                $oneIframe->setAttribute('height', '540px');
                $oneIframe->setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
                $oneIframe->setAttribute('allowfullscreen', 'allowfullscreen');
            }
        }

        return $this;
    }


    protected function extractNodes(DOMDocument $domDoc, $tagName, $attributeToCheck, $attributeRegEx)
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
