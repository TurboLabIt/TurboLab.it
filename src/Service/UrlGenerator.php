<?php
namespace App\Service;

use App\Service\Cms\BaseCmsService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;


class UrlGenerator
{
    const DEFAULT_TAG_SLUG_DASH_ID = "pc-642";

    protected AsciiSlugger $slugger;


    public function __construct(protected StopWords $stopWords, protected UrlGeneratorInterface $symfonyUrlGenerator)
    {
        $this->slugger = new AsciiSlugger();
    }


    protected function buildSlugDashIdString(BaseCmsService $service) : string
    {
        $title      = $service->getTitle();
        $builtValue = $this->slugify($title) . "-" . $service->getId();
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
}
