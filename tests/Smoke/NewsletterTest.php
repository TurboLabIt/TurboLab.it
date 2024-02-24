<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;


class NewsletterTest extends BaseT
{
    public function testPreview()
    {
        $url = $this->getPreviewUrl();

        $html = $this->fetchHtml( $this->getPreviewUrl() );
        $this->assertStringContainsStringIgnoringCase('Ciao Nyan Cat', $html);
        $this->assertStringContainsStringIgnoringCase('Non vuoi più ricevere queste email', $html);
        $this->assertStringContainsStringIgnoringCase('Email inviata a info@turbolab.it', $html);
        $this->assertStringContainsStringIgnoringCase('Articoli e news', $html);
        $this->assertStringContainsStringIgnoringCase('Dal forum', $html);

        // H2
        $crawler = $this->fetchDomNode($url, 'body');
        $H2s = $crawler->filter('h3');
        $countH2 = $H2s->count();
        $this->assertGreaterThanOrEqual(2, $countH2);
    }


    public function testPreviewLinks()
    {
        $url = $this->getPreviewUrl();

        $crawler = $this->fetchDomNode($url, 'body');
        $this->internalLinksChecker($crawler);
    }


    public function testPreviewImages()
    {
        $url = $this->getPreviewUrl();

        $crawler = $this->fetchDomNode($url, 'body');
        $this->internalImagesChecker($crawler);
    }


    protected function getPreviewUrl() : string
    {
        return $_ENV["APP_SITE_URL"] . 'newsletter/anteprima';
    }
}
