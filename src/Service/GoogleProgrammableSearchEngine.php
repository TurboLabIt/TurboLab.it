<?php
namespace App\Service;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


/**
 * 📚 https://console.cloud.google.com/apis/api/customsearch.googleapis.com/metrics?invt=AbeCrw&project=tli2-search-1736167497152
 * 📚 https://programmablesearchengine.google.com/controlpanel/overview?cx=c8985352856be0e00
 *
 * Custom Search JSON API provides 100 search queries per day for free
 */
#[\Deprecated(message: "No longer in use - https://github.com/TurboLabIt/TurboLab.it/issues/73")]
class GoogleProgrammableSearchEngine
{
    const string ENDPOINT       = 'https://customsearch.googleapis.com';
    const string SITE_RESULTS   = 'website';
    const string FORUM_RESULTS  = 'forum';


    public function __construct(
        protected array $arrConfig,
        protected TagAwareCacheInterface $cache, protected HttpClientInterface $httpClient
    ) {}


    public function query(string $term)
    {
        $url = static::ENDPOINT . '/customsearch/v1';

        $arrExcludeSlugs = [
            '/utenti/*', '/ajax/*', '/editor/*', '/feed/*', '/scarica/*', '/home/*', '/immagini/*', '/calendario/*',
            '/news/*', '/newsletter/*', '/viste/*', '/forum/*', '/newsletter-turbolab.it-1349/*'
        ];

        $hqExcludeString = '';
        foreach($arrExcludeSlugs as $excludePattern) {
            $hqExcludeString .= "-site:turbolab.it$excludePattern ";
        }

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


        try {

            return $response->toArray();

        } catch(\Exception $ex) {

            $statusCode = $response->getStatusCode();
            $arrResponse = $response->toArray(false);

            $errorMessage = trim('
                Si è verificato un errore con i risultati di ricerca forniti da Google.
                Per favore, apri una nuova discussione sul nostro forum per segnalare il problema.<br><br>
                Dettagli dell\'errore: 🦠 ' . ($arrResponse["error"]["message"] ?? 'Nessuno')
            );

            throw new HttpException($statusCode, $errorMessage);
        }
    }
}
