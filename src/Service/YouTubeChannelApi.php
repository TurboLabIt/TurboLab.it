<?php
namespace App\Service;

use App\Exception\YouTubeException;
use DateTime;
use DateTimeZone;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class YouTubeChannelApi
{
    const int CACHE_MINUTES     = 60;
    const string API_ENDPOINT   = "https://youtube.googleapis.com/youtube/v3/";


    public function __construct(protected array $arrConfig, protected HttpClientInterface $httpClient, protected TagAwareCacheInterface $cache)
    { }


    public function getLatestVideos(int $results = 10): array
    {
        $cacheKey   = "youtube_latest-videos_" . $this->arrConfig["channelId"]  ."_" . $results;
        return
            $this->cache->get($cacheKey, function (CacheItem $item) use($results) {

                $response = $this->getLatestVideosUncached($results);

                if( empty($response) ) {

                    $item->expiresAfter(1);

                } else {

                    $item->expiresAfter(static::CACHE_MINUTES * 60);
                }

                return $response;
            });
    }


    public function getLatestVideosUncached(int $results = 5): array
    {
        $apiEndpoint = static::API_ENDPOINT . "search";

        $arrParams   = [
            "part"          => "snippet",
            "channelId"     => $this->arrConfig["channelId"],
            "key"           => $this->arrConfig["apiKey"],
            "maxResults"    => $results,
            "order"         => "date"
        ];

        $response = $this->httpClient->request('GET', $apiEndpoint, [
            'query'     => $arrParams,
            'timeout'   => 5
        ]);

        try {
            $txtResponse= $response->getContent(false);
            $statusCode = $response->getStatusCode();
            if( $statusCode != Response::HTTP_OK ) {
                throw new Exception($txtResponse, $statusCode);
            }

        } catch(Exception $ex) {
            throw new YouTubeException($ex->getCode(), $ex->getMessage());
        }

        $objResponse = json_decode($txtResponse);

        $utcTimeZone    = new DateTimeZone('UTC');
        $currentTimeZone= new DateTimeZone(date_default_timezone_get());

        $arrVideos = [];
        foreach($objResponse->items as $oneVideoItem) {

            // this happens when the item is a playlist
            if( empty($oneVideoItem->id->videoId) ) {
                continue;
            }

            $arrVideos[] = (object)[
                "id"            => $oneVideoItem->id->videoId,
                "source"        => 'youtube',
                "url"           => "https://www.youtube.com/watch?v=" . $oneVideoItem->id->videoId,
                "embedUrl"      => "https://www.youtube-nocookie.com/embed/" . $oneVideoItem->id->videoId . "?rel=0&enablejsapi=1",
                "title"         => trim($oneVideoItem->snippet->title),
                "abstract"      => trim($oneVideoItem->snippet->description),
                "thumbnails"    => $oneVideoItem->snippet->thumbnails,
                "publishedAt"   =>
                    DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $oneVideoItem->snippet->publishedAt, $utcTimeZone)
                        ->setTimezone($currentTimeZone)
            ];
        }

        return $arrVideos;
    }
}
