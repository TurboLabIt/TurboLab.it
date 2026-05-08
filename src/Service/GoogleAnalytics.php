<?php
namespace App\Service;

use App\Exception\GoogleAnalyticsException;
use App\Repository\Cms\ArticleRepository;
use App\Repository\PhpBB\PostRepository;
use App\Repository\PhpBB\UserRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use TurboLabIt\BaseCommand\Traits\EnvTrait;


/**
 * GA4 Data API (v1beta) client.
 *
 * 📚 https://developers.google.com/analytics/devguides/reporting/data/v1
 * 📚 https://console.cloud.google.com/iam-admin/serviceaccounts
 *
 * Authentication uses a service-account JSON key + JWT bearer flow.
 * The service account e-mail must be granted "Viewer" on the GA4 property.
 */
class GoogleAnalytics
{
    const string OAUTH_TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    const string OAUTH_SCOPE        = 'https://www.googleapis.com/auth/analytics.readonly';
    const string API_ENDPOINT       = 'https://analyticsdata.googleapis.com/v1beta/properties/';
    const string CREDENTIALS_PATH   = 'var/google-analytics-credentials.json';
    const int CACHE_REPORT_SECONDS  = 3600 * 6;     // 6 hours: GA4 has data-freshness lag anyway
    const int CACHE_TOKEN_SECONDS   = 60 * 50;      // tokens last 60 minutes; refresh slightly early
    const int LAST_YEAR_OFFSET_DAYS = 364;          // 52 weeks ⇒ same day-of-week
    const array ALLOWED_RANGE_DAYS  = [7, 30, 60, 90];
    const int DEFAULT_RANGE_DAYS    = 7;
    const int TOP_PAGES_LIMIT       = 20;
    const int TOP_TAGS_LIMIT        = 20;
    const int TOP_REFERRERS_LIMIT   = 20;
    const int TOP_REFERRERS_FETCH   = 60;           // over-fetch so self-referrals can be filtered out without losing rows
    const int TOP_POSTERS_LIMIT     = 10;
    const string SELF_DOMAIN        = 'turbolab.it';

    /** Domains where every subdomain + path collapses to https://{root}/ — useful for sites with many tracking subdomains */
    const array MERGE_SUBDOMAINS_FOR = ['facebook.com'];

    use EnvTrait;


    public function __construct(
        protected array $arrConfig,
        protected HttpClientInterface $httpClient, protected TagAwareCacheInterface $cache,
        protected ProjectDir $projectDir, protected ParameterBagInterface $parameters,
        protected UserRepository $userRepository, protected PostRepository $postRepository,
        protected ArticleRepository $articleRepository
    ) {}


    protected function isCachable() : bool { return !$this->isDevOrTest(); }


    public function isConfigured() : bool
    {
        return
            !empty($this->arrConfig['propertyId']) &&
            is_readable($this->getCredentialsPath());
    }


