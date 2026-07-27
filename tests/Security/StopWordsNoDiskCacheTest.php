<?php
namespace App\Tests\Security;

use App\Service\StopWords;
use App\Tests\BaseT;
use TurboLabIt\BaseCommand\Service\ProjectDir;


/**
 * Anti-regression guard for docs/security-audit.md finding #39
 * ("Crescita illimitata della cache su disco in StopWords") — now RESOLVED.
 *
 * removeFromSting() used to cache its result on disk (PhpArrayAdapter ➡ FilesystemAdapter, lifetime 0)
 * keyed by the RAW search term coming from ArticleCollection::loadSerp(). Every distinct query wrote a
 * new never-expiring file under var/cache/<env>/stopwords-it/, so an organic long tail of unique
 * searches — or a deliberate flood — grew that directory without bound (~9,700 files were found on
 * prod). Measured, a cache hit (35.6µs) barely beat recomputing (39.3µs) and both sit upstream of a
 * Meilisearch network query, so the cache bought no real speed while adding the disk-growth liability.
 *
 * Fix: the 166-iteration preg_replace loop is now a single combined `\b(w1|w2|…)\b` regex compiled
 * once per process (~1.3µs), and the on-disk result cache is gone entirely. These tests prove that
 * processing many DISTINCT terms writes NO per-term files, and that stopword removal is unchanged.
 */
class StopWordsNoDiskCacheTest extends BaseT
{
    /** @return string[] existing var/cache/<env>/stopwords-it directories */
    private function cacheDirs() : array
    {
        $varCacheDir = static::getService(ProjectDir::class)->getProjectDir(['var', 'cache']);
        return glob($varCacheDir . '*/stopwords-it', GLOB_ONLYDIR) ?: [];
    }


    public function testProcessingManyDistinctTermsWritesNoDiskCache() : void
    {
        /** @var StopWords $stopWords */
        $stopWords = static::getService(StopWords::class);

        // clean slate: drop any dir left behind by an older build
        foreach($this->cacheDirs() as $dir) {
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
        }

        // the exact pattern that used to explode the cache: a long tail of unique terms
        for($i = 0; $i < 250; $i++) {
            $stopWords->removeFromSting("il gatto e la volpe numero unico $i " . uniqid());
        }

        $this->assertSame([], $this->cacheDirs(),
            "removeFromSting() must not write per-term files to disk (security-audit #39)");
    }


    public function testStopWordRemovalIsUnchanged() : void
    {
        /** @var StopWords $stopWords */
        $stopWords = static::getService(StopWords::class);

        // core Italian stopwords removed, double spaces collapsed, string trimmed
        $this->assertSame('gatto volpe', $stopWords->removeFromSting('il gatto e la volpe'));
        $this->assertSame('come installare windows 11 vecchio pc',
            $stopWords->removeFromSting('come installare windows 11 su un vecchio pc'));

        // longest-first alternation: "una" is removed whole, never mangled into "un" + stray "a"
        $this->assertSame('come si fa fare cosa pc',
            $stopWords->removeFromSting('come si fa a fare una cosa con il pc'));

        // no Italian stopwords ➡ returned untouched
        $this->assertSame('the quick brown fox', $stopWords->removeFromSting('the quick brown fox'));
    }
}
