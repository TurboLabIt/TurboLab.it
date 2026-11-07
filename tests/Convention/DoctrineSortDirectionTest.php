<?php
namespace App\Tests\Convention;

use FilesystemIterator;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClassConstant;
use SortDirection;
use TurboLabIt\ServiceEntityPlusBundle\SEPRepository;


/**
 * Every Doctrine sort direction must be a SortDirection case: strings are deprecated since ORM 3.7
 * (https://github.com/doctrine/orm/issues/11313).
 *
 * This reads the source instead of running it, because nothing at runtime would notice a new string:
 *   - Doctrine reports only the first hit per process and per issue link, so one string hides all the others;
 *   - the deprecation is raised inside Doctrine, and phpunit.dist.xml ignores those (ignoreIndirectDeprecations);
 *   - some repository queries are run by no test at all.
 *
 * It covers #[ORM\OrderBy], ->orderBy(), ->addOrderBy() and new Expr\OrderBy() in src/, tests/ and our own packages,
 * and it fails closed: a direction that isn't visibly a SortDirection case is reported even when it would work at
 * runtime, a variable included, because its type can't be read from here. Leaving the direction out is fine: Doctrine
 * then picks ASC without complaining. Expr\OrderBy::add() is out of reach, a bare ->add() says nothing without types.
 * SQL and DQL strings aren't affected by the deprecation.
 */
class DoctrineSortDirectionTest extends TestCase
{
    public function testEveryDirectionIsASortDirection() : void
    {
        $root        = dirname(__DIR__, 2);
        $arrProblems = [];

        foreach( ["$root/src", "$root/tests", ...glob("$root/vendor/turbolabit/*/src")] as $dir ) {

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach( $files as $file ) {

                $code = $file->getExtension() == 'php' ? file_get_contents($file->getPathname()) : '';
                if( stripos($code, 'orderby') === false ) {
                    continue;
                }

                foreach( $this->findUnsafeDirections($code) as $problem ) {
                    $arrProblems[] = str_replace("$root/", '', $file->getPathname()) . ":$problem";
                }
            }
        }

        $this->assertSame([], $arrProblems,
            "A sort direction must be SortDirection::Ascending or SortDirection::Descending: " .
            "anything else is deprecated since ORM 3.7, see https://github.com/doctrine/orm/issues/11313"
        );
    }


    /**
     * DEFAULT_ORDER_DIRECTION is the one exception accepted by the scan: PHP enforces its type on every override,
     * but only as long as the bundle declares it.
     */
    public function testDefaultOrderDirectionIsTyped() : void
    {
        $type = (new ReflectionClassConstant(SEPRepository::class, 'DEFAULT_ORDER_DIRECTION'))->getType();
        $this->assertSame(SortDirection::class, (string)$type);
    }


    /** A scan that stops recognizing a call would pass forever: it has to be seen failing on each form */
    #[DataProvider('unsafeDirectionsProvider')]
    public function testUnsafeDirectionIsReported(string $code, array $arrExpected) : void
        { $this->assertSame($arrExpected, $this->findUnsafeDirections("<?php $code")); }


    public static function unsafeDirectionsProvider() : iterable
    {
        yield 'orderBy'             => ['$qb->orderBy("t.id", "DESC");', ['1: "DESC"']];
        yield 'addOrderBy'          => ['$qb->addOrderBy("t.id", "ASC");', ['1: "ASC"']];
        yield 'lowercase method'    => ['$qb->orderby("t.id", "ASC");', ['1: "ASC"']];
        yield 'nullsafe'            => ['$qb?->orderBy("t.id", "ASC");', ['1: "ASC"']];
        yield 'multi-line call'     => ["\$qb->orderBy(\n\"t.id\",\n\"DESC\"\n);", ['3: "DESC"']];
        yield 'chained calls'       => ['$qb->orderBy("t.a", "ASC")->addOrderBy("t.b", "DESC");', ['1: "DESC"', '1: "ASC"']];
        yield 'explicit null'       => ['$qb->orderBy("t.id", null);', ['1: null']];
        yield 'variable'            => ['$qb->orderBy("t.id", $direction);', ['1: $direction']];
        yield 'other constant'      => ['$qb->orderBy("t.id", self::DIRECTION);', ['1: self::DIRECTION']];
        yield 'ternary of strings'  => ['$qb->orderBy("t.id", $asc ? "ASC" : "DESC");', ['1: $asc ? "ASC" : "DESC"']];
        yield 'half-safe ternary'   => ['$qb->orderBy("t.id", $asc ? SortDirection::Ascending : "DESC");',
                                        ['1: $asc ? SortDirection::Ascending : "DESC"']];
        yield 'new OrderBy'         => ['new OrderBy("t.id", "ASC");', ['1: "ASC"']];
        yield 'new Expr\OrderBy'    => ['new Expr\OrderBy("t.id", "DESC");', ['1: "DESC"']];
        yield 'attribute'           => ['class A { #[ORM\OrderBy(["ranking" => "ASC"])] public $a; }', ['1: "ASC"']];
        yield 'imported attribute'  => ['class A { #[OrderBy(["ranking" => "ASC"])] public $a; }', ['1: "ASC"']];
        yield 'qualified attribute' => ['class A { #[\Doctrine\ORM\Mapping\OrderBy(["a" => "ASC"])] public $a; }', ['1: "ASC"']];
        yield 'attribute, 2 fields' => ['class A { #[ORM\OrderBy(["a" => SortDirection::Ascending, "b" => "DESC"])] public $a; }',
                                        ['1: "DESC"']];
        yield 'attribute, no array' => ['class A { #[ORM\OrderBy(self::ORDER)] public $a; }', ['1: self::ORDER']];
    }


