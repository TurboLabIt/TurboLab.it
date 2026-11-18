<?php
namespace App\Tests\Smoke;

use App\Controller\FileController;
use App\Service\Cms\Article;
use App\Service\Cms\File;
use App\Service\Factory;
use App\Service\FrontendHelper;
use App\Service\User;
use App\ServiceCollection\Cms\FileCollection;
use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TurboLabIt\BaseCommand\Service\ProjectDir;


/**
 * "File da controllare" (/scarica/da-controllare) shows the results of four checks on the Downloads. A check that found
 * something gets its own section (heading with the count, explanation, table); one that found nothing gets no section,
 * only a line under "Altri controlli effettuati", at the end, before "Altre risorse per gli autori": four headings over
 * empty tables made the page look broken (Zane, 2026-11-18). When every check passed nothing comes before that list,
 * so its heading drops the "Altri".
 *
 * The real controller renders the real template with every combination of empty and non-empty results. The reports are
 * read from a folder of the test's own, since the real ones in var/ are rewritten by FilesToArticles and FilesNotFound,
 * by other tests too, in parallel; the orphans come from a collection the test fills.
 */
class FileNeedFixingTest extends BaseT
{
    // each check: its title, which heads its section and is quoted in its line, and what that line says
    const array CHECKS = [
        'File da controllare'           => 'nessun articolo ha download associati che non siano linkati nel testo',
        'File orfani'                   => 'ogni file è associato almeno a un articolo',
        'File mancanti su filesystem'   => 'tutti i file ospitati da TLI sono scaricabili',
        'File mancanti su database'     => 'tutti i file ospitati da TLI sono indicizzati nel database',
    ];

    // the report each check reads; the orphans aren't a report, they come from the database
    const array REPORT_FILE_NAMES = [
        'File da controllare'           => File::ATTACHED_BUT_UNUSED_FILE_NAME,
        'File mancanti su filesystem'   => File::MISSING_ON_FILESYSTEM_FILE_NAME,
        'File mancanti su database'     => File::MISSING_ON_DATABASE_FILE_NAME,
    ];

    const array FIXTURE_AUTHORS = [User::ID_SYSTEM => ['username' => 'System', 'url' => 'https://turbolab.it/utenti/system']];

    // a report with one item, shaped as FilesToArticles and FilesNotFound write it
    const array REPORTS_WITH_ONE_ITEM = [
        File::ATTACHED_BUT_UNUSED_FILE_NAME => [
            Article::ID_QUALITY_TEST => [
                'title'     => 'Un articolo con un Download non linkato',
                'url'       => 'https://turbolab.it/' . Article::ID_QUALITY_TEST,
                'Authors'   => self::FIXTURE_AUTHORS,
                'Files'     => [
                    File::ID_LOGO => ['title' => 'Un Download non linkato', 'url' => 'https://turbolab.it/scarica/' . File::ID_LOGO]
                ]
            ]
        ],
        File::MISSING_ON_FILESYSTEM_FILE_NAME => [
            File::ID_LOGO => [
                'title'     => 'Un Download senza il suo file',
                'url'       => 'https://turbolab.it/scarica/' . File::ID_LOGO,
                'Authors'   => self::FIXTURE_AUTHORS,
                'Articles'  => [
                    Article::ID_QUALITY_TEST => ['title' => 'Un articolo', 'url' => 'https://turbolab.it/' . Article::ID_QUALITY_TEST]
                ]
            ]
        ],
        File::MISSING_ON_DATABASE_FILE_NAME => [
            ['filepath' => '/tmp/tli-test-orphan.zip', 'filename' => 'tli-test-orphan.zip', 'lastModified' => '2026-11-18 18:00:00']
        ]
    ];

    private ?string $reportsDir = null;


    public static function checkResultsProvider() : Generator
    {
        $arrTitles = array_keys(self::CHECKS);

        for($combination = 0; $combination < 2 ** count($arrTitles); $combination++) {

            $arrFoundSomething = [];
            foreach($arrTitles as $bit => $title) {
                $arrFoundSomething[$title] = (bool)($combination & (1 << $bit));
            }

            $arrWithResults = array_keys( array_filter($arrFoundSomething) );
            $caseName = empty($arrWithResults) ? 'every check passed' : 'results in: ' . implode(', ', $arrWithResults);

            yield $caseName => [$arrFoundSomething];
        }
    }


