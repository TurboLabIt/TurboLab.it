<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;
use Generator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;


class NginxRedirectTest extends BaseT
{
    public static function urlProvider(): Generator
    {
        yield from [
            [
                'requestUrl'    => '/codice',
                'redirectToUrl' => 'https://github.com/TurboLabIt/TurboLab.it'
            ],
            [
                'requestUrl'    => '/dona',
                'redirectToUrl' => '/1126'
            ],
            [
                'requestUrl'    => '/iscriviti',
                'redirectToUrl' => '/forum/ucp.php?mode=register'
            ],
            [
                'requestUrl'    => '/something-245',
                'redirectToUrl' => '/ssd-dischi-fissi-hard-disk-570'
            ]
            /*[
                'requestUrl'    => '',
                'redirectToUrl' => ''
            ]*/
        ];
    }


    #[DataProvider('urlProvider')]
    public function testRedirect(string $requestUrl, string $redirectToUrl)
    {
        $httpClient =
            HttpClient::create([
                'max_redirects' => 0,
                'verify_peer'   => false,
                'verify_host'   => false
            ]);

        if( stripos($requestUrl, 'https://') !== 0 ) {
            $requestUrl = $this->generateUrl() . ltrim($requestUrl, '/');
        }

        $response = $httpClient->request('GET', $requestUrl);

        // 301 HTTP Status Code check
        $httpStatusCode = $response->getStatusCode();
        $this->assertSame(Response::HTTP_MOVED_PERMANENTLY, $httpStatusCode);

        // HTTP location check
        if( stripos($redirectToUrl, 'https://') !== 0 ) {
            $redirectToUrl = $this->generateUrl() . ltrim($redirectToUrl, '/');
        }

        $this->assertSame($redirectToUrl, $response->getHeaders(false)['location'][0]);

        // test the redirect-to URL
        if( stripos( $redirectToUrl, $this->generateUrl() . 'forum' ) !== false ) {

            // forum URLs are not managed by Symfony, but still end with an HTTPClient timeout

        } else if( stripos( $redirectToUrl, $this->generateUrl() ) === 0 ) {

            $this->browse($redirectToUrl);
            try {
                static::$client->followRedirect();
            } catch (LogicException) {}

            $this->assertResponseIsSuccessful();

        } else {

            $httpStatusCode =
                HttpClient::create()
                    ->request('GET', $redirectToUrl)
                    ->getStatusCode();

            $this->assertSame(Response::HTTP_OK, $httpStatusCode);

            unset($httpStatusCode);
        }

        // also test request with trailing-slash
        if( !str_ends_with($requestUrl, '/') ) {
            $this->testRedirect($requestUrl . "/", $redirectToUrl);
        }
    }
}
