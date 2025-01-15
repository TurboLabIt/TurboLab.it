<?php
namespace App\Service;

use Exception;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class GoogleProgrammableSearchEngine
{
    const string ENDPOINT       ='https://customsearch.googleapis.com';
    const string SITE_RESULTS   = 'website';
    const string FORUM_RESULTS  = 'forum';


    public function __construct(
        protected array $arrConfig,
        protected TagAwareCacheInterface $cache, protected HttpClientInterface $httpClient
    ) {}


    public function query(string $term)
    {
        $url = static::ENDPOINT . '/customsearch/v1';

        $cachedResponse =
            $this->cache->get("{$url}_{$term}", function(CacheItem $cache)
            use($url, $term) {

                $cache->expiresAfter(3600 * 48); // 2 days
                $cache->tag(["search"]);

                $arrExcludeSlugs = [
                    '/utenti/*', '/ajax/*', '/editor/*', '/feed/*', '/scarica/*', '/home/*', '/immagini/*', '/calendario/*',
                    '/news/*', '/newsletter/*', '/viste/*', '/forum/*', '/newsletter-turbolab.it-1349/*'
                ];

                $hqExcludeString = '';
                foreach($arrExcludeSlugs as $excludePattern) {
                    $hqExcludeString .= "-site:turbolab.it$excludePattern ";
                }

                try {
                    $response =
                        $this->httpClient->request('GET', $url, [
                            'query' => [
                                'key'   => $this->arrConfig['apiKey'],
                                'cx'    => $this->arrConfig['engineId'],
                                'gl'    => 'it',
                                'hl'    => 'it',
                                'lr'    => 'lang_it',
                                // https://gemini.google.com/app/6a08800bf7117e69
                                'hq'    => trim($hqExcludeString),
                                'num'   => 10, // max 10,
                                'q'     => trim($term)
                            ]
                        ]);

                    $arrResponse = $response->toArray();
                    return $arrResponse;

                } catch(Exception) {
                    return null;
                }
            });

        return $cachedResponse;
    }
}
