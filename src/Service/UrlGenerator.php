<?php
namespace App\Service;

use App\Service\Cms\BaseCmsService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;


class UrlGenerator
{
    const INTERNAL_DOMAINS = ['localhost', 'dev0.turbolab.it', 'next.turbolab.it', 'turbolab.it'];
    const DEFAULT_TAG_SLUG_DASH_ID = "pc-642";

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
        $slug   = $this->stopWords->process($slug);
        $slug   = trim($slug);
        $slug   = $this->slugger->slug($slug);
        $slug   = mb_strtolower($slug);
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
