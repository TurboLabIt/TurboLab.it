<?php
namespace App\Tests\Editor;

use App\Service\Cms\ArticleEditor;
use App\Service\Cms\ArticlePlanner;
use App\Service\Factory;
use App\Service\User;
use App\Tests\BaseT;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;


/**
 * When ArticlePlanner publishes an article, for each choice of the editor's status menu
 * (ArticleEditorController::setPublishingStatus()): news are spaced through the day, every other format gets a business
 * day of its own, an urgent article goes out at once or on the next business day, a date the article already has is
 * kept or dropped.
 *
 * The clock is set (ClockSensitiveTrait) in 2036: dev0's copy of the data has nothing scheduled that year, so every
 * article the planner finds on those days is one the test created, and each case means the same whatever day it runs.
 * Every date carries its day of the week, which the test checks. The planner is asked as the controller asks it,
 * then the article is saved, and the dates are read back from the database.
 */
class ArticlePlannerTest extends BaseT
{
    use ClockSensitiveTrait;

    private const string FIXTURE_TITLE_PREFIX = 'tli-test-article-planner-';

    const int DRAFT     = ArticleEditor::PUBLISHING_STATUS_DRAFT;
    const int READY     = ArticleEditor::PUBLISHING_STATUS_READY_FOR_REVIEW;
    const int PUBLISHED = ArticleEditor::PUBLISHING_STATUS_PUBLISHED;
    const int KO        = ArticleEditor::PUBLISHING_STATUS_KO;

    /** @var ArticleEditor[] the articles already published when the planner is asked */
    private array $arrPublished = [];

    /** @var ArticleEditor[] every article the test created */
    private array $arrArticles = [];


    /** [now, news already scheduled, the news' date] */
    public static function newsProvider() : iterable
    {
        yield 'urgent: now, whatever is queued'     => ['Tue 2036-03-11 05:00', ['Tue 2036-03-11 06:30'], 'Tue 2036-03-11 05:00', true];
        yield 'nothing queued, before 06:30: 06:30' => ['Tue 2036-03-11 05:00', [], 'Tue 2036-03-11 06:30'];
        yield 'nothing queued, later: now'          => ['Tue 2036-03-11 10:17', [], 'Tue 2036-03-11 10:17'];
        yield 'after 21:30: tomorrow at 06:30'      => ['Tue 2036-03-11 22:00', [], 'Wed 2036-03-12 06:30'];
        yield 'on a Sunday as on any day'           => ['Sun 2036-03-16 05:00', [], 'Sun 2036-03-16 06:30'];
        yield 'the day before does not count'       => ['Tue 2036-03-11 10:17', ['Mon 2036-03-10 21:00'], 'Tue 2036-03-11 10:17'];
        yield 'the second of the day: 08:05'        => ['Tue 2036-03-11 05:00', ['Tue 2036-03-11 06:30'], 'Tue 2036-03-11 08:05'];
        yield 'then 1 hour, +15 min each that day'  => ['Tue 2036-03-11 05:00', ['Tue 2036-03-11 06:30', 'Tue 2036-03-11 08:05'],
                                                        'Tue 2036-03-11 09:35'];
        yield 'at 21:30: still that day'            => ['Tue 2036-03-11 05:00', ['Tue 2036-03-11 20:15'], 'Tue 2036-03-11 21:30'];
        yield 'past 21:30: next day at 06:30'       => ['Tue 2036-03-11 05:00', ['Tue 2036-03-11 20:30'], 'Wed 2036-03-12 06:30'];
        yield 'after midnight: lifted to 06:30'     => ['Tue 2036-03-11 05:00', ['Tue 2036-03-11 23:00'], 'Wed 2036-03-12 06:30'];
        yield 'a slot already gone: now'            => ['Tue 2036-03-11 10:17', ['Tue 2036-03-11 06:30'], 'Tue 2036-03-11 10:17'];
        yield 'an editor marks it ready: paced'     => ['Tue 2036-03-11 05:00', [], 'Tue 2036-03-11 06:30', false, self::READY];
    }


