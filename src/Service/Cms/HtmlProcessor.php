<?php
namespace App\Service\Cms;

use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\ImageCollection;
use App\Entity\Cms\Image as ImageEntity;
use App\ServiceCollection\Cms\TagCollection;


class HtmlProcessor
{
    public function __construct(
        protected ImageCollection $imageCollection, protected ArticleCollection $articleCollection,
        protected TagCollection $tagCollection
    )
    { }


    public function processArticleBodyForDisplay(Article $article) : string
    {
        $text   = $article->getBody();
        $domDoc = $this->parseHTML($text);
        if( $domDoc === false ) {
            return $text;
        }

        return
            $this
                ->imagesFromCodeToHTML($domDoc, $article)
                ->articlesFromCodeToHTML($domDoc)
                ->tagsFromCodeToHTML($domDoc)
                ->youTubeFromCodeToHTML($domDoc)
                ->renderDomDocAsHTML($domDoc);
    }


    protected function parseHTML(string $text): \DOMDocument|bool
    {
        $domDoc = new \DOMDocument();

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


    protected function imagesFromCodeToHTML(\DOMDocument $domDoc, Article $article): static
    {
        $imgRegEx       = '/(?<=(==###immagine::id::))[1-9]+[0-9]*(?=(###==))/';
        $arrImgNodes    = $this->extractNodes($domDoc, 'img', 'src', $imgRegEx);
        $this->imageCollection->load( array_keys($arrImgNodes) );

        $index = 1;
        foreach($arrImgNodes as $imgId => $arrThisImgDomOccurrences) {

            foreach($arrThisImgDomOccurrences as $oneImgNode) {

                /** @var Image $srvImage */
                $srvImage = $this->imageCollection->get($imgId);

                if( empty($srvImage) ) {

                    // this will display an error, but we want to keep the original image URL
                    $fakeImageEntity    = (new ImageEntity())->setId($imgId);
                    $imgUrl             = $this->imageCollection->createService($fakeImageEntity)->getUrl($article, Image::SIZE_MED);
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


    protected function articlesFromCodeToHTML(\DOMDocument $domDoc) : static
    {
        $artRegEx       = '/(?<=(==###contenuto::id::))[1-9]+[0-9]*(?=(###==))/';
        $arrLinkNodes   = $this->extractNodes($domDoc, 'a', 'href', $artRegEx);
        $this->articleCollection->load( array_keys($arrLinkNodes) );

        foreach($arrLinkNodes as $artId => $arrLinksToThisId) {

            foreach($arrLinksToThisId as $oneLinkNode) {

                $srvArticle = $this->articleCollection->get($artId);
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


    protected function tagsFromCodeToHTML(\DOMDocument $domDoc)
    {
        $tagRegEx       = '/(?<=(==###tag::id::))[1-9]+[0-9]*(?=(###==))/';
        $arrLinkNodes   = $this->extractNodes($domDoc, 'a', 'href', $tagRegEx);
        $this->tagCollection->load( array_keys($arrLinkNodes) );

        foreach($arrLinkNodes as $tagId => $arrLinksToThisId) {

            foreach($arrLinksToThisId as $oneLinkNode) {

                $srvTag = $this->tagCollection->get($tagId);
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


    protected function youTubeFromCodeToHTML(\DOMDocument $domDoc) : static
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
