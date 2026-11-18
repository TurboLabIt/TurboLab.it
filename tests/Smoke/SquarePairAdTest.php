<?php
namespace App\Tests\Smoke;

use App\Service\Cms\Article;
use App\Service\Cms\Tag;
use App\Service\User;
use App\Tests\BaseT;
use Symfony\Component\DomCrawler\Crawler;


/**
 * Two 336x280 squares (templates/ads/clickio/square-pair.html.twig, Clickio's "articoli" unit), in three places:
 *
 * - at the end of the article: after the text and the share buttons, right before the tags. Not above the text, by
 *   choice: there they would push it off the first screen;
 * - on the homepage, after all four cards of the top slider and before the mosaic: below the first screen at every
 *   common size, while between the two rows of cards they would reach it on a large display;
 * - in every listing (archive/index.html.twig: tags, news, the homepage's later pages, authors, views), twice at most:
 *   after the first two rows (the two big cards, then the first row of three), by choice, even if the first one reaches
 *   the first screen at 1920x1080 and on a portrait tablet. Only with two cards or more after it: on a phone, a listing
 *   of two cards closed by a pair is 37% ads with the bottom sticky, one of three 30%, at the limit of the Better Ads
 *   Standards.
 *
 * Both squares run the same unit, which works because Clickio's loader gives an ad to every <script> whose text
 * carries the unit's code, so each one must keep it literally.
 *
 * In an article, readers only, like every ad: whoever can edit it gets none (hasAds); see ArticleSidebarAdTest for why
 * that branch isn't exercised here. Nor does an article with its ads off (show_ads): see ArticleShowAdsTest. The
 * homepage and the listings are the same for everyone.
 */
class SquarePairAdTest extends BaseT
{
    // the registration Clickio's loader looks for in each script's text
    private const string SQUARE_UNIT_CODE = "['b']['_601521']";

    // in a listing, each pair follows this many cards
    private const array LISTING_PAIRS_AFTER_CARDS = [2, 5];


    public function testTheSquarePairClosesTheArticleText() : void
    {
        $url  = static::getArticleWithAds()->getUrl();
        $pair = $this->fetchDomNode($url, '.tli-ad-pair');
        $this->assertIsTheSquarePair($pair, $url);

        $this->assertSame('tli-article-tags', $pair->nextAll()->first()->attr('id'), "The pair must sit right before the tags on $url");
        $this->assertCount(1, $pair->previousAll()->filter('#tli-article-body'), "The pair must come after the text on $url, not above it");
    }


    public function testTheSquarePairFollowsTheHomeTopSlider() : void
    {
        $url  = $this->generateUrl();
        $page = $this->fetchDomNode($url);
        $pair = $page->filter('.tli-ad-pair');
        $this->assertIsTheSquarePair($pair, $url);

        $sliderCards = $page->filter('.trending-news-area .trending-news-item')->count();
        $this->assertGreaterThan(0, $sliderCards, "The top slider is missing on $url");
        $this->assertSame($sliderCards, $pair->previousAll()->filter('.row')->first()->filter('.trending-news-item')->count(),
            "The pair on $url must follow all the top slider's cards, not sit between them");
        $this->assertGreaterThan(0, $pair->nextAll()->first()->filter('.trending-news-post-items')->count(),
            "The pair on $url must sit right before the mosaic");
    }


    public function testTheSquarePairsBreakTheListings() : void
    {
        $arrUrls = [
            static::getTag(Tag::ID_WINDOWS)->getUrl(),
            $this->generateUrl('app_news'),
            $this->generateUrl('app_home_paginated', ['page' => 2]),
            $this->generateUrl('app_views_multi', ['slug' => 'visitati']),
            static::getUser(User::ID_DEFAULT_ADMIN)->getUrl(),
        ];

        foreach($arrUrls as $url) {

            $pairs = $this->fetchDomNode($url, '.about-post-items .tli-ad-pair');
            $this->assertCount(count(self::LISTING_PAIRS_AFTER_CARDS), $pairs, "Wrong number of pairs of squares on $url");

            foreach(self::LISTING_PAIRS_AFTER_CARDS as $i => $cardsBefore) {

                $pair = $pairs->eq($i);
                $this->assertIsTheSquarePair($pair, $url);

                $row = $pair->closest('.col-12') ?? $this->fail("Each pair on $url must take a whole row of the grid");
                $this->assertSame($cardsBefore, $row->previousAll()->filter('.trending-news-item')->count(),
                    "Pair #" . ($i + 1) . " on $url must follow card $cardsBefore");
            }
        }
    }


    public function testShortListingsGetNoSquarePair() : void
    {
        // cards on the page => pairs: each one needs two more cards after it
        foreach([2 => 0, 3 => 0, 4 => 1, 6 => 1, 7 => 2] as $cardsNum => $pairsNum) {

            [$url, $grid] = $this->fetchTagGridWithCards($cardsNum);
            $this->assertCount($pairsNum, $grid->filter('.tli-ad-pair'), "Wrong number of pairs of squares on $url, $cardsNum cards");
        }
    }


    // a tag page whose grid has exactly that many cards
    private function fetchTagGridWithCards(int $cardsNum) : array
    {
        $arrTagIds =
            static::getEntityManager()->getConnection()->fetchFirstColumn("
                SELECT article_tag.tag_id FROM article_tag
                INNER JOIN article ON article.id = article_tag.article_id
                INNER JOIN tag ON tag.id = article_tag.tag_id
                WHERE publishing_status = :published AND published_at <= NOW() AND tag.replacement_id IS NULL
                GROUP BY article_tag.tag_id HAVING COUNT(1) = :cardsNum
                ORDER BY article_tag.tag_id LIMIT 10
            ", ['published' => Article::PUBLISHING_STATUS_PUBLISHED, 'cardsNum' => $cardsNum]);

        foreach($arrTagIds as $tagId) {

            $url  = static::getTag((int)$tagId)->getUrl();
            $grid = $this->fetchDomNode($url, '.about-post-items');
            if( $grid->filter('.trending-news-item')->count() == $cardsNum ) {
                return [$url, $grid];
            }
        }

        static::fail("No tag page with exactly $cardsNum cards");
    }


    private function assertIsTheSquarePair(Crawler $pair, string $url) : void
    {
        $this->assertCount(1, $pair, "The pair of squares is missing on $url");

        $blocks = $pair->filter('script.__lxGc__');
        $this->assertCount(2, $blocks, "The pair on $url must carry two Clickio ad blocks");
        foreach($blocks as $block) {
            $this->assertStringContainsString(self::SQUARE_UNIT_CODE, $block->textContent,
                "Each square on $url must carry the unit's code in its own script, or Clickio gives it no ad");
        }

        $this->assertStringContainsString('tli-handstop', (string)$pair->previousAll()->first()->attr('class'),
            "The pair on $url needs its ad-blocker warning, right before it");
    }
}