    #[DataProvider('newsProvider')]
    public function testNewsAreSpacedThroughTheDay(
        string $now, array $arrQueued, string $expected, bool $urgent = false, int $status = self::PUBLISHED
    ) : void
    {
        $news = $this->plan($now, ArticleEditor::FORMAT_NEWS, $status, $urgent, $arrQueued);
        $this->assertSame([self::PUBLISHED, $expected], $this->stored($news));
    }


    /** [now, articles already scheduled, the article's date] */
    public static function articlesProvider() : iterable
    {
        yield 'a free business day, morning: now'   => ['Tue 2036-03-11 10:17', [], 'Tue 2036-03-11 10:17'];
        yield 'still at 12:59'                      => ['Tue 2036-03-11 12:59', [], 'Tue 2036-03-11 12:59'];
        yield 'from 13:00: tomorrow at midnight'    => ['Tue 2036-03-11 13:00', [], 'Wed 2036-03-12 00:00'];
        yield 'today taken: tomorrow'               => ['Tue 2036-03-11 10:17', ['Tue 2036-03-11 00:00'], 'Wed 2036-03-12 00:00'];
        yield 'a day taken: the next free one'      => ['Tue 2036-03-11 15:00', ['Wed 2036-03-12 00:00'], 'Thu 2036-03-13 00:00'];
        yield 'taken, at any time of the day'       => ['Tue 2036-03-11 15:00', ['Wed 2036-03-12 00:00', 'Thu 2036-03-13 09:00'],
                                                        'Fri 2036-03-14 00:00'];
        yield 'a Friday afternoon: Monday'          => ['Fri 2036-03-14 15:00', [], 'Mon 2036-03-17 00:00'];
        yield 'a Saturday: Monday'                  => ['Sat 2036-03-15 10:17', [], 'Mon 2036-03-17 00:00'];
        yield 'not on Easter Monday'                => ['Fri 2036-04-11 15:00', [], 'Tue 2036-04-15 00:00'];
        yield 'not on April 25th'                   => ['Thu 2036-04-24 15:00', [], 'Mon 2036-04-28 00:00'];
        yield 'not on May 1st'                      => ['Wed 2036-04-30 15:00', [], 'Fri 2036-05-02 00:00'];
        yield 'not at Christmas, nor on the 26th'   => ['Wed 2036-12-24 15:00', [], 'Mon 2036-12-29 00:00'];
    }


    #[DataProvider('articlesProvider')]
    public function testArticlesGetABusinessDayOfTheirOwn(string $now, array $arrScheduled, string $expected) : void
    {
        $article = $this->plan($now, ArticleEditor::FORMAT_ARTICLE, self::PUBLISHED, false, $arrScheduled);

        $this->assertSame([self::PUBLISHED, $expected], $this->stored($article));
        $this->assertSame($arrScheduled, $this->storedDatesOfThePublished(), "The others must stay where they are");
    }


    /** [now, articles already scheduled, the article's date, theirs afterwards] */
    public static function urgentArticlesProvider() : iterable
    {
        yield 'morning, nothing out today: now'     => ['Tue 2036-03-11 10:17', [], 'Tue 2036-03-11 10:17'];
        yield 'one out this morning: tomorrow'      => ['Tue 2036-03-11 10:17', ['Tue 2036-03-11 00:00'], 'Wed 2036-03-12 00:00'];
        yield 'one due later today: tomorrow'       => ['Tue 2036-03-11 10:17', ['Tue 2036-03-11 15:00'], 'Wed 2036-03-12 00:00'];
        yield 'from 12:00: tomorrow'                => ['Tue 2036-03-11 12:00', [], 'Wed 2036-03-12 00:00'];
        yield 'a Friday afternoon: Monday'          => ['Fri 2036-03-14 15:00', [], 'Mon 2036-03-17 00:00'];
        yield 'not on Easter Monday'                => ['Fri 2036-04-11 15:00', [], 'Tue 2036-04-15 00:00'];
        yield 'its day taken: the other one moves'  => ['Tue 2036-03-11 15:00', ['Wed 2036-03-12 00:00'], 'Wed 2036-03-12 00:00',
                                                        ['Thu 2036-03-13 00:00']];
        // each to the first day free by then: Thursday is taken, and so is Friday when the second one moves
        yield 'two on its day: both move'           => ['Tue 2036-03-11 15:00', ['Wed 2036-03-12 00:00', 'Wed 2036-03-12 09:00', 'Thu 2036-03-13 00:00'],
                                                        'Wed 2036-03-12 00:00', ['Fri 2036-03-14 00:00', 'Mon 2036-03-17 00:00', 'Thu 2036-03-13 00:00']];
    }


