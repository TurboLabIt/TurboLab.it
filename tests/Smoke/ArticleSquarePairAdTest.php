<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;


/**
 * Two 336x280 squares (templates/ads/clickio/square-pair.html.twig, Clickio's "articoli" unit) close the article: after
 * the text and the share buttons, right before the tags. Not above the text, by choice: there they would push it off
 * the first screen. Both run the same unit, which works because Clickio's loader gives an ad to every <script> whose
 * text carries the unit's code, so each one must keep it literally.
 *
 * Readers only, like every ad: whoever can edit the article gets none (hasAds); see ArticleSidebarAdTest for why that
 * branch isn't exercised here.
 */
class ArticleSquarePairAdTest extends BaseT
{
    // the registration Clickio's loader looks for in each script's text
    private const string SQUARE_UNIT_CODE = "['b']['_601521']";


    public function testTheSquarePairClosesTheArticleText() : void
    {
        $url  = static::getArticle()->getUrl();
        $pair = $this->fetchDomNode($url, '.tli-ad-pair');
        $this->assertCount(1, $pair, "The pair of squares is missing on $url");

        $blocks = $pair->filter('script.__lxGc__');
        $this->assertCount(2, $blocks, "The pair on $url must carry two Clickio ad blocks");
        foreach($blocks as $block) {
            $this->assertStringContainsString(self::SQUARE_UNIT_CODE, $block->textContent,
                "Each square on $url must carry the unit's code in its own script, or Clickio gives it no ad");
        }

        $this->assertSame('tli-article-tags', $pair->nextAll()->first()->attr('id'), "The pair must sit right before the tags on $url");
        $this->assertStringContainsString('tli-handstop', (string)$pair->previousAll()->first()->attr('class'),
            "The pair on $url needs its ad-blocker warning, right before it");
        $this->assertCount(1, $pair->previousAll()->filter('#tli-article-body'), "The pair must come after the text on $url, not above it");
    }
}
