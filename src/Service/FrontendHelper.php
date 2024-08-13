<?php
namespace App\Service;

use App\Service\Cms\Tag;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\TagCollection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class FrontendHelper
{
    protected ArticleCollection $guidesForAuthors;
    protected TagCollection $categories;


    public function __construct(protected UrlGeneratorInterface $urlGenerator, protected Factory $factory) {}


    //<editor-fold defaultstate="collapsed" desc="*** 🔗 Newsletter, Feed, Social links ***">
    public function getFollowUsLinks() : array
        { return array_merge( $this->getOwnFollowUsPages(), $this->getSocialMediaPages() ); }


    public function getOwnFollowUsPages() : array
    {
        return [
            $this->buildLink(
                "Forum e newsletter", '/forum/ucp.php?mode=register', false,
                '/images/social-icons/email.svg', 'fa-solid fa-user-group'
            ),
            $this->buildLink(
                "Feed RSS", $this->urlGenerator->generate('app_feed'), false,
                '/images/social-icons/rss.svg', 'fa fa-square-rss'
            ),
        ];
    }


    public function getSocialMediaPages() : array
    {
        return [
            $this->buildLink("YouTube", 'https://www.youtube.com/c/turbolabit?sub_confirmation=1'),
            $this->buildLink("Telegram", 'https://t.me/turbolab'),
            $this->buildLink("Facebook", 'https://www.facebook.com/TurboLab.it'),
            $this->buildLink(
                "X (Twitter)", 'https://twitter.com/TurboLabIt', true,
                '/images/social-icons/x-twitter.svg', 'fab fa-x-twitter'
            ),
            $this->buildLink("GitHub", 'https://github.com/TurboLabIt/'),
            $this->buildLink("LinkedIn", 'https://linkedin.com/company/turbolabit')
        ];
    }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 🗂️ Nav menu ***">
    public function getNavCategories() : array
        { return array_merge( $this->getNavTopCategories(), $this->getNavOtherCategories() ); }


    public function getNavTopCategories() : array
    {
        $arrNavItems = $this->buildNavCategories(TagCollection::TOP_CATEGORIES);

        $arrNavItems["news"] =
            $this->buildLink(
                "News", $this->urlGenerator->generate('app_news'), false,
                null, "fa-solid fa-newspaper", "news"
            );

        return $arrNavItems;
    }


    public function getNavOtherCategories() : array
        { return $this->buildNavCategories(TagCollection::NAV_OTHER_CATEGORIES); }


    protected function buildNavCategories(array $arrCategoryIds) : array
    {
        if( empty($this->categories) ) {
            $this->categories = $this->factory->createTagCollection()->loadCategories();
        }

        $arrTopCategories = $this->categories->getFilteredData( fn(Tag $tag) => in_array($tag->getId(), $arrCategoryIds) );

        $arrNavItems = [];
        foreach($arrTopCategories as $tag) {

            $id = (string)$tag->getId();
            $arrNavItems[$id] =
                $this->buildLink(
                    $tag->getTitleFormatted(), $tag->getUrl(), false, null,
                    $tag->getFontAwesomeIcon(), $tag->getSlug()
                );
        }

        return $arrNavItems;
    }
    //</editor-fold>


    protected function buildLink(
        string $name, string $url, bool $blank = true,
        ?string $iconFileName = null, null|string|array $faIcon = null,
        ?string $activeMenu = null
    ) : array
    {
        if( empty($faIcon) ) {
            $faIcon = 'fab fa-' . mb_strtolower($name);
        }

        if( !is_array($faIcon) ) {
            $faIcon = [$faIcon];
        }

        return [
            'name'          => $name,
            'icon'          => $iconFileName ?? ( '/images/social-icons/' . mb_strtolower($name) . '.svg' ),
            "fa"            => $faIcon,
            "url"           => $url,
            "blank"         => $blank,
            "activeMenu"    => $activeMenu,
        ];
    }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** Articles ***">
    public function getGuidesForAuthors() : ArticleCollection
    {
        if( empty($this->guidesForAuthors) ) {
            $this->guidesForAuthors = $this->factory->createArticleCollection()->loadGuidesForAuthors();
        }

        return $this->guidesForAuthors;
    }
    //</editor-fold>
}