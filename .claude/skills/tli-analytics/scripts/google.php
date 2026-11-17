#!/usr/bin/env php
<?php
/**
 * Read-only questions to Google Analytics (GA4) and Google Search Console about TurboLab.it, with the service
 * account the /statistiche page already uses. Nothing to install: PHP with openssl.
 *
 *   php google.php ga4 [--from 28daysAgo] [--to yesterday] [--metrics screenPageViews,activeUsers]
 *                      [--dimensions pagePath] [--filter 'pagePath=@windows'] [--order -screenPageViews] [--limit 25]
 *   php google.php realtime [--metrics activeUsers] [--dimensions unifiedScreenName] [--limit 25]
 *   php google.php gsc [--from 30daysAgo] [--to 3daysAgo] [--dimensions query] [--type web|discover|googleNews]
 *                      [--filter 'page=@/windows-10/'] [--limit 25]
 *   php google.php article ID [--days 28]      one article: its views by day, and what Google Search sends to it
 *
 * Filters, repeatable (all must hold): NAME==value (is), NAME=@text (contains), NAME=^text (starts with),
 * NAME=$text (ends with), NAME=~regex (matches). Put them in single quotes.
 * --order NAME sorts ascending, --order -NAME descending. Dates: YYYY-MM-DD, today, yesterday, NdaysAgo.
 *
 * It reads the key in var/google-analytics-credentials.json and the property ID from the .env files, both local
 * to the box, and prints neither. The key's two scopes are read-only. It talks to Google's API hosts only.
 */
declare(strict_types=1);

const PROJECT_DIR  = __DIR__ . '/../../../../';
const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
const GA4_URL      = 'https://analyticsdata.googleapis.com/v1beta/properties/';
const GSC_URL      = 'https://www.googleapis.com/webmasters/v3/sites/';
const GSC_SITE     = 'sc-domain:turbolab.it';
const SCOPE_GA4    = 'https://www.googleapis.com/auth/analytics.readonly';
const SCOPE_GSC    = 'https://www.googleapis.com/auth/webmasters.readonly';
const GOOGLE_HOSTS = ['oauth2.googleapis.com', 'analyticsdata.googleapis.com', 'www.googleapis.com'];

function fail(string $message, int $code = 1) : never
{
    fwrite(STDERR, "FAILED: $message\n");
    exit($code);
}

/** Text that came from Google (a page path, a search query: other people's words), safe to print. */
function shown(string $text) : string
{
    return trim(preg_replace('/[\p{Cc}\p{Zl}\p{Zp}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', ' ', mb_scrub($text)) ?? '');
}

function options(array $arguments) : array
{
    $options = ['filter' => []];
    while ($arguments) {
        $name = array_shift($arguments);
        if (!str_starts_with($name, '--') || !$arguments) {
            fail("«{$name}»: options are --name value. See the top of this file", 2);
        }
        if ($name === '--filter') {
            $options['filter'][] = array_shift($arguments);
        } else {
            $options[substr($name, 2)] = array_shift($arguments);
        }
    }
    return $options;
}

function names(?string $list) : array
{
    return array_values(array_filter(array_map('trim', explode(',', $list ?? ''))));
}

function propertyId() : string
{
    $id = getenv('APP_GOOGLE_ANALYTICS_PROPERTY_ID') ?: '';
    // what Symfony would load, most specific first
    foreach (['.env.dev.local', '.env.staging.local', '.env.prod.local', '.env.local', '.env'] as $file) {
        if ($id === '' && is_readable(PROJECT_DIR . $file)
                && preg_match('/^APP_GOOGLE_ANALYTICS_PROPERTY_ID=["\']?(\d+)["\']?\s*$/m', file_get_contents(PROJECT_DIR . $file), $match)) {
            $id = $match[1];
        }
    }
    if (!ctype_digit($id)) {
        fail("no GA4 property ID: set APP_GOOGLE_ANALYTICS_PROPERTY_ID in .env.<env>.local (docs/stats.md)");
    }
    return $id;
}

function request(string $url, string $body, array $headers) : array
{
    if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array(parse_url($url, PHP_URL_HOST), GOOGLE_HOSTS, true)) {
        fail("refusing to call " . shown($url) . ": not one of Google's API hosts");
    }
    $context = stream_context_create(['http' => [
        'method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 60,
        'ignore_errors' => true, 'follow_location' => 0,
    ]]);
    $answer = @file_get_contents($url, false, $context);
    $first  = (http_get_last_response_headers() ?? [])[0] ?? '';
    $status = preg_match('/\s(\d{3})\s/', $first, $match) ? (int) $match[1] : 0;
    $data   = is_string($answer) ? json_decode($answer, true) : null;
    if ($status !== 200 || !is_array($data)) {
        $said = is_array($data) ? ($data['error']['message'] ?? $data['error_description'] ?? '') : '';
        fail("Google answered HTTP $status" . (is_string($said) && $said !== '' ? ": " . shown($said) : ''));
    }
    return $data;
}

