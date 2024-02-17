<?php
namespace App\Service\Cms;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class Paginator
{
    const ITEMS_PER_PAGE = 25;

    // page numbers
    protected int $currentPageNum   = 0;
    protected int $totalElementsNum = 0;

    // prev and next page
    protected array $arrPreviousPage= [];
    protected array $arrCurrentPage = [];
    protected array $arrNextPage    = [];


    public function __construct(protected UrlGeneratorInterface $urlGenerator)
    {}


    public function build(string $routeName, array $arrRouteParam = [], $routeWithNumName = null, $arrRouteWithNumParam = null): self
    {
        $routeWithNumName = $routeWithNumName ?? $routeName;
        $arrRouteWithNumParam = $arrRouteWithNumParam ?? $arrRouteParam;

        if( $this->currentPageNum == 1 ) {

            $prevPageUrl = null;
            $currPageUrl = $this->urlGenerator->generate($routeName, $arrRouteParam, UrlGeneratorInterface::ABSOLUTE_URL);

        } elseif( $this->currentPageNum == 2 ) {

            $prevPageUrl = $this->urlGenerator->generate($routeName, $arrRouteParam, true);
            $currPageUrl = $this->urlGenerator->generate($routeWithNumName, array_merge($arrRouteWithNumParam, ["page" => $this->currentPageNum]), UrlGeneratorInterface::ABSOLUTE_URL);

        } else {

            $prevPageUrl = $this->urlGenerator->generate($routeWithNumName, array_merge($arrRouteWithNumParam, ["page" => $this->currentPageNum - 1]), UrlGeneratorInterface::ABSOLUTE_URL);
            $currPageUrl = $this->urlGenerator->generate($routeWithNumName, array_merge($arrRouteWithNumParam, ["page" => $this->currentPageNum]), UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $this->arrPreviousPage  = [ "url" => $prevPageUrl ];
        $this->arrCurrentPage   = [ "url" => $currPageUrl ];
        $this->arrNextPage      = [ "url" => $this->urlGenerator->generate($routeWithNumName, array_merge($arrRouteWithNumParam, ["page" => $this->currentPageNum + 1]), UrlGeneratorInterface::ABSOLUTE_URL) ];

        return $this;
    }


    public function setCurrentPageNum(?int $page): self
    {
        $this->currentPageNum = $page ?: 1;
        return $this;
    }


    public function getCurrentPageNum(): int
    {
        return $this->currentPageNum;
    }


    public function setTotalElementsNum(int $num): self
    {
        $this->totalElementsNum = $num;
        return $this;
    }


    public function isPageOutOfRange(): int|bool
    {
        if( $this->currentPageNum > $this->getMaxPageNum() ) {
            return $this->getMaxPageNum();
        }

        return false;
    }


    public function getMaxPageNum(): int
    {
        $maxPage = $this->totalElementsNum / static::ITEMS_PER_PAGE;
        $maxPage = $maxPage ?: 1;
        return (int)ceil($maxPage);
    }


    public function getPreviousPage(): ?array
    {
        if( $this->currentPageNum > 1 ) {
            return $this->arrPreviousPage;
        }

        return null;
    }


    public function getCurrentPage(): ?array
    {
        return $this->arrCurrentPage;
    }


    public function getNextPage(): ?array
    {
        if( $this->currentPageNum < $this->getMaxPageNum() ) {
            return $this->arrNextPage;
        }

        return null;
    }
}
