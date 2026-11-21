<?php
namespace App\Tests\Smoke;

use App\Service\Cms\Article;
use App\Service\User;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\HttpFoundation\Response;


class NewsletterTest extends BaseT
{
    public function testAppNewsletterIndex() : void
    {
        $articleUrl = static::getArticle(Article::ID_NEWSLETTER)->getUrl();
        $this->expectRedirect('/newsletter', $articleUrl, Response::HTTP_FOUND);
    }


    protected function getPreviewUrl() : string
        { return $this->generateUrl() . 'newsletter/anteprima'; }


    public function testPreview() : void
    {
        $url  = $this->getPreviewUrl();
        $html = $this->fetchHtml($url);
        $this->assertStringContainsStringIgnoringCase('Ciao System', $html);
        $this->assertStringContainsStringIgnoringCase('Non vuoi più ricevere queste email', $html);
        $this->assertStringContainsStringIgnoringCase('abbiamo inviato questa email a', $html);
        $this->assertStringContainsStringIgnoringCase('info.system@turbolab.it', $html);
        $this->assertStringContainsStringIgnoringCase('Articoli e news', $html);
        $this->assertStringContainsStringIgnoringCase('Dal forum', $html);

        $crawler = $this->fetchDomNode($url, 'body');
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertEquals(5, $countH2);
    }


    public function testPreviewLinks() : void
    {
        $url = $this->getPreviewUrl();
        $crawler = $this->fetchDomNode($url, 'body');
        $this->internalLinksChecker($crawler);
    }


    public function testPreviewImages() : void
    {
        $url = $this->getPreviewUrl();
        $crawler = $this->fetchDomNode($url, 'body');
        $this->internalImagesChecker($crawler);
    }


    public function testUnsubscribe() : string
    {
        $firstUnsubscribeUrl = null;

        $url = $this->getPreviewUrl();
        $links = $this->fetchDomNode($url, 'body')->filter('a');
        foreach($links->extract(['href']) as $href) {

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
    public function testResubscribe(string $unsubscribeUrl) : void
    {
        $firstSubscribeUrl = null;

        $links = $this->fetchDomNode($unsubscribeUrl, 'body')->filter('a');
        foreach($links->extract(['href']) as $href) {

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


    public function testUnsubscribeAlreadyUnsubscribedError() : void
    {
        $user = static::getUser();
        $user->unsubscribeFromNewsletter();
        $this->getEntityManager()->flush();

        $unsubscribeUrl = $user->getNewsletterUnsubscribeUrl();
        $this->browse($unsubscribeUrl);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $subscribeUrl = $user->getNewsletterSubscribeUrl();
        $this->fetchHtml($subscribeUrl);

        // system must not receive the newsletter
        $user->unsubscribeFromNewsletter();
        $this->getEntityManager()->flush();
    }


    public function testSubscribeAlreadySubscribedError() : void
    {
        $user = static::getUser();
        $user->subscribeToNewsletter();
        $this->getEntityManager()->flush();

        $subscribeUrl = $user->getNewsletterSubscribeUrl();
        $this->browse($subscribeUrl);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $unsubscribeUrl = $user->getNewsletterUnsubscribeUrl();
        $this->fetchHtml($unsubscribeUrl);

        // system must not receive the newsletter
        $user->unsubscribeFromNewsletter();
        $this->getEntityManager()->flush();
    }
}
