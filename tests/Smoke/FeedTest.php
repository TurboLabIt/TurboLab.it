<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;


class FeedTest extends BaseT
{
    public static function feedToTestProvider()  : Generator
    {
        yield from [
            ['/feed'], ['/feed/nuovi-finiti'], ['/feed/fullfeed']
        ];
    }


    #[DataProvider('feedToTestProvider')]
    public function testOpenAllFeeds(string $url)
    {
        $errorMessage = "Failing URL: " . $url;
        $xml = $this->fetchHtml($url, Request::METHOD_GET, false);
        $this->assertResponseHeaderSame('content-type', 'application/xml', $errorMessage);

        // W3C form
        $httpClient = HttpClient::create();
        $response =
            $httpClient->request(Request::METHOD_POST, 'https://validator.w3.org/feed/check.cgi', [
                'body' => [
                    "rawdata"   => $xml,
                    "manual"    => 1
            ]
        ]);

        $statusCode = $response->getStatusCode();
        $this->assertEquals(Response::HTTP_OK, $statusCode, "W3C submit error: ##$statusCode##");

        $responseHtml = $response->getContent();
        $this->assertStringContainsString("Congratulations!", $responseHtml, "W3C didn't congratulate for $url");
        $this->assertStringContainsString("This is a valid RSS feed", $responseHtml, "W3C didn't report a valid RSS feed for $url");
    }
}