    #[DataProvider('checkResultsProvider')]
    public function testOnlyTheChecksWithResultsGetASection(array $arrFoundSomething) : void
    {
        $crawler = $this->renderPage($arrFoundSomething);

        $arrExpectedHeadings    = [];
        $arrExpectedPassed      = [];
        foreach(self::CHECKS as $title => $passedText) {

            if( $arrFoundSomething[$title] ) {

                $arrExpectedHeadings[] = "$title: 1";

            } else {

                $arrExpectedPassed[] = "Zero \"$title\" - $passedText";
            }
        }

        if( !empty($arrExpectedPassed) ) {
            $arrExpectedHeadings[] =
                count($arrExpectedPassed) == count(self::CHECKS) ? 'Controlli effettuati' : 'Altri controlli effettuati';
        }

        $arrExpectedHeadings[] = 'Altre risorse per gli autori';

        $this->assertSame($arrExpectedHeadings, $this->texts($crawler, 'main h2'),
            "A section for each check with results, in order, then the passed checks, then the resources for authors");

        $this->assertSame($arrExpectedPassed, $this->texts($crawler, 'main .section-title li'),
            "A line for each check that found nothing, and only for those");
    }


    /** each list generated every 3 hours says when: its own report's date, not another one's */
    public function testEachListShowsWhenItsReportWasGenerated() : void
    {
        // noon UTC: the same day in whatever time zone the page is formatted in
        $arrReportDates = [
            File::ATTACHED_BUT_UNUSED_FILE_NAME     => '2026-01-15 12:00 UTC',
            File::MISSING_ON_FILESYSTEM_FILE_NAME   => '2026-02-16 12:00 UTC',
            File::MISSING_ON_DATABASE_FILE_NAME     => '2026-03-17 12:00 UTC',
        ];

        $crawler = $this->renderPage( array_fill_keys(array_keys(self::CHECKS), true), $arrReportDates );

        foreach([
            'File da controllare'           => '15 gennaio 2026',
            'File mancanti su filesystem'   => '16 febbraio 2026',
            'File mancanti su database'     => '17 marzo 2026',
        ] as $title => $expectedDate) {

            $section =
                $crawler
                    ->filter('main .section-title')
                    ->reduce( fn(Crawler $node) => str_starts_with($node->filter('h2')->text(), "$title:") );

            $this->assertCount(1, $section, "The page must have one section titled \"$title\"");
            $this->assertStringContainsString("è stata generata", $section->text(), "\"$title\" must say when it was generated");
            $this->assertStringContainsString($expectedDate, $section->text(),
                "\"$title\" must show the date of its own report, " . self::REPORT_FILE_NAMES[$title]);
        }
    }


    protected function tearDown() : void
    {
        if( !empty($this->reportsDir) && is_dir($this->reportsDir) ) {

            array_map('unlink', glob($this->reportsDir . '*'));
            rmdir($this->reportsDir);
        }

        $this->reportsDir = null;
        parent::tearDown();
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🧰 the page, rendered with the results the test chooses ***">
    /**
     * @param array<string, bool> $arrFoundSomething check title => whether it found something (one item) or nothing
     * @param array<string, string> $arrReportDates report file name => its date (default: now)
     */
    private function renderPage(array $arrFoundSomething, array $arrReportDates = []) : Crawler
    {
        $this->reportsDir = sys_get_temp_dir() . '/' . uniqid('tli-test-need-fixing-') . '/';
        mkdir($this->reportsDir);

        foreach(self::REPORT_FILE_NAMES as $title => $fileName) {

            $filePath = $this->reportsDir . $fileName;
            file_put_contents($filePath, json_encode( $arrFoundSomething[$title] ? self::REPORTS_WITH_ONE_ITEM[$fileName] : [] ));

            if( !empty($arrReportDates[$fileName]) ) {
                touch( $filePath, strtotime($arrReportDates[$fileName]) );
            }
        }

        clearstatcache();

        $projectDir = new class($this->reportsDir) extends ProjectDir {

            public function __construct(private readonly string $reportsDir) {}

            public function getVarDirFromFilePath(array|string $filePath) : string
            {
                return $this->reportsDir . implode(DIRECTORY_SEPARATOR, (array)$filePath);
            }
        };

        static::loginAsSystem();

        $orphans = new class(static::getService(Factory::class), $arrFoundSomething['File orfani']) extends FileCollection {

            public function __construct(Factory $factory, private readonly bool $foundSomething)
            {
                parent::__construct($factory);
            }

            public function loadOrphans() : static
            {
                return $this->foundSomething ? $this->load(File::ID_LOGO) : $this->setEntities([]);
            }
        };

        static::getService('request_stack')->push( Request::create( $this->generateUrl('app_file_need-fixing') ) );

        $response =
            static::getService(FileController::class)
                ->needFixing( $projectDir, static::getService(FrontendHelper::class), $orphans );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        return new Crawler( $response->getContent() );
    }


    /** the text of each element, with the non-breaking space that keeps a dash beside its words as a plain space */
    private function texts(Crawler $crawler, string $selector) : array
    {
        return $crawler->filter($selector)->each(
            fn(Crawler $node) => trim( str_replace(mb_chr(0xA0), ' ', $node->text()) )
        );
    }
    //</editor-fold>
}
