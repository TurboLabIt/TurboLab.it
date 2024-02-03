<?php
namespace App\Tests;

use App\Comparabile\Entity\Opportunity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Response;


abstract class BaseT extends WebTestCase
{
    protected static ?KernelBrowser $client = null;
    protected static Crawler $crawler;


    protected static function getService(string $name)
    {
        // boots the kernel and prevents LogicException:
        // Booting the kernel before calling "WebTestCase::createClient()" is not supported, the kernel should only be booted once.
        if( empty(static::$client) ) {
            static::$client = static::createClient();
        }

        $container = static::getContainer();
        $connector = $container->get($name);
        return $connector;
    }


    protected static function getEntityManager() : EntityManagerInterface
    {
        return static::getService('doctrine.orm.entity_manager');
    }


    protected function fetchHtml(string $url, string $method = 'GET', bool $toLower = true) : string
    {
        $this->browse($url, $method);
        $this->assertResponseIsSuccessful("Failing URL: " . $url);

        $html = static::$client->getResponse()->getContent();

        if($toLower) {
            $html =  mb_strtolower($html);
        }

        return $html;
    }


    public function browse(string $url, string $method = 'GET') : Crawler
    {
        if( empty(static::$client) ) {

            // prevent "Kernel has already been booted"
            static::$client = static::createClient();

        } else {

            // prevent add the path twice on second call
            static::$client->restart();
        }

        static::$crawler = static::$client->request($method, $url);
        return static::$crawler;
    }



    public function fetchDomNode(string $url, string $selector = "html") : Crawler
    {
        $crawler = $this->browse($url);
        $this->assertResponseIsSuccessful("Failing URL: " . $url);

        $node = $crawler->filter($selector);
        return $node;
    }


    public function expectRedirect(string $urlFirst, string $expectedUrlRedirectTo, int $expectedHttpStatus = Response::HTTP_MOVED_PERMANENTLY)
    {
        $crawler = $this->browse($urlFirst);
        $this->assertResponseRedirects($expectedUrlRedirectTo, $expectedHttpStatus);
    }


    protected function listingChecker(array $arrUrlsToTest, array $arrExpectedStrings) : array
    {
        foreach($arrUrlsToTest as $url) {

            $html = $this->fetchHtml($url);

            foreach($arrExpectedStrings as $expectedString) {

                $expectedString = mb_strtolower($expectedString);
                $this->assertStringContainsString($expectedString, $html, "Failing URL: $url");
            }
        }

        return $arrUrlsToTest;
    }
}