    /**
     * Returns paired daily series for GA metrics + top pages + top referrers + registered-users:
     *   [
     *     'range'            => ['start' => 'YYYY-MM-DD', 'end' => 'YYYY-MM-DD'],
     *     'pageViews'        => [ ['date' => 'YYYY-MM-DD', 'label' => 'dd/mm', 'current' => int, 'lastYear' => int], ... ],
     *     'activeUsers'      => [ ['date' => 'YYYY-MM-DD', 'label' => 'dd/mm', 'current' => int, 'lastYear' => int], ... ],
     *     // For 'registeredUsers', `current` is the CUMULATIVE total of registered + activated users at the end of that day.
     *     'registeredUsers'  => [ ['date' => 'YYYY-MM-DD', 'label' => 'dd/mm', 'current' => int], ... ],
     *     'totals'           => [
     *                              'pageViews'       => ['current' => int, 'lastYear' => int],
     *                              'activeUsers'     => ['current' => int, 'lastYear' => int],
     *                              // 'current'    = NEW registered+activated users in the period (for headline)
     *                              // 'totalAtEnd' = CUMULATIVE total at the end of the period (for the card body + chart endpoint)
     *                              'registeredUsers' => ['current' => int, 'totalAtEnd' => int]
     *                           ],
     *     'topPages'         => [ ['path' => '/foo', 'title' => 'Title', 'displayTitle' => '...', 'iconClass' => null|string, 'iconColor' => null|string, 'views' => int], ... ],
     *     'topReferrers'     => [ ['url' => 'https://...', 'displayUrl' => '...', 'views' => int], ... ]
     *   ]
     */
    public function getStatsForChart(?int $rangeDays = null) : array
    {
        $rangeDays  = $rangeDays ?: static::DEFAULT_RANGE_DAYS;

        if( !in_array($rangeDays, static::ALLOWED_RANGE_DAYS, true) ) {
            throw new GoogleAnalyticsException(
                Response::HTTP_BAD_REQUEST,
                'Intervallo non valido: i valori ammessi sono ' . implode(', ', static::ALLOWED_RANGE_DAYS) . ' giorni'
            );
        }

        $end        = (new DateTimeImmutable('yesterday'))->setTime(0, 0);
        $start      = $end->modify('-' . ($rangeDays - 1) . ' days');

        $lastYearEnd    = $end->modify('-' . static::LAST_YEAR_OFFSET_DAYS . ' days');
        $lastYearStart  = $start->modify('-' . static::LAST_YEAR_OFFSET_DAYS . ' days');

        $current        = $this->fetchDaily($start, $end);
        $lastYear       = $this->fetchDaily($lastYearStart, $lastYearEnd);
        $topPages       = $this->fetchTopPages($start, $end);
        $topReferrers   = $this->fetchTopReferrers($start, $end);
        $topTags        = $this->fetchTopTagsByPageviews($start, $end);

        $registeredNewByDay = $this->userRepository->getNewRegistrationsByDay($start, $end);
        $registeredCumul    = $this->userRepository->countActivatedAtTimestamp( (int)$start->format('U') - 1 );

        $newsletterNewByDay = $this->userRepository->getNewsletterSignupsByDay($start, $end);
        $newsletterCumul    = $this->userRepository->countNewsletterSubscribersAtTimestamp( (int)$start->format('U') - 1 );

        $forumPostsCur      = $this->postRepository->getPostsByDay($start, $end);
        $forumPostsLy       = $this->postRepository->getPostsByDay($lastYearStart, $lastYearEnd);
        $topPosters         = $this->postRepository->getTopPosters($start, $end, static::TOP_POSTERS_LIMIT);

        $articlesPublishedCur = $this->articleRepository->getPublishedByDay($start, $end);
        $articlesPublishedLy  = $this->articleRepository->getPublishedByDay($lastYearStart, $lastYearEnd);

        $arrPageViews           = [];
        $arrActiveUsers         = [];
        $arrRegisteredUsers     = [];
        $arrNewsletterSubs      = [];
        $arrForumPosts          = [];
        $arrArticlesPublished   = [];
        $totPvCur = $totPvLy = $totAuCur = $totAuLy = $totRuNew = $totNlNew = $totFpCur = $totFpLy = $totApCur = $totApLy = 0;

        $cursor = $start;
        $i = 0;
        while( $cursor <= $end ) {

            $curKey = $cursor->format('Y-m-d');
            $lyDate = $cursor->modify('-' . static::LAST_YEAR_OFFSET_DAYS . ' days');
            $lyKey  = $lyDate->format('Y-m-d');

            $pvCur  = (int)( $current[$curKey]['pageViews']     ?? 0 );
            $pvLy   = (int)( $lastYear[$lyKey]['pageViews']     ?? 0 );
            $auCur  = (int)( $current[$curKey]['activeUsers']   ?? 0 );
            $auLy   = (int)( $lastYear[$lyKey]['activeUsers']   ?? 0 );

            // Registered + activated users: per-day NEW count and running CUMULATIVE total
            $ruNew              = (int)( $registeredNewByDay[$curKey] ?? 0 );
            $registeredCumul    += $ruNew;

            // Newsletter subscribers: same shape as registered users
            $nlNew              = (int)( $newsletterNewByDay[$curKey] ?? 0 );
            $newsletterCumul    += $nlNew;

            // Forum posts per day, with same-day-of-week last-year overlay
            $fpCur  = (int)( $forumPostsCur[$curKey] ?? 0 );
            $fpLy   = (int)( $forumPostsLy[$lyKey] ?? 0 );

            // Articles published per day, with same-day-of-week last-year overlay
            $apCur  = (int)( $articlesPublishedCur[$curKey] ?? 0 );
            $apLy   = (int)( $articlesPublishedLy[$lyKey] ?? 0 );

            $label  = $cursor->format('d/m');

            $arrPageViews[] = [
                'date'      => $curKey,
                'label'     => $label,
                'current'   => $pvCur,
                'lastYear'  => $pvLy,
                'lastYearDate' => $lyKey
            ];

            $arrActiveUsers[] = [
                'date'      => $curKey,
                'label'     => $label,
                'current'   => $auCur,
                'lastYear'  => $auLy,
                'lastYearDate' => $lyKey
            ];

            $arrRegisteredUsers[] = [
                'date'      => $curKey,
                'label'     => $label,
                'current'   => $registeredCumul
            ];

            $arrNewsletterSubs[] = [
                'date'      => $curKey,
                'label'     => $label,
                'current'   => $newsletterCumul
            ];

            $arrForumPosts[] = [
                'date'          => $curKey,
                'label'         => $label,
                'current'       => $fpCur,
                'lastYear'      => $fpLy,
                'lastYearDate'  => $lyKey
            ];

            $arrArticlesPublished[] = [
                'date'          => $curKey,
                'label'         => $label,
                'current'       => $apCur,
                'lastYear'      => $apLy,
                'lastYearDate'  => $lyKey
            ];

            $totPvCur += $pvCur;
            $totPvLy  += $pvLy;
            $totAuCur += $auCur;
            $totAuLy  += $auLy;
            $totRuNew += $ruNew;
            $totNlNew += $nlNew;
            $totFpCur += $fpCur;
            $totFpLy  += $fpLy;
            $totApCur += $apCur;
            $totApLy  += $apLy;

            $cursor = $cursor->modify('+1 day');
            $i++;
        }

        return [
            'range' => [
                'start'         => $start->format('Y-m-d'),
                'end'           => $end->format('Y-m-d'),
                'lastYearStart' => $lastYearStart->format('Y-m-d'),
                'lastYearEnd'   => $lastYearEnd->format('Y-m-d'),
                'days'          => $rangeDays
            ],
            'pageViews'             => $arrPageViews,
            'activeUsers'           => $arrActiveUsers,
            'registeredUsers'       => $arrRegisteredUsers,
            'newsletterSubscribers' => $arrNewsletterSubs,
            'forumPosts'            => $arrForumPosts,
            'articlesPublished'     => $arrArticlesPublished,
            'totals'                => [
                'pageViews'             => ['current' => $totPvCur, 'lastYear' => $totPvLy],
                'activeUsers'           => ['current' => $totAuCur, 'lastYear' => $totAuLy],
                'registeredUsers'       => ['current' => $totRuNew, 'totalAtEnd' => $registeredCumul],
                'newsletterSubscribers' => ['current' => $totNlNew, 'totalAtEnd' => $newsletterCumul],
                'forumPosts'            => ['current' => $totFpCur, 'lastYear' => $totFpLy],
                'articlesPublished'     => ['current' => $totApCur, 'lastYear' => $totApLy]
            ],
            'topPages'              => $topPages,
            'topReferrers'          => $topReferrers,
            'topTags'               => $topTags,
            'topPosters'            => $topPosters
        ];
    }


