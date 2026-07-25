<?php
namespace App\Tests\Security;

use App\Controller\ArticleController;
use App\Controller\AuthorContoller;
use App\Controller\TagController;
use App\Tests\BaseT;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\ItemInterface;


/**
 * Anti-regression guard for docs/security-audit.md finding #31
 * ("Collisione di chiave nella cache HTML condivisa: username vs tag") — now RESOLVED.
 *
 * AuthorContoller and TagController share the one autowired cache pool. Their keys used to be built
 * straight from URL segments — "$usernameClean/$page" and "$tagSlugDashId/$page" — so an author
 * named "windows-10" and the "windows-10" tag (ID_WINDOWS=10) produced the SAME key "windows-10/1"
 * and poisoned each other's cached HTML. (Symfony's reserved-char check that would reject the "/"
 * runs inside an assert(), compiled out in prod with zend.assertions=-1, so the raw keys passed.)
 *
 * Fix: BaseController::buildViewCacheKey() namespaces the key per controller (author. / tag. /
 * article.) and drops the reserved "/". These tests invoke the real key-builders and prove the
 * collision is gone; the negative control reproduces the original poisoning so the assertions can't
 * pass vacuously.
 *
 * NB: controllers bypass the cache entirely when isCachable() is false (dev/test), so the collision
 * lives purely in the key strings — hence this unit-level guard rather than an HTTP test.
 */
class ViewCacheKeyCollisionTest extends BaseT
{
    /** Invoke a controller's protected cache-key builder without booting the controller. */
    private function invokeKeyBuilder(string $class, string $method, array $args) : string
    {
        $instance = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        return (new ReflectionMethod($class, $method))->invokeArgs($instance, $args);
    }


    public function testAuthorAndTagPagesGetDistinctCacheKeysForSameUrlSegment() : void
    {
        // "windows-10" is both a valid username and the slug-id of the Windows tag.
        $authorKey = $this->invokeKeyBuilder(AuthorContoller::class, 'getPageCacheKey', ['windows-10', 1]);
        $tagKey    = $this->invokeKeyBuilder(TagController::class,    'getPageCacheKey', ['windows-10', 1]);

        $this->assertNotSame($authorKey, $tagKey,
            'Author and tag pages must not share a cache key (#31).');

        // sharing one pool, distinct keys must mean no cross-poisoning
        $pool = new TagAwareAdapter(new ArrayAdapter());
        $this->assertSame('AUTHOR-HTML', $pool->get($authorKey, fn(ItemInterface $i) => 'AUTHOR-HTML'));
        $this->assertSame('TAG-HTML',    $pool->get($tagKey,    fn(ItemInterface $i) => 'TAG-HTML'),
            'The tag page served the author-page HTML — cache poisoning (#31).');
    }


    public function testViewCacheKeysContainNoReservedCharacters() : void
    {
        // {}()/\@: are reserved by Symfony's CacheItem::validateKey(); the old "/" only slipped
        // through because that check runs inside an assert().
        $keys = [
            $this->invokeKeyBuilder(AuthorContoller::class,  'getPageCacheKey',   ['windows-10', 2]),
            $this->invokeKeyBuilder(TagController::class,     'getPageCacheKey',   ['windows-10', 2]),
            $this->invokeKeyBuilder(ArticleController::class, 'buildViewCacheKey', ['article', 'windows-10', 'guida-123']),
        ];

        foreach ($keys as $key) {
            $this->assertSame(0, preg_match('#[{}()/\\\\@:]#', $key), "Reserved char in cache key: $key");
        }
    }


    public function testUnprefixedKeysWouldCollide() : void
    {
        // Negative control: the pre-fix pattern "$segment/$page" collides across controllers, which
        // is exactly what made the poisoning possible — proving the test above is not vacuous.
        $pool = new TagAwareAdapter(new ArrayAdapter());
        $authorOldKey = 'windows-10/1';   // old AuthorContoller pattern
        $tagOldKey    = 'windows-10/1';   // old TagController pattern

        $this->assertSame($authorOldKey, $tagOldKey);
        $pool->get($authorOldKey, fn(ItemInterface $i) => 'AUTHOR-HTML');
        $this->assertSame('AUTHOR-HTML', $pool->get($tagOldKey, fn(ItemInterface $i) => 'TAG-HTML'),
            'Sanity: the old unprefixed keys must collide (poisoning).');
    }
}
