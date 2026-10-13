<?php
namespace App\Tests\Editor;

use App\Command\ShareOnSocialCommand;
use App\Service\YouTubeChannelApi;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use stdClass;
use Symfony\Component\DomCrawler\Crawler;
use TurboLabIt\MessengersBundle\TelegramMessenger;


/**
 * The YouTube search API returns snippet.title HTML-escaped (&quot; &#39; &amp;) and
 * snippet.description raw. YouTubeChannelApi used to pass the title on as-is, so every consumer
 * escaped it a second time: the Telegram post read `NON SI CHIAMA &quot;CHIAVETTA USB&quot;!`
 * (the #29 escaping in ShareOnSocialCommand assumes a raw title), and so did the homepage video
 * player and the newsletter, through Twig auto-escape.
 *
 * @see \App\Service\YouTubeChannelApi::parseLatestVideosResponse()
 */
class YouTubeTitleEncodingTest extends BaseT
{
    public static function titleProvider() : array
    {
        return [
            // snippet.title, as the search API returns it => the title the reader must see
            'double quotes' => [
                '🤣 NON SI CHIAMA &quot;CHIAVETTA USB&quot;!',
                '🤣 NON SI CHIAMA "CHIAVETTA USB"!'
            ],
            'apostrophe and quotes' => [
                'BITCOIN, IDENTITA&#39; SVELATA: riassunto documentario &quot;Money Electric: The Bitcoin Mystery&quot;',
                'BITCOIN, IDENTITA\' SVELATA: riassunto documentario "Money Electric: The Bitcoin Mystery"'
            ],
            'ampersand' => [
                'PC &amp; SMARTPHONE: OFFERTE #blackfriday',
                'PC & SMARTPHONE: OFFERTE #blackfriday'
            ],
            'an escaped entity stays literal text' => [
                'COS&#39;È &amp;nbsp; IN HTML',
                'COS\'È &nbsp; IN HTML'
            ],
        ];
    }


    /** Runs a search API response through the real parser, like getLatestVideos() does. */
    private function parseVideo(string $apiTitle) : stdClass
    {
        $searchResponse = [
            'kind'  => 'youtube#searchListResponse',
            'items' => [[
                'kind'      => 'youtube#searchResult',
                'id'        => ['kind' => 'youtube#video', 'videoId' => 'l84HVIR_E3s'],
                'snippet'   => [
                    'publishedAt'   => '2025-10-21T08:00:05Z',
                    'title'         => $apiTitle,
                    'description'   => 'Per parlare con gli altri, che termine usate?',
                    'thumbnails'    => [
                        'medium'    => ['url' => 'https://i.ytimg.com/vi/l84HVIR_E3s/mqdefault.jpg'],
                        'high'      => ['url' => 'https://i.ytimg.com/vi/l84HVIR_E3s/hqdefault.jpg']
                    ]
                ]
            ]]
        ];

        $arrVideos =
            (new ReflectionMethod(YouTubeChannelApi::class, 'parseLatestVideosResponse'))
                ->invoke(static::getService(YouTubeChannelApi::class), json_encode($searchResponse));

        $this->assertCount(1, $arrVideos);
        return reset($arrVideos);
    }


    /** What a reader sees once Telegram, or the browser, has parsed the HTML. */
    private function renderedText(string $html) : string
        { return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'); }


    #[DataProvider('titleProvider')]
    public function testTitleIsDecoded(string $apiTitle, string $expectedTitle) : void
    {
        $this->assertSame($expectedTitle, $this->parseVideo($apiTitle)->title);
    }


    #[DataProvider('titleProvider')]
    public function testTelegramPostShowsTheTitle(string $apiTitle, string $expectedTitle) : void
    {
        $video = $this->parseVideo($apiTitle);

        $composed =
            (new ReflectionMethod(ShareOnSocialCommand::class, 'buildTelegramArticleMessageHtml'))
                ->invoke(static::getService(ShareOnSocialCommand::class), '📺', $video->url, $video->title);

        // messageEncoder() is the final transform sendMessage() applies before POSTing
        $sent =
            (new ReflectionMethod(TelegramMessenger::class, 'messageEncoder'))
                ->invoke(static::getService(TelegramMessenger::class), $composed);

        $this->assertSame("📺 $expectedTitle", $this->renderedText($sent));
    }


    #[DataProvider('titleProvider')]
    public function testHomepageVideoPlayerShowsTheTitle(string $apiTitle, string $expectedTitle) : void
    {
        $html =
            static::getTwig()->render('home/video-player.html.twig', [
                'Videos' => [ $this->parseVideo($apiTitle) ]
            ]);

        $crawler = new Crawler($html);
        $this->assertSame($expectedTitle, $crawler->filter('#tli-youtube-video-player .title')->text());
        $this->assertSame($expectedTitle, $crawler->filter('.tli-video-thumb-title')->text());
    }
}
