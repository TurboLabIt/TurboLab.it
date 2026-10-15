<?php
namespace App\Tests\Editor;

use App\Entity\Cms\ArticleTag;
use App\Service\Cms\ArticleEditor;
use App\Service\Cms\Tag;
use App\Service\Factory;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * A replaced tag (tag.replacement_id, assigned nightly by TagAggregatorCommand) 301s to its replacement, so it must
 * never be assigned to an article. autotag() always swapped it, but the tag editor didn't: the search offered it as-is
 * when the term matched mid-title ("installazion" listed "disinstallazioni"), and set-tags stored whatever it got.
 * An article tagged that way failed TagTest::testOpenLatestArticlesTags until the nightly aggregator re-pointed it.
 *
 * Nothing is saved: every change stays in memory and is discarded with the kernel.
 *
 * @see \App\ServiceCollection\Cms\TagCollection::loadBySearchTagOrCreate()
 * @see \App\Service\Cms\ArticleEditor::setTags()
 * @see \App\Command\TagAggregatorCommand::BAD_TAGS
 */
class TagReplacementTest extends BaseT
{
    // replaced by Tag::ID_UNINSTALL in TagAggregatorCommand::BAD_TAGS
    const string REPLACED_TAG_TITLE = 'disinstallazioni';


    public static function searchTermProvider() : array
    {
        return [
            // mid-title matches: these listed the replaced tags as-is
            ['installazion'], ['install'], ['stallazioni'],
            // perfect and prefix matches
            ['disinstallazioni'], ['disinstall'],
        ];
    }


    #[DataProvider('searchTermProvider')]
    public function testSearchNeverOffersAReplacedTag(string $term) : void
    {
        $this->getReplacedTag();

        $tags = static::getService(Factory::class)->createTagCollection()->loadBySearchTagOrCreate($term);

        $arrOfferedIds = [];
        foreach($tags as $tag) {

            $this->assertNull($tag->getReplacement(), "Replaced tag ##" . $tag->getId() . "## offered for ##$term##");
            $arrOfferedIds[] = $tag->getId();
        }

        $this->assertContains(Tag::ID_UNINSTALL, $arrOfferedIds, "The replacement isn't offered for ##$term##");
    }


    public function testSetTagsAssignsTheReplacementInstead() : void
    {
        $replacedTag    = $this->getReplacedTag();
        $articleEditor  = static::getArticleEditor();

        $articleEditor->setTags([$replacedTag, static::getTag(Tag::ID_WINDOWS)], static::getUser());

        $this->assertTagIds([Tag::ID_UNINSTALL, Tag::ID_WINDOWS], $articleEditor);
    }


    public function testSetTagsDoesNotDuplicateTheReplacement() : void
    {
        $replacedTag    = $this->getReplacedTag();
        $articleEditor  = static::getArticleEditor();

        $articleEditor->setTags([
            $replacedTag, static::getTag(Tag::ID_UNINSTALL), static::getTag(Tag::ID_WINDOWS)
        ], static::getUser());

        $this->assertTagIds([Tag::ID_UNINSTALL, Tag::ID_WINDOWS], $articleEditor);
    }


    public function testSetTagsDropsAReplacedTagTheArticleAlreadyHas() : void
    {
        $replacedTag    = $this->getReplacedTag();
        $articleEditor  = static::getArticleEditor();

        // tagged before this fix: the tag modal lists it, and sends it back on save
        $articleEditor->getEntity()->addTag(
            (new ArticleTag())
                ->setTag( $replacedTag->getEntity() )
                ->setUser( static::getUser()->getEntity() )
        );

        $articleEditor->setTags([$replacedTag, static::getTag(Tag::ID_WINDOWS)], static::getUser());

        $this->assertTagIds([Tag::ID_UNINSTALL, Tag::ID_WINDOWS], $articleEditor);
    }


    protected function getReplacedTag() : Tag
    {
        $tag = static::getService(Factory::class)->createTag()->loadByTitle(static::REPLACED_TAG_TITLE);

        $this->assertSame(
            Tag::ID_UNINSTALL, $tag->getReplacement()?->getId(),
            "Fixture: ##" . static::REPLACED_TAG_TITLE . "## is no longer replaced by ##" . Tag::ID_UNINSTALL . "##"
        );

        return $tag;
    }


    protected function assertTagIds(array $arrExpectedIds, ArticleEditor $articleEditor) : void
    {
        $arrIds = array_keys( $articleEditor->getTags() );
        sort($arrIds);
        sort($arrExpectedIds);

        $this->assertSame($arrExpectedIds, $arrIds);
    }
}
