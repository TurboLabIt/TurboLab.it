<?php
namespace App\Tests\Smoke;

use App\Command\TagAggregatorCommand;
use App\Service\Cms\Tag;
use App\Tests\BaseT;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class NginxRedirectTest extends BaseT
{
    protected static array $arrUrlToTest = [];


    public static function urlProvider(): array
    {
        $arrUrlToTest = [
            [
                'requestUrl'    => '/codice',
                'redirectToUrl' => 'https://github.com/TurboLabIt/TurboLab.it',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/dona',
                'redirectToUrl' => '/1126',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/iscriviti',
                'redirectToUrl' => '/forum/ucp.php?mode=register',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/something-245',
                'redirectToUrl' => '/ssd-dischi-fissi-hard-disk-570',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/tag/criptovalute-bitcoin-ethereum-litecoin',
                'redirectToUrl' => '/bitcoin-criptovalute-blockchain-4904',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/tag/intelligenza%20artificiale',
                'redirectToUrl' => '/ai-intelligenza-artificiale-6960',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/tag/mac',
                'redirectToUrl' => '/mac-macos-26',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/tag/macos',
                'redirectToUrl' => '/mac-macos-26',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/tag/apple-mac-macos',
                'redirectToUrl' => '/mac-macos-26',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/tag/apple%20mac%20macos',
                'redirectToUrl' => '/mac-macos-26',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/tag/ios',
                'redirectToUrl' => '/iphone-ipad-ios-39',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/tag/iphone',
                'redirectToUrl' => '/iphone-ipad-ios-39',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/viste/tutti',
                'redirectToUrl' => '/',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/viste/tutti/77',
                'redirectToUrl' => '/',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/viste/news',
                'redirectToUrl' => '/news',
                'alsoTestWithTrailingSlash' => true
            ],
            [
                'requestUrl'    => '/viste/news/100',
                'redirectToUrl' => '/news',
                'alsoTestWithTrailingSlash' => true
            ]
        ];

        $tags = static::getTagCollection()->load(TagAggregatorCommand::BAD_TAGS);

        foreach(TagAggregatorCommand::BAD_TAGS as $badTag => $replacementTagId) {

            if( str_starts_with($badTag, 'windows') ) {
                continue;
            }

            $tag = $tags->get($replacementTagId);
            if( empty($tag) ) {
                continue;
            }

            $redirectToUrl = $tags->get($replacementTagId)->getUrl(null, UrlGeneratorInterface::ABSOLUTE_PATH);

            $arrUrlToTest[] = [
                'requestUrl'    => "/tag/$badTag",
                'redirectToUrl' => $redirectToUrl,
                'alsoTestWithTrailingSlash' => true
            ];
        }


        $windowsTagUrl = $tags->get(Tag::ID_WINDOWS)->getUrl(null, UrlGeneratorInterface::ABSOLUTE_PATH);

        foreach(TagAggregatorCommand::BAD_TAGS as $badTag => $replacementTagId) {

            if( !str_starts_with($badTag, 'windows') ) {
                continue;
            }

            $arrUrlToTest[] = [
                'requestUrl'    => "/tag/$badTag",
                'redirectToUrl' => $windowsTagUrl,
                'alsoTestWithTrailingSlash' => true
            ];

            $arrUrlToTest[] = [
                'requestUrl'    => "/tag/" . str_ireplace('windows', 'windows ', $badTag),
                'redirectToUrl' => $windowsTagUrl,
                'alsoTestWithTrailingSlash' => true
            ];

            $arrUrlToTest[] = [
                'requestUrl'    => "/tag/" . str_ireplace('windows', 'windows-', $badTag),
                'redirectToUrl' => $windowsTagUrl,
                'alsoTestWithTrailingSlash' => true
            ];
        }


        foreach([1256,2011,2291,2372,10462,13182] as $windowsVersionTagId) {

            $arrUrlToTest[] = [
                'requestUrl'    => "/windows-ver-$windowsVersionTagId",
                'redirectToUrl' => $windowsTagUrl,
                'alsoTestWithTrailingSlash' => false
            ];
        }


        return $arrUrlToTest;
    }


    #[DataProvider('urlProvider')]
    public function testRedirect(string $requestUrl, string $redirectToUrl, bool $alsoTestWithTrailingSlash)
    {
        if( stripos($requestUrl, 'https://') !== 0 ) {
            $requestUrl = $this->generateUrl() . ltrim($requestUrl, '/');
        }

        $arrHttpClientOptions = [
            'max_redirects'     => 0,
            'verify_peer'       => false,
            'verify_host'       => false,
            'timeout'           => 5
        ];

        $response = HttpClient::create($arrHttpClientOptions)->request('GET', $requestUrl);

        // HTTP 301 check
        $httpStatusCode = $response->getStatusCode();
        $this->assertSame(Response::HTTP_MOVED_PERMANENTLY, $httpStatusCode);
        $responseLocation = $response->getHeaders(false)['location'][0];
        $this->assertSameUrlWithoutDomain($redirectToUrl, $responseLocation);

        // test the redirect-to URL
        if( stripos($redirectToUrl, '/forum/') !== false ) {

            // forum URLs are not managed by Symfony, but still end with an HTTPClient timeout

        } else if( str_starts_with($responseLocation, $this->generateUrl() ) ) {

            // internal URL, try to pen it
            $this->browse($responseLocation);
            try {
                // if it's another redirect, follow it
                // if it's not, ignore the LogicException
                static::$client->followRedirect();
            } catch (LogicException) {}

            $this->assertResponseIsSuccessful();

        } else {

            $httpStatusCode =
                HttpClient::create($arrHttpClientOptions)
                    ->request('GET', $responseLocation)
                    ->getStatusCode();

            $this->assertSame(Response::HTTP_OK, $httpStatusCode);
        }

        // also test request with trailing-slash
        if($alsoTestWithTrailingSlash) {
            $this->testRedirect($requestUrl . "/", $redirectToUrl, false);
        }
    }
}
