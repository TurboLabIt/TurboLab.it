<?php
namespace App\Tests\Smoke;

use App\Service\Cms\Tag;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * Opening every category and checking all its links and images is the slowest test in the suite.
 * ParaTest runs whole classes on a worker, so the categories are split among TagCategories{1..SHARDS}Test:
 * each class takes one category every SHARDS, and the shards run in parallel.
 */
abstract class TagCategoriesTestCase extends BaseT
{
    use TagTitleCheckerTrait;

    const int SHARDS = 4;
    // 1..SHARDS, set by each TagCategories{N}Test
    const int SHARD = 0;


    public static function categoryProvider() : array
    {
        $arrData = static::getTagCollection()->loadCategories()->getAll();

        $arrShard = [];
        $i = 0;
        foreach($arrData as $key => $category) {

            if( $i++ % static::SHARDS == static::SHARD - 1 ) {
                $arrShard[$key] = $category;
            }
        }

        return self::repackDataProviderArray($arrShard);
    }


    #[DataProvider('categoryProvider')]
    public function testOpenAllCategories(Tag $category)
    {
        static::$client = null;

        $url = $category->getUrl();
        $this->assertStringEndsWith("-" . $category->getId(), $url, "Failing URL: $url");

        $crawler = $this->fetchDomNode($url);
        $this->assertResponseIsSuccessful();
        $this->tagTitleAsH1Checker($category, $crawler);

        // H2
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(10, $countH2);

        $this
            ->internalLinksChecker($crawler)
            ->internalImagesChecker($crawler);
    }
}
