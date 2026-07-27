<?php
namespace App\Tests\Security;

use App\Tests\BaseT;


/**
 * Anti-regression guard for docs/security-audit.md finding #38 — now RESOLVED.
 *
 * The newsletter subscribe/unsubscribe capability pages print the subscriber's email and a fresh
 * opposite-action token, yet they extended base.html.twig, which loads gtag.js and the Clickio ad
 * tags. So Google Analytics received the full URL (token included) as page_location and the
 * first-party ad scripts could read the rendered email + the re-subscribe href.
 *
 * Fix (per Zane): pass hasAds=false and hasAnalytics=false on those pages, so base.html.twig
 * suppresses both the ad tags and gtag. This test renders each capability page and asserts neither
 * tracker is present — with the home page as a positive control (a normal page DOES carry them).
 */
class NewsletterCapabilityPageTrackersTest extends BaseT
{
    private const string GA_MARKER  = 'googletagmanager.com/gtag';
    private const string ADS_MARKER = 'clickio';


    public function testCapabilityPagesServeNoAdsOrAnalytics() : void
    {
        // positive control: a normal page (home) DOES carry both trackers, so the "not present"
        // assertions below are meaningful rather than vacuous
        $home = $this->fetchHtml('/');
        $this->assertStringContainsString(self::GA_MARKER, $home, 'sanity: the home page should carry gtag');
        $this->assertStringContainsString(self::ADS_MARKER, $home, 'sanity: the home page should carry the ad tag');

        $user = static::getUser();

        // unsubscribe capability page — put the user in the "already unsubscribed" error branch so
        // the page renders without a net state change
        $user->unsubscribeFromNewsletter();
        $this->getEntityManager()->flush();
        $this->assertPageHasNoTrackers($user->getNewsletterUnsubscribeUrl(), 'unsubscribe page (#38)');

        // subscribe capability page — "already subscribed" error branch
        $user->subscribeToNewsletter();
        $this->getEntityManager()->flush();
        $this->assertPageHasNoTrackers($user->getNewsletterSubscribeUrl(), 'subscribe page (#38)');

        // restore: leave the test user unsubscribed
        $user->unsubscribeFromNewsletter();
        $this->getEntityManager()->flush();
    }


    private function assertPageHasNoTrackers(string $url, string $where) : void
    {
        $this->browse($url);
        $html = mb_strtolower( (string)static::$client->getResponse()->getContent() );

        $this->assertNotEmpty($html, "$where should still render a page.");
        $this->assertStringNotContainsString(self::GA_MARKER, $html, "$where must not load gtag/analytics.");
        $this->assertStringNotContainsString(self::ADS_MARKER, $html, "$where must not load the ad tag.");
    }
}