    #[DataProvider('urgentArticlesProvider')]
    public function testAnUrgentArticleGoesOutTodayOrOnTheNextBusinessDay(
        string $now, array $arrScheduled, string $expected, ?array $arrScheduledAfter = null
    ) : void
    {
        $article = $this->plan($now, ArticleEditor::FORMAT_ARTICLE, self::PUBLISHED, true, $arrScheduled);

        $this->assertSame([self::PUBLISHED, $expected], $this->stored($article));
        $this->assertSame($arrScheduledAfter ?? $arrScheduled, $this->storedDatesOfThePublished(),
            "An article scheduled on the day the urgent one takes must move to the next free day, and only that one");
    }


    /** [now, format, status asked for, the article's own date, the status and the date afterwards] */
    public static function ownDateProvider() : iterable
    {
        yield 'published, its date ahead: kept'     => ['Tue 2036-03-11 10:17', ArticleEditor::FORMAT_ARTICLE, self::PUBLISHED,
                                                        'Thu 2036-03-13 10:15', self::PUBLISHED, 'Thu 2036-03-13 10:15'];
        yield 'a news, its date ahead: kept'        => ['Tue 2036-03-11 10:17', ArticleEditor::FORMAT_NEWS, self::PUBLISHED,
                                                        'Thu 2036-03-13 10:15', self::PUBLISHED, 'Thu 2036-03-13 10:15'];
        yield 'ready, its date ahead: out then'     => ['Tue 2036-03-11 10:17', ArticleEditor::FORMAT_ARTICLE, self::READY,
                                                        'Thu 2036-03-13 10:15', self::PUBLISHED, 'Thu 2036-03-13 10:15'];
        yield 'published, its date gone: afresh'    => ['Tue 2036-03-11 15:00', ArticleEditor::FORMAT_ARTICLE, self::PUBLISHED,
                                                        'Thu 2036-03-06 10:15', self::PUBLISHED, 'Wed 2036-03-12 00:00'];
        yield 'a news, its date gone: paced'        => ['Tue 2036-03-11 05:00', ArticleEditor::FORMAT_NEWS, self::PUBLISHED,
                                                        'Thu 2036-03-06 10:15', self::PUBLISHED, 'Tue 2036-03-11 06:30'];
        yield 'earlier today: scheduled afresh'     => ['Tue 2036-03-11 15:00', ArticleEditor::FORMAT_ARTICLE, self::PUBLISHED,
                                                        'Tue 2036-03-11 08:00', self::PUBLISHED, 'Wed 2036-03-12 00:00'];
        yield 'back to draft: the date ahead stays' => ['Tue 2036-03-11 10:17', ArticleEditor::FORMAT_ARTICLE, self::DRAFT,
                                                        'Thu 2036-03-13 10:15', self::DRAFT, 'Thu 2036-03-13 10:15'];
        // a date is gone once it is before today: yesterday evening's goes, this morning's stays
        yield 'back to draft: yesterday\'s goes'    => ['Tue 2036-03-11 10:17', ArticleEditor::FORMAT_ARTICLE, self::DRAFT,
                                                        'Mon 2036-03-10 20:00', self::DRAFT, null];
        yield 'back to draft: today\'s stays'       => ['Tue 2036-03-11 10:17', ArticleEditor::FORMAT_ARTICLE, self::DRAFT,
                                                        'Tue 2036-03-11 08:00', self::DRAFT, 'Tue 2036-03-11 08:00'];
        yield 'ready, its date gone: dropped'       => ['Tue 2036-03-11 10:17', ArticleEditor::FORMAT_ARTICLE, self::READY,
                                                        'Thu 2036-03-06 10:15', self::READY, null];
        yield 'blocked: no date'                    => ['Tue 2036-03-11 10:17', ArticleEditor::FORMAT_ARTICLE, self::KO,
                                                        null, self::KO, null];
    }