    #[DataProvider('safeCodeProvider')]
    public function testSafeCodeIsNotReported(string $code) : void
        { $this->assertSame([], $this->findUnsafeDirections("<?php $code")); }


    public static function safeCodeProvider() : iterable
    {
        yield 'enum case'           => ['$qb->orderBy("t.id", SortDirection::Descending);'];
        yield 'addOrderBy'          => ['$qb->addOrderBy("t.id", SortDirection::Ascending);'];
        yield 'fully qualified'     => ['$qb->orderBy("t.id", \SortDirection::Ascending);'];
        yield 'ternary of cases'    => ['$qb->orderBy("t.id", $asc ? SortDirection::Ascending : SortDirection::Descending);'];
        yield 'default direction'   => ['$qb->orderBy(static::DEFAULT_ORDER_BY, static::DEFAULT_ORDER_DIRECTION);'];
        yield 'direction left out'  => ['$qb->orderBy("t.id");'];
        yield 'Expr as only arg'    => ['$qb->orderBy(new Expr\OrderBy("t.id", SortDirection::Ascending));'];
        yield 'new, no direction'   => ['new Expr\OrderBy("t.id");'];
        yield 'attribute'           => ['class A { #[ORM\OrderBy(["ranking" => SortDirection::Ascending])] public $a; }'];
        yield 'comment'             => ['// $qb->orderBy("t.id", "ASC");'];
        yield 'raw SQL'             => ['$sql = str_replace("##DIR##", "DESC", "SELECT id FROM t ORDER BY id ##DIR##");'];
        yield 'declaration'         => ['class A { public function orderBy(string $sort, string $order = "ASC") {} }'];
        yield 'unrelated method'    => ['$collection->sortBy("t.id", "ASC");'];
    }


    /** @return string[] "line: direction" for each direction that isn't visibly a SortDirection case */
    private function findUnsafeDirections(string $code) : array
    {
        $ast        = (new ParserFactory())->createForNewestSupportedVersion()->parse($code);
        $printer    = new Standard();
        $arrUnsafe  = [];

        foreach( (new NodeFinder())->find($ast, fn(Node $node) : bool => true) as $node ) {

            foreach( $this->getDirections($node) as $direction ) {

                if( !$this->isSortDirection($direction) ) {
                    $arrUnsafe[] = $direction->getStartLine() . ': ' . $printer->prettyPrintExpr($direction);
                }
            }
        }

        return $arrUnsafe;
    }


    /** @return Expr[] */
    private function getDirections(Node $node) : array
    {
        $isOrderByCall =
            ( $node instanceof MethodCall || $node instanceof NullsafeMethodCall ) &&
            $node->name instanceof Identifier &&
            in_array($node->name->toLowerString(), ['orderby', 'addorderby']);

        $isNewOrderBy = $node instanceof New_ && $node->class instanceof Name && $node->class->getLast() == 'OrderBy';

        if( $isOrderByCall || $isNewOrderBy ) {

            // the first argument is the field
            return array_column(array_slice($node->getArgs(), 1), 'value');
        }

        if( $node instanceof Attribute && $node->name->getLast() == 'OrderBy' ) {

            $value = $node->args[0]->value;
            return $value instanceof Array_ ? array_column($value->items, 'value') : [$value];
        }

        return [];
    }


    private function isSortDirection(Expr $direction) : bool
    {
        if( $direction instanceof Ternary ) {

            return
                $direction->if !== null && $this->isSortDirection($direction->if) &&
                $this->isSortDirection($direction->else);
        }

        if( !$direction instanceof ClassConstFetch ) {
            return false;
        }

        return
            ( $direction->class instanceof Name && $direction->class->getLast() == 'SortDirection' ) ||
            ( $direction->name instanceof Identifier && $direction->name->name == 'DEFAULT_ORDER_DIRECTION' );
    }
}