    /**
     * Returns ['YYYY-MM-DD' => ['pageViews' => int, 'activeUsers' => int]] for the given inclusive range.
     */
    protected function fetchDaily(DateTimeInterface $start, DateTimeInterface $end) : array
    {
        $startStr   = $start->format('Y-m-d');
        $endStr     = $end->format('Y-m-d');

        if( !$this->isCachable() ) {
            return $this->runReport($startStr, $endStr);
        }

        $cacheKey   = "ga4_daily_{$this->arrConfig['propertyId']}_{$startStr}_{$endStr}";

        return
            $this->cache->get($cacheKey, function(ItemInterface $cacheItem) use($startStr, $endStr) {

                $cacheItem->expiresAfter(static::CACHE_REPORT_SECONDS);
                return $this->runReport($startStr, $endStr);
            });
    }


    /**
     * Returns the most-viewed pages for the inclusive range:
     *   [ ['path' => '/foo', 'title' => 'Title', 'views' => int], ... ]
     *
     * Rows are returned exactly as GA4 returns them: no dedupe by path, no query-string stripping.
     */
    protected function fetchTopPages(DateTimeInterface $start, DateTimeInterface $end) : array
    {
        $startStr   = $start->format('Y-m-d');
        $endStr     = $end->format('Y-m-d');

        if( !$this->isCachable() ) {
            return $this->runTopPagesReport($startStr, $endStr);
        }

        $cacheKey   = "ga4_top-pages_{$this->arrConfig['propertyId']}_{$startStr}_{$endStr}";

        return
            $this->cache->get($cacheKey, function(ItemInterface $cacheItem) use($startStr, $endStr) {

                $cacheItem->expiresAfter(static::CACHE_REPORT_SECONDS);
                return $this->runTopPagesReport($startStr, $endStr);
            });
    }


