<?php
namespace App\Service\Cms;

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
        $title      = $service->getTitle();
        $builtValue = $this->slugify($title);
        return $builtValue;
    }


    protected function buildSlugDashIdString(BaseCmsService $service) : string
    {
        $builtValue = $this->buildSlug($service) . "-" . $service->getId();
        return $builtValue;
    }


    public function slugify(?string $text) : string
    {
        $slug   = strip_tags($text);
        $slug   = html_entity_decode($slug, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $slug   = $this->stopWords->removeFromSting($slug);
        $slug   = trim($slug);
        $slug   = $this->slugger->slug($slug);
        $slug   = mb_strtolower($slug);

        $arrReplaceMap = [
            'turbolab-it'   => 'turbolab.it'
        ];

        $slug = str_ireplace(array_keys($arrReplaceMap), $arrReplaceMap, $slug);

        return $slug;
    }


    public function isInternalUrl(string $urlCandidate) : bool
    {
        if (
            stripos($urlCandidate, 'http://') !== 0 &&
            stripos($urlCandidate, 'https://') !== 0
        ) {
            return true;
        }

        $arrUrlComponents = parse_url($urlCandidate);
        $urlDomain = $arrUrlComponents["host"] ?? null;

        $isInternal = !empty($urlDomain) && in_array($urlDomain, static::INTERNAL_DOMAINS);
        return $isInternal;
    }


    protected function removeDomainFromUrl(string $url) : ?string
    {
        $arrUrlComponents = parse_url($url);
        return $arrUrlComponents["path"] ?? null;
    }
}
