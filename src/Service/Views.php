<?php
namespace App\Service;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Views
{
    const array VIEWS = [
        "bozze"         => [
            "title" => "Articoli in lavorazione (bozze)",
            "fx"    => 'loadDrafts'
        ],
        "finiti"        => [
            "title" => "Articoli finiti, in attesa di pubblicazione",
            "fx"    => 'loadLatestReadyForReview'
        ],
        "visitati"      => [
            "title" => "Gli articoli più visitati",
            "fx"    => 'loadTopViews'
        ],
        "commentati"    => [
            "title" => "Gli articoli più commentati",
            "fx"    => 'loadTopTopComments'
        ],
        "annuali"    => [
            "title" => "Articoli annuali da aggiornare",
            "fx"    => 'loadPeriodicUpdateList'
        ]
    ];


    public function __construct(protected Factory $factory, protected UrlGeneratorInterface $urlGenerator) {}


    public function get(array|string $slug) : array
    {
        $arrSlug    = is_array($slug) ? $slug : [$slug];
        $arrViews   = [];
        foreach($arrSlug as $oneSlug) {

            $arrViewRequested = static::VIEWS[$oneSlug] ?? null;
            if( empty($arrViewRequested) ) {
                throw new NotFoundHttpException();
            }

            $arrViews[$oneSlug] = $arrViewRequested;
        }

        $this->addUrls($arrViews);

        return is_array($slug) ? $arrViews : reset($arrViews);
    }


    public function getAll() : array
    {
        $arrViews = static::VIEWS;
        return $this->addUrls($arrViews);
    }


    protected function addUrls(array &$arrViews) : array
    {
        foreach($arrViews as $slug => &$view) {

            $view["url"] =
                $this->urlGenerator->generate(
                    'app_views_multi', ['slug' => $slug],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
        }

        return $arrViews;
    }
}
