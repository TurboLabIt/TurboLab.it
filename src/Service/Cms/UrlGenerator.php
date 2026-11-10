<?php
namespace App\Service\Cms;

use App\Service\HtmlProcessorBase;
use App\Service\StopWords;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;


class UrlGenerator
{
    const array INTERNAL_DOMAINS = ['localhost', 'dev0.turbolab.it', 'next.turbolab.it', 'turbolab.it'];
    const string DEFAULT_TAG_SLUG_DASH_ID = "pc-642";

    protected AsciiSlugger $slugger;


    public function __construct(protected StopWords $stopWords, protected UrlGeneratorInterface $symfonyUrlGenerator)
    {
        $this->slugger = new AsciiSlugger();
    }


    public function buildSlug(BaseCmsService $service) : string
    {
        $title = $service->getTitle();
        return $this->slugify($title);
    }


    protected function buildSlugDashIdString(BaseCmsService $service) : string
    {
        return $this->buildSlug($service) . "-" . $service->getId();
    }


    public function slugify(?string $text) : string
    {
        $slug   = strip_tags($text);
        $slug   = HtmlProcessorBase::decode($slug);

        // special handling
        $arrSpecials = [
          'o&o' => 'oo'
        ];

        $slug   = str_ireplace( array_keys($arrSpecials), $arrSpecials, $slug );

        $slug   = $this->stopWords->removeFromSting($slug);
        $slug   = $this->slugger->slug($slug);
        $slug   = mb_strtolower($slug);

        // replace two or more consecutive dashes with one
        $slug   = preg_replace('/-{2,}/', '-', $slug);

        //
        $arrReplaceMap = [
            'turbolab-it' => 'turbolab.it'
        ];

        return str_ireplace(array_keys($arrReplaceMap), $arrReplaceMap, $slug);
    }


    public function isInternalUrl(string $urlCandidate) : bool
    {
        $arrUrlComponents = parse_url($urlCandidate);
        $urlDomain = $arrUrlComponents["host"] ?? null;

        return empty($urlDomain) || in_array($urlDomain, static::INTERNAL_DOMAINS);
    }


    /**
     * Adds the utm_* parameters Google Analytics reads to tell where a visit comes from. Without
     * them, a click from an email client or a messaging app reaches the site with no referrer and
     * is counted as "direct". A URL already tagged is returned as it is.
     *
     * 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/seo-news.md
     */
    public static function addCampaignParams(string $url, string $source, string $medium, string $campaign) : string
    {
        $fragment = '';
        $fragmentPos = strpos($url, '#');
        if( $fragmentPos !== false ) {

            $fragment   = substr($url, $fragmentPos);
            $url        = substr($url, 0, $fragmentPos);
        }

        if( preg_match('/[?&]utm_source=/', $url) ) {
            return $url . $fragment;
        }

        $query = http_build_query(['utm_source' => $source, 'utm_medium' => $medium, 'utm_campaign' => $campaign]);

        return $url . ( str_contains($url, '?') ? '&' : '?' ) . $query . $fragment;
    }


    protected function removeDomainFromUrl(string $url) : ?string
    {
        $arrUrlComponents = parse_url($url);
        return $arrUrlComponents["path"] ?? null;
    }
}