/** A one-hour access token for that scope, from the service account's key (the signed-JWT flow). */
function accessToken(string $scope) : string
{
    // TLI_GOOGLE_KEY_FILE: for a box that keeps the key elsewhere
    $file = getenv('TLI_GOOGLE_KEY_FILE') ?: PROJECT_DIR . 'var/google-analytics-credentials.json';
    if (!is_readable($file)) {
        fail("no service-account key at var/google-analytics-credentials.json: docs/stats.md says how to get one");
    }
    $key = json_decode(file_get_contents($file), true);
    if (($key['type'] ?? '') !== 'service_account' || empty($key['client_email']) || empty($key['private_key'])) {
        fail("the key file isn't a Google service-account key");
    }
    if (($key['token_uri'] ?? '') !== TOKEN_URL) {
        fail("the key file names a token address that isn't Google's: not signing anything for it");
    }
    $encode  = static fn(string $bytes) : string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    $now     = time();
    $claims  = ['iss' => $key['client_email'], 'scope' => $scope, 'aud' => TOKEN_URL, 'iat' => $now, 'exp' => $now + 600];
    $signing = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $encode(json_encode($claims));
    if (!@openssl_sign($signing, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
        fail("the private key in the key file can't sign: is the file complete?");
    }
    $answer = request(TOKEN_URL, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $signing . '.' . $encode($signature),
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    return is_string($answer['access_token'] ?? null) ? $answer['access_token'] : fail("Google gave no access token");
}

function api(string $url, string $scope, array $body) : array
{
    return request($url, json_encode($body), ['Content-Type: application/json', 'Authorization: Bearer ' . accessToken($scope)]);
}

/** [name, operator, value] of each --filter. */
function filters(array $given) : array
{
    return array_map(static function (string $filter) : array {
        if (!preg_match('/^(\w+)(==|=@|=\^|=\$|=~)(.+)$/s', $filter, $parts)) {
            fail("«" . shown($filter) . "» isn't a filter: NAME==value, NAME=@text, NAME=^text, NAME=\$text or NAME=~regex", 2);
        }
        return [$parts[1], $parts[2], $parts[3]];
    }, $given);
}

function printTable(array $header, array $rows, string $footer = '') : void
{
    echo implode("\t", $header), "\n";
    foreach ($rows as $row) {
        echo implode("\t", array_map(static fn($cell) : string => shown((string) $cell), $row)), "\n";
    }
    echo count($rows), " row(s)", $footer, "\n";
}

function ga4(string $method, array $options, array $defaults) : void
{
    $metrics    = names($options['metrics'] ?? $defaults['metrics']);
    $dimensions = names($options['dimensions'] ?? $defaults['dimensions']);
    $body       = ['metrics' => array_map(static fn($name) => ['name' => $name], $metrics), 'limit' => (int) ($options['limit'] ?? 25)];
    if ($dimensions) {
        $body['dimensions'] = array_map(static fn($name) => ['name' => $name], $dimensions);
    }
    if ($method === 'runReport') {
        $body['dateRanges'] = [['startDate' => $options['from'] ?? '28daysAgo', 'endDate' => $options['to'] ?? 'yesterday']];
    }
    $matchTypes  = ['==' => 'EXACT', '=@' => 'CONTAINS', '=^' => 'BEGINS_WITH', '=$' => 'ENDS_WITH', '=~' => 'PARTIAL_REGEXP'];
    $expressions = array_map(static fn(array $filter) => ['filter' => [
        'fieldName' => $filter[0], 'stringFilter' => ['matchType' => $matchTypes[$filter[1]], 'value' => $filter[2]],
    ]], filters($options['filter']));
    if ($expressions) {
        $body['dimensionFilter'] = count($expressions) === 1 ? $expressions[0] : ['andGroup' => ['expressions' => $expressions]];
    }
    // by day when the report is by day, otherwise the biggest first
    $order = $options['order'] ?? (in_array('date', $dimensions, true) ? 'date' : '-' . $metrics[0]);
    $field = ltrim($order, '-');
    $body['orderBys'] = [(in_array($field, $metrics, true) ? ['metric' => ['metricName' => $field]] : ['dimension' => ['dimensionName' => $field]])
        + ['desc' => str_starts_with($order, '-')]];

    $data = api(GA4_URL . propertyId() . ':' . $method, SCOPE_GA4, $body);
    $rows = array_map(static fn(array $row) => array_merge(
        array_column($row['dimensionValues'] ?? [], 'value'), array_column($row['metricValues'] ?? [], 'value')), $data['rows'] ?? []);
    $totals = array_fill(0, count($metrics), 0);
    foreach ($rows as $row) {
        foreach (array_slice($row, count($dimensions)) as $index => $value) {
            $totals[$index] += (float) $value;
        }
    }
    $sums = implode(', ', array_map(static fn($name, $sum) => "$name " . round($sum, 2), $metrics, $totals));
    printTable(array_merge($dimensions, $metrics), $rows, " of " . ($data['rowCount'] ?? count($rows)) . "; sum of these rows: $sums");
}

function day(string $when) : string
{
    $date = match (true) {
        (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $when) => $when,
        $when === 'today', $when === 'yesterday'            => date('Y-m-d', strtotime($when)),
        (bool) preg_match('/^(\d+)daysAgo$/', $when, $n)   => date('Y-m-d', strtotime("-{$n[1]} days")),
        default                                             => null,
    };
    return $date ?? fail("«" . shown($when) . "» isn't a date: YYYY-MM-DD, today, yesterday or NdaysAgo", 2);
}

function gsc(array $options, array $defaults) : void
{
    $dimensions = names($options['dimensions'] ?? $defaults['dimensions']);
    $body = ['startDate' => day($options['from'] ?? '30daysAgo'), 'endDate' => day($options['to'] ?? '3daysAgo'),
             'type' => $options['type'] ?? 'web', 'rowLimit' => (int) ($options['limit'] ?? 25)];
    if ($dimensions) {
        $body['dimensions'] = $dimensions;
    }
    $operators = ['==' => 'equals', '=@' => 'contains', '=~' => 'includingRegex'];
    foreach (filters($options['filter']) as [$name, $operator, $value]) {
        // Search Console has no "starts with" or "ends with": said as a regular expression
        [$operator, $value] = match ($operator) {
            '=^'    => ['=~', '^' . preg_quote($value)],
            '=$'    => ['=~', preg_quote($value) . '$'],
            default => [$operator, $value],
        };
        $body['dimensionFilterGroups'][0]['filters'][] = ['dimension' => $name, 'operator' => $operators[$operator], 'expression' => $value];
    }
    $data = api(GSC_URL . rawurlencode(GSC_SITE) . '/searchAnalytics/query', SCOPE_GSC, $body);
    // Discover and Google News results have no position
    $rows = array_map(static fn(array $row) => array_merge($row['keys'] ?? [], [
        $row['clicks'] ?? 0, $row['impressions'] ?? 0, round(($row['ctr'] ?? 0) * 100, 1) . '%',
        isset($row['position']) ? round($row['position'], 1) : '-',
    ]), $data['rows'] ?? []);
    printTable(array_merge($dimensions, ['clicks', 'impressions', 'ctr', 'position']), $rows,
        " from {$body['startDate']} to {$body['endDate']}, {$body['type']} results");
}

$arguments = array_slice($argv, 1);
$command   = array_shift($arguments) ?? '';
switch ($command) {
    case 'ga4':
        ga4('runReport', options($arguments), ['metrics' => 'screenPageViews,activeUsers', 'dimensions' => 'pagePath']);
        break;
    case 'realtime':
        ga4('runRealtimeReport', options($arguments), ['metrics' => 'activeUsers', 'dimensions' => 'unifiedScreenName']);
        break;
    case 'gsc':
        gsc(options($arguments), ['dimensions' => 'query']);
        break;
    case 'article':
        $id = array_shift($arguments) ?? '';
        if (!ctype_digit($id)) {
            fail("article takes the article's numeric ID", 2);
        }
        $days = (int) (options($arguments)['days'] ?? 28);
        // an article's address ends with -ID, whatever its tag and its title are
        echo "Article $id, views by day (Google Analytics, last $days days):\n";
        ga4('runReport', ['filter' => ["pagePath=~-$id/?\$"], 'from' => "{$days}daysAgo", 'to' => 'today', 'limit' => (string) ($days + 1)],
            ['metrics' => 'screenPageViews,activeUsers', 'dimensions' => 'date']);
        echo "\nIn Google Search, the last $days days Search Console has:\n";
        gsc(['filter' => ["page=~-$id/?\$"], 'from' => ($days + 3) . 'daysAgo'], ['dimensions' => '']);
        echo "\nThe queries that led to it:\n";
        gsc(['filter' => ["page=~-$id/?\$"], 'from' => ($days + 3) . 'daysAgo', 'limit' => '15'], ['dimensions' => 'query']);
        break;
    default:
        fail("which report: ga4, realtime, gsc or article? The top of this file lists the options", 2);
}
