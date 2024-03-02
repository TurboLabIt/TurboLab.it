<?php
namespace App\Tests\Smoke;

use App\Service\Cms\Article;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\HttpFoundation\Response;


class NewsletterTest extends BaseT
{
    public function testAppNewsletterIndex()
    {
        /** @var Article $article */
        $article = static::getService("App\\Service\\Cms\\Article");
        // 👀 https://turbolab.it/402
        $articleUrl = $article->load(402)->getUrl();
        $this->expectRedirect('/newsletter', $articleUrl, Response::HTTP_FOUND);
    }


    protected function getPreviewUrl() : string
    {
        return $_ENV["APP_SITE_URL"] . 'newsletter/anteprima';
    }


    public function testPreview()
    {
        $url  = $this->getPreviewUrl();
        $html = $this->fetchHtml($url);
        $this->assertStringContainsStringIgnoringCase('Ciao Zane', $html);
        $this->assertStringContainsStringIgnoringCase('Non vuoi più ricevere queste email', $html);
        $this->assertStringContainsStringIgnoringCase('Email inviata a info@turbolab.it', $html);
        $this->assertStringContainsStringIgnoringCase('Articoli e news', $html);
        $this->assertStringContainsStringIgnoringCase('Dal forum', $html);

        // H2

        $crawler = $this->fetchDomNode($url, 'body');
        $H2s = $crawler->filter('h3');
        $countH2 = $H2s->count();
        //$this->assertGreaterThanOrEqual(2, $countH2);
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


    public function testUnsubscribe()
    {
        $firstUnsubscribeUrl = null;

        $url = $this->getPreviewUrl();
        $links = $this->fetchDomNode($url, 'body')->filter('a');
        foreach($links as $link) {

            $href = $link->getAttribute('href');
            if( stripos($href, 'newsletter/disiscrizione') === false ) {
                continue;
            }

            if( empty($firstUnsubscribeUrl) ) {
                $firstUnsubscribeUrl = $href;
            } else {
                $this->assertEquals($firstUnsubscribeUrl, $href);
            }

            $this->fetchDomNode($href);
        }

        $this->assertNotEmpty($firstUnsubscribeUrl);

        return $firstUnsubscribeUrl;
    }


    #[Depends('testUnsubscribe')]
    public function testResubscribe(string $unsubscribeUrl)
    {
        $firstSubscribeUrl = null;

        $links = $this->fetchDomNode($unsubscribeUrl, 'body')->filter('a');
        foreach($links as $link) {

            $href = $link->getAttribute('href');
            if( stripos($href, 'newsletter/iscrizione') !== false ) {

                if( empty($firstSubscribeUrl) ) {
                    $firstSubscribeUrl = $href;
                } else {
                    $this->assertEquals($firstSubscribeUrl, $href);
                }

                $this->fetchDomNode($href);
            }
        }

        $this->assertNotEmpty($firstSubscribeUrl);
    }



}