    protected function runTopPagesReport(string $startDate, string $endDate) : array
    {
        $accessToken    = $this->getAccessToken();
        $url            = static::API_ENDPOINT . $this->arrConfig['propertyId'] . ':runReport';

        $payload = [
            'dateRanges'    => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions'    => [['name' => 'pagePathPlusQueryString'], ['name' => 'pageTitle']],
            'metrics'       => [['name' => 'screenPageViews']],
            'orderBys'      => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            'limit'         => static::TOP_PAGES_LIMIT
        ];

        $response =
            $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json'
                ],
                'json'      => $payload,
                'timeout'   => 10
            ]);

        $statusCode = $response->getStatusCode();
        $body       = $response->getContent(false);

        if( $statusCode !== Response::HTTP_OK ) {

            $arrError = json_decode($body, true);
            $message  = $arrError['error']['message'] ?? "GA4 Data API error (HTTP $statusCode)";
            throw new GoogleAnalyticsException($statusCode, $message);
        }

        $arrData    = json_decode($body, true) ?: [];
        $arrRows    = $arrData['rows'] ?? [];
        $arrOut     = [];

        // CMS uses " | TurboLab.it" (APP_META_TITLE_SUFFIX); forum uses " - TurboLab.it" (built from APP_SITE_NAME)
        $siteName        = trim( (string)($this->arrConfig['siteName'] ?? '') );
        $arrTitleSuffixes = array_filter([
            (string)($this->arrConfig['metaTitleSuffix'] ?? ''),
            $siteName !== '' ? ' - ' . $siteName : '',
        ]);

        foreach($arrRows as $row) {

            $path   = (string)( $row['dimensionValues'][0]['value'] ?? '' );
            $title  = (string)( $row['dimensionValues'][1]['value'] ?? '' );
            $views  = (int)( $row['metricValues'][0]['value'] ?? 0 );

            foreach($arrTitleSuffixes as $suffix) {

                if( str_ends_with($title, $suffix) ) {
                    $title = rtrim( substr($title, 0, -strlen($suffix)) );
                    break;
                }
            }

            $arrOut[] = ['path' => $path, 'title' => $title, 'views' => $views] + $this->decorateTopPageRow($path, $title);
        }

        return $arrOut;
    }


    /**
     * Returns the presentation fields for a top-page row: which icon to show, its color, and the visible title.
     *   - "/"                        ⇒ home icon + "Home page"
     *   - "/forum/..."               ⇒ forum/comments icon
     *   - "/{tag}-{id}/{art}-{id}/?" (two-segment path ending in -<digits>) ⇒ article icon
     *   - "/{slug}-{id}/?"           (single-segment path ending in -<digits>) ⇒ tag icon
     *   - everything else            ⇒ no icon, just the title (or path as fallback)
     */
    protected function decorateTopPageRow(string $path, string $title) : array
    {
        $trimmedTitle   = trim($title);
        $displayTitle   = $trimmedTitle === '' ? $path : $trimmedTitle;
        $iconClass      = null;
        $iconColor      = null;

        if( $path === '/' ) {

            $displayTitle = 'Home page';
            $iconClass    = 'fa-house';
            $iconColor    = '#1091ff';

        } elseif( str_starts_with($path, '/forum/') ) {

            $iconClass    = 'fa-comments';
            $iconColor    = '#1091ff';

        } else {

            $pathOnly = parse_url($path, PHP_URL_PATH);
            if( is_string($pathOnly) ) {

                if( preg_match('#^/[a-z0-9._-]+-\d+/[a-z0-9._-]+-\d+/?$#i', $pathOnly) ) {
                    $iconClass    = 'fa-book-open';
                    $iconColor    = '#26a269';

                } elseif( preg_match('#^/[a-z0-9._-]+-\d+/?$#i', $pathOnly) ) {
                    $iconClass    = 'fa-tag';
                    $iconColor    = '#e5a50a';
                }
            }
        }

        return [
            'displayTitle'  => $displayTitle,
            'iconClass'     => $iconClass,
            'iconColor'     => $iconColor
        ];
    }


    protected function runReport(string $startDate, string $endDate) : array
    {
        $accessToken    = $this->getAccessToken();
        $url            = static::API_ENDPOINT . $this->arrConfig['propertyId'] . ':runReport';

        $payload = [
            'dateRanges'    => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions'    => [['name' => 'date']],
            'metrics'       => [['name' => 'screenPageViews'], ['name' => 'activeUsers']],
            'orderBys'      => [['dimension' => ['dimensionName' => 'date']]],
            'keepEmptyRows' => true
        ];

        $response =
            $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json'
                ],
                'json'      => $payload,
                'timeout'   => 10
            ]);

        $statusCode = $response->getStatusCode();
        $body       = $response->getContent(false);

        if( $statusCode !== Response::HTTP_OK ) {

            $arrError = json_decode($body, true);
            $message  = $arrError['error']['message'] ?? "GA4 Data API error (HTTP $statusCode)";
            throw new GoogleAnalyticsException($statusCode, $message);
        }

        $arrData    = json_decode($body, true) ?: [];
        $arrRows    = $arrData['rows'] ?? [];
        $arrOut     = [];

        foreach($arrRows as $row) {

            $rawDate = $row['dimensionValues'][0]['value'] ?? null;
            if( empty($rawDate) || strlen($rawDate) !== 8 ) {
                continue;
            }

            $key = substr($rawDate, 0, 4) . '-' . substr($rawDate, 4, 2) . '-' . substr($rawDate, 6, 2);

            $arrOut[$key] = [
                'pageViews'     => (int)( $row['metricValues'][0]['value'] ?? 0 ),
                'activeUsers'   => (int)( $row['metricValues'][1]['value'] ?? 0 )
            ];
        }

        return $arrOut;
    }


    /**
     * Returns the most-viewed TAG pages for the inclusive range:
     *   [ ['path' => '/foo-1', 'title' => 'Title', 'views' => int], ... ]
     *
     * GA4 dimensionFilter restricts to single-segment paths ending in -<digits>, which is the tag-URL pattern.
     */
    protected function fetchTopTagsByPageviews(DateTimeInterface $start, DateTimeInterface $end) : array
    {
        $startStr   = $start->format('Y-m-d');
        $endStr     = $end->format('Y-m-d');

        if( !$this->isCachable() ) {
            return $this->runTopTagsReport($startStr, $endStr);
        }

        $cacheKey   = "ga4_top-tags_{$this->arrConfig['propertyId']}_{$startStr}_{$endStr}";

        return
            $this->cache->get($cacheKey, function(ItemInterface $cacheItem) use($startStr, $endStr) {

                $cacheItem->expiresAfter(static::CACHE_REPORT_SECONDS);
                return $this->runTopTagsReport($startStr, $endStr);
            });
    }


    protected function runTopTagsReport(string $startDate, string $endDate) : array
    {
        $accessToken    = $this->getAccessToken();
        $url            = static::API_ENDPOINT . $this->arrConfig['propertyId'] . ':runReport';

        $payload = [
            'dateRanges'        => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions'        => [['name' => 'pagePathPlusQueryString'], ['name' => 'pageTitle']],
            'metrics'           => [['name' => 'screenPageViews']],
            'orderBys'          => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            'limit'             => static::TOP_TAGS_LIMIT,
            'dimensionFilter'   => [
                'filter' => [
                    'fieldName'    => 'pagePathPlusQueryString',
                    'stringFilter' => [
                        'matchType' => 'FULL_REGEXP',
                        'value'     => '^/[a-z0-9._-]+-\\d+/?$'
                    ]
                ]
            ]
        ];

        $response =
            $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json'
                ],
                'json'      => $payload,
                'timeout'   => 10
            ]);

        $statusCode = $response->getStatusCode();
        $body       = $response->getContent(false);

        if( $statusCode !== Response::HTTP_OK ) {

            $arrError = json_decode($body, true);
            $message  = $arrError['error']['message'] ?? "GA4 Data API error (HTTP $statusCode)";
            throw new GoogleAnalyticsException($statusCode, $message);
        }

        $arrData    = json_decode($body, true) ?: [];
        $arrRows    = $arrData['rows'] ?? [];
        $arrOut     = [];

        $siteName        = trim( (string)($this->arrConfig['siteName'] ?? '') );
        $arrTitleSuffixes = array_filter([
            (string)($this->arrConfig['metaTitleSuffix'] ?? ''),
            $siteName !== '' ? ' - ' . $siteName : '',
        ]);

        foreach($arrRows as $row) {

            $path   = (string)( $row['dimensionValues'][0]['value'] ?? '' );
            $title  = (string)( $row['dimensionValues'][1]['value'] ?? '' );
            $views  = (int)( $row['metricValues'][0]['value'] ?? 0 );

            foreach($arrTitleSuffixes as $suffix) {

                if( str_ends_with($title, $suffix) ) {
                    $title = rtrim( substr($title, 0, -strlen($suffix)) );
                    break;
                }
            }

            $arrOut[] = ['path' => $path, 'title' => $title, 'views' => $views];
        }

        return $arrOut;
    }


    /**
     * Returns the most-frequent referring URLs for the inclusive range:
     *   [ ['url' => 'https://...', 'views' => int], ... ]
     *
     * Rows are returned exactly as GA4 returns them. Empty `pageReferrer` (direct traffic) is preserved as ''.
     */
    protected function fetchTopReferrers(DateTimeInterface $start, DateTimeInterface $end) : array
    {
        $startStr   = $start->format('Y-m-d');
        $endStr     = $end->format('Y-m-d');

        if( !$this->isCachable() ) {
            return $this->runTopReferrersReport($startStr, $endStr);
        }

        $cacheKey   = "ga4_top-referrers_{$this->arrConfig['propertyId']}_{$startStr}_{$endStr}";

        return
            $this->cache->get($cacheKey, function(ItemInterface $cacheItem) use($startStr, $endStr) {

                $cacheItem->expiresAfter(static::CACHE_REPORT_SECONDS);
                return $this->runTopReferrersReport($startStr, $endStr);
            });
    }


    protected function runTopReferrersReport(string $startDate, string $endDate) : array
    {
        $accessToken    = $this->getAccessToken();
        $url            = static::API_ENDPOINT . $this->arrConfig['propertyId'] . ':runReport';

        $payload = [
            'dateRanges'    => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions'    => [['name' => 'pageReferrer']],
            'metrics'       => [['name' => 'screenPageViews']],
            'orderBys'      => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            'limit'         => static::TOP_REFERRERS_FETCH
        ];

        $response =
            $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json'
                ],
                'json'      => $payload,
                'timeout'   => 10
            ]);

        $statusCode = $response->getStatusCode();
        $body       = $response->getContent(false);

        if( $statusCode !== Response::HTTP_OK ) {

            $arrError = json_decode($body, true);
            $message  = $arrError['error']['message'] ?? "GA4 Data API error (HTTP $statusCode)";
            throw new GoogleAnalyticsException($statusCode, $message);
        }

        $arrData    = json_decode($body, true) ?: [];
        $arrRows    = $arrData['rows'] ?? [];
        $arrByUrl   = [];

        foreach($arrRows as $row) {

            $refUrl = (string)( $row['dimensionValues'][0]['value'] ?? '' );
            $views  = (int)( $row['metricValues'][0]['value'] ?? 0 );

            if( $this->isSelfReferral($refUrl) ) {
                continue;
            }

            $normalizedUrl = $this->normalizeReferrerUrl($refUrl);

            if( !isset($arrByUrl[$normalizedUrl]) ) {
                $arrByUrl[$normalizedUrl] = [
                    'url'           => $normalizedUrl,
                    'displayUrl'    => rtrim( preg_replace('#^https?://#i', '', $normalizedUrl) ?? $normalizedUrl, '/' ),
                    'views'         => 0
                ];
            }

            $arrByUrl[$normalizedUrl]['views'] += $views;
        }

        usort($arrByUrl, fn($a, $b) => $b['views'] <=> $a['views']);
        return array_slice($arrByUrl, 0, static::TOP_REFERRERS_LIMIT);
    }


    protected function normalizeReferrerUrl(string $refUrl) : string
    {
        if( $refUrl === '' ) {
            return $refUrl;
        }

        // Domains where every subdomain + path collapses into https://{root}/
        $host = parse_url($refUrl, PHP_URL_HOST);
        if( is_string($host) && $host !== '' ) {

            $hostLower = strtolower($host);
            foreach(static::MERGE_SUBDOMAINS_FOR as $rootDomain) {

                if( $hostLower === $rootDomain || str_ends_with($hostLower, '.' . $rootDomain) ) {
                    return 'https://' . $rootDomain . '/';
                }
            }
        }

        // Force https:// and strip leading www. so http/https and www-variants of the same URL merge
        $withoutSchemeAndWww = preg_replace('#^[a-z][a-z0-9+\-.]*://(?:www\.)?#i', '', $refUrl) ?? $refUrl;
        return 'https://' . $withoutSchemeAndWww;
    }


    protected function isSelfReferral(string $refUrl) : bool
    {
        if( $refUrl === '' ) {
            return false;
        }

        $host = parse_url($refUrl, PHP_URL_HOST);
        if( !is_string($host) || $host === '' ) {
            return false;
        }

        $host = strtolower($host);
        return $host === static::SELF_DOMAIN || str_ends_with($host, '.' . static::SELF_DOMAIN);
    }


    protected function getAccessToken() : string
    {
        if( !$this->isCachable() ) {
            return $this->requestAccessToken();
        }

        $cacheKey = 'ga4_access_token_' . md5($this->getCredentialsPath());

        return
            $this->cache->get($cacheKey, function(ItemInterface $cacheItem) {

                $cacheItem->expiresAfter(static::CACHE_TOKEN_SECONDS);
                return $this->requestAccessToken();
            });
    }


    protected function requestAccessToken() : string
    {
        $arrCredentials = $this->loadCredentials();

        $now    = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claim  = [
            'iss'   => $arrCredentials['client_email'],
            'scope' => static::OAUTH_SCOPE,
            'aud'   => static::OAUTH_TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600
        ];

        $segments   = [$this->base64UrlEncode( json_encode($header) ), $this->base64UrlEncode( json_encode($claim) )];
        $signingIn  = implode('.', $segments);
        $signature  = '';

        $signOk = openssl_sign($signingIn, $signature, $arrCredentials['private_key'], OPENSSL_ALGO_SHA256);
        if( !$signOk ) {
            throw new GoogleAnalyticsException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Impossibile firmare il JWT con la chiave privata del service account'
            );
        }

        $segments[] = $this->base64UrlEncode($signature);
        $jwt        = implode('.', $segments);

        $response =
            $this->httpClient->request('POST', static::OAUTH_TOKEN_URL, [
                'body' => [
                    'grant_type'    => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'     => $jwt
                ],
                'timeout' => 10
            ]);

        $statusCode = $response->getStatusCode();
        $body       = $response->getContent(false);

        if( $statusCode !== Response::HTTP_OK ) {

            $arrError = json_decode($body, true);
            $message  =
                ($arrError['error_description'] ?? null) ?:
                ($arrError['error'] ?? "OAuth token exchange failed (HTTP $statusCode)");

            throw new GoogleAnalyticsException($statusCode, "Google OAuth: $message");
        }

        $arrData = json_decode($body, true) ?: [];
        if( empty($arrData['access_token']) ) {
            throw new GoogleAnalyticsException(
                Response::HTTP_BAD_GATEWAY,
                'Risposta OAuth Google priva di access_token'
            );
        }

        return $arrData['access_token'];
    }


    protected function loadCredentials() : array
    {
        $path = $this->getCredentialsPath();

        if( !is_readable($path) ) {
            throw new GoogleAnalyticsException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                "Credenziali Google Analytics non leggibili: $path"
            );
        }

        $arrCredentials = json_decode( file_get_contents($path), true );

        if(
            empty($arrCredentials['client_email']) ||
            empty($arrCredentials['private_key'])
        ) {
            throw new GoogleAnalyticsException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Il file di credenziali non contiene i campi client_email e private_key richiesti'
            );
        }

        return $arrCredentials;
    }


    protected function getCredentialsPath() : string
        { return rtrim($this->projectDir->getProjectDir(), '/') . '/' . static::CREDENTIALS_PATH; }


    protected function base64UrlEncode(string $data) : string
        { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
}