    #[DataProvider('ownDateProvider')]
    public function testADateTheArticleAlreadyHas(
        string $now, int $format, int $status, ?string $ownDate, int $expectedStatus, ?string $expectedDate
    ) : void
    {
        $article = $this->plan($now, $format, $status, false, [], $ownDate);
        $this->assertSame([$expectedStatus, $expectedDate], $this->stored($article));
    }


    /** only an editor's "ready" publishes a news */
    public function testAnAuthorMarkingTheirNewsReadyPublishesNothing() : void
    {
        $news = $this->plan('Tue 2036-03-11 10:17', ArticleEditor::FORMAT_NEWS, self::READY, userId: User::ID_SYSTEM);
        $this->assertSame([self::READY, null], $this->stored($news));
    }


    protected function tearDown() : void
    {
        foreach($this->arrArticles as $article) {
            if( !empty($article->getId()) ) {
                $article->delete();
            }
        }

        $this->arrArticles = $this->arrPublished = [];
        parent::tearDown();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 the planner, asked at the time the test chooses ***">
    /**
     * Logs in, publishes the articles of the same format the case wants already out (by System, as the article),
     * creates the article as a draft, with its own date if given, then asks the planner at the time given and saves,
     * as ArticleEditorController::setPublishingStatus() does. ID_DEFAULT_ADMIN is a founder: every status, urgent
     * included; System is the article's author, not an editor
     */
    private function plan(
        string $now, int $format, int $status, bool $urgent = false, array $arrPublished = [], ?string $ownDate = null,
        int $userId = User::ID_DEFAULT_ADMIN
    ) : ArticleEditor
    {
        static::loginAs($userId);
        $this->deleteWhatAnInterruptedRunLeft();

        foreach($arrPublished as $when) {

            $this->arrPublished[] =
                $this->createArticle($format)
                    ->setPublishedAt( $this->at($when) )
                    ->setPublishingStatus(self::PUBLISHED)
                    ->save();
        }

        $article = $this->createArticle($format);
        if( $ownDate !== null ) {
            $article->setPublishedAt( $this->at($ownDate) );
        }
        $article->save();

        static::mockTime( $this->at($now) );
        static::getService(ArticlePlanner::class)->setPublishingStatus($article, $status, $urgent);
        $article->save();

        return $article;
    }


    /** a run stopped half-way never got to tearDown(): its articles sit on the very days these cases need free */
    private function deleteWhatAnInterruptedRunLeft() : void
    {
        $arrIds =
            static::getEntityManager()->getConnection()->fetchFirstColumn(
                'SELECT id FROM article WHERE title LIKE ?', [self::FIXTURE_TITLE_PREFIX . '%']
            );

        foreach($arrIds as $id) {
            static::getService(Factory::class)->createArticleEditor()->load( (int)$id )->delete();
        }
    }


    private function createArticle(int $format) : ArticleEditor
    {
        $factory = static::getService(Factory::class);

        return
            $this->arrArticles[] =
                $factory->createArticleEditor()
                    ->setTitle( uniqid(self::FIXTURE_TITLE_PREFIX) )
                    ->setFormat($format)
                    ->addAuthor( $factory->createUser()->load(User::ID_SYSTEM) );
    }


    /** "Tue 2036-03-11 10:17": a date the test writes with its day of the week, checked */
    private function at(string $when) : DateTimeImmutable
    {
        [$dayOfWeek, $dateTime] = explode(' ', $when, 2);
        $date = new DateTimeImmutable($dateTime);

        $this->assertSame($dayOfWeek, $date->format('D'), "The test itself is wrong: $dateTime isn't a $dayOfWeek");
        return $date;
    }


    /** @return array{int, ?string} the status and the date of an article, as the database has them */
    private function stored(ArticleEditor $article) : array
    {
        $row =
            static::getEntityManager()->getConnection()->fetchAssociative(
                'SELECT publishing_status, published_at FROM article WHERE id = ?', [$article->getId()]
            );

        return [
            (int)$row['publishing_status'],
            $row['published_at'] === null ? null : (new DateTimeImmutable($row['published_at']))->format('D Y-m-d H:i')
        ];
    }


    /** @return string[] the dates of the articles already published, in the order the test gave them */
    private function storedDatesOfThePublished() : array
        { return array_map(fn(ArticleEditor $article) => $this->stored($article)[1], $this->arrPublished); }
    //</editor-fold>
}
