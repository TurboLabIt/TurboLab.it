<?php
namespace App\Service;

use App\Exception\YouTubeException;
use DateTime;
use DateTimeZone;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use TurboLabIt\BaseCommand\Traits\EnvTrait;


/**
 * Quota usage: https://console.cloud.google.com/apis/api/youtube.googleapis.com/quotas?invt=AbeCrw&project=turbolabit
 * Quota calculator: https://developers.google.com/youtube/v3/determine_quota_cost
 *
 * Default budget: 10.000 per day
 * Search API cost: 100 ➡ 10.000/100 = **max 100 calls per day**
 */
class YouTubeChannelApi
{
    const int CACHE_MINUTES     = 17;
    const string API_ENDPOINT   = "https://youtube.googleapis.com/youtube/v3/";

    use EnvTrait;


    public function __construct(
        protected array $arrConfig, protected HttpClientInterface $httpClient,
        protected TagAwareCacheInterface $cache, protected ProjectDir $projectDir,
        protected ParameterBagInterface $parameters
    ) {}


    public function getLatestVideos(int $results = 10) : array
    {
        $cacheKey = "youtube_latest-videos_" . $this->arrConfig["channelId"]  ."_" . $results;

        if( $this->isNotProd() ) {

            $storedResponse = $this->getStoredResponse($cacheKey, 48);
            if( !empty($storedResponse) ) {
                return $storedResponse;
            }
        }

        return
            $this->cache->get($cacheKey, function (ItemInterface $cacheItem) use($results, $cacheKey) {

                $cacheLife = date('H:i') < '06:00' ? (3600 * 3) : (static::CACHE_MINUTES * 60);

                try {
                    $response = $this->getLatestVideosUncached($results, $cacheKey);
                    $cacheItem->expiresAfter($cacheLife);

                } catch(YouTubeException $ex) {

                    $response = $this->getStoredResponse($cacheKey);

                    if( empty($response) ) {
                        throw $ex;
                    }

                    $cacheItem->expiresAfter(static::CACHE_MINUTES * 60 * 10);
                }

                return $response;
            });
    }


    protected function getLatestVideosUncached(int $results, string $storeFileName) : array
    {
        $apiEndpoint = static::API_ENDPOINT . "search";

        $arrParams = [
            "part"          => "snippet",
            "channelId"     => $this->arrConfig["channelId"],
            "key"           => $this->arrConfig["apiKey"],
            "type"          => "video",
            "maxResults"    => $results,
            "order"         => "date"
        ];

        $response =
            $this->httpClient->request('GET', $apiEndpoint, [
                'query'     => $arrParams,
                'timeout'   => 5
            ]);

        try {
            $txtJsonResponse= $response->getContent(false);
            $statusCode = $response->getStatusCode();
            if( $statusCode != Response::HTTP_OK ) {
                throw new Exception($txtJsonResponse, $statusCode);
            }

            $this->storeResponse($txtJsonResponse, $storeFileName);

        } catch(Exception $ex) {
            throw new YouTubeException($ex->getCode(), $ex->getMessage());
        }

        return $this->parseLatestVideosResponse($txtJsonResponse);
    }


    protected function parseLatestVideosResponse(string $txtJsonResponse) : array
    {
        $objResponse = json_decode($txtJsonResponse);

        $utcTimeZone    = new DateTimeZone('UTC');
        $currentTimeZone= new DateTimeZone(date_default_timezone_get());

        $arrVideos = [];
        foreach($objResponse->items as $oneVideoItem) {

            $arrVideos[] = (object)[
                "id"            => $oneVideoItem->id->videoId,
                "source"        => 'youtube',
                "url"           => "https://www.youtube.com/watch?v=" . $oneVideoItem->id->videoId,
                "embedUrl"      => "https://www.youtube-nocookie.com/embed/" . $oneVideoItem->id->videoId . "?rel=0&enablejsapi=1&autoplay=1",
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


    protected function storeResponse(string $txtJsonResponse, string $storeFileName) : static
    {
        $fullFileName = $this->projectDir->getVarDirFromFilePath("$storeFileName.json");
        file_put_contents($fullFileName, $txtJsonResponse);
        return $this;
    }


    protected function getStoredResponse(string $storeFileName, ?int $maxHoursLife = null) : ?array
    {
        $fullFileName = $this->projectDir->getVarDirFromFilePath("$storeFileName.json");

        if( !is_readable($fullFileName) ) {
            return null;
        }

        if( !empty($maxHoursLife) ) {

            $fileTimeModification = filemtime($fullFileName);
            $ageInSeconds = time() - $fileTimeModification;

            if ($ageInSeconds > $maxHoursLife * 3600) { // 5 hours in seconds
                return null;
            }
        }

        $txtJsonResponse = file_get_contents($fullFileName);

        if( empty($txtJsonResponse) ) {
            return null;
        }

        return $this->parseLatestVideosResponse($txtJsonResponse);
    }
}
