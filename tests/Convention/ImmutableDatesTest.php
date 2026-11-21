<?php
namespace App\Tests\Convention;

use Doctrine\DBAL\Types\Types;
use FilesystemIterator;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\UseItem;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Every date is a DateTimeImmutable, never a DateTime: DBAL 4.6 deprecates the mutable date types
 * (https://github.com/doctrine/dbal/pull/7575), and DBAL 5 removes them.
 *
 * Nothing at runtime would notice one coming back:
 *   - Doctrine reports only the first hit per process and per issue link, in dev and in prod;
 *   - the deprecation is raised inside Doctrine, and phpunit.dist.xml ignores those (ignoreIndirectDeprecations);
 *   - yet in tests every single hit is reported, and costs time: with the dates of every entity still mutable, about
 *     1,700 per homepage, and the suite ran more than twice as long (2026-11-21).
 *
 * So the mapping of every entity is read from Doctrine itself, traits from other packages included (Gedmo's
 * TimestampableEntity maps DateTime), and src/ and tests/ are read for the DateTime class itself: a DateTime given to a
 * query goes through the deprecated type too, and from the source a variable's class can't be told, so the class is
 * reported wherever the code names it, with no exception: DateMagician takes either. Comments and strings don't count.
 */
class ImmutableDatesTest extends KernelTestCase
{
    public function testEveryMappedDateIsImmutable() : void
    {
        $arrMutable = [];
        foreach( static::getContainer()->get('doctrine')->getManagers() as $em ) {

            foreach( $em->getMetadataFactory()->getAllMetadata() as $meta ) {

                foreach( $meta->fieldMappings as $field => $mapping ) {
                    if( in_array($mapping->type, self::getMutableDateTypes(), true) ) {
                        $arrMutable[] = $meta->getName() . "::\$$field ({$mapping->type})";
                    }
                }
            }
        }

        $this->assertSame([], $arrMutable,
            "Each date column must be mapped as Types::DATETIME_IMMUTABLE (or another *_IMMUTABLE type), with a " .
            "DateTimeImmutable property: the mutable types are deprecated since DBAL 4.6. The timestamps: " .
            "App\Trait\TimestampableEntityTrait, not Gedmo's TimestampableEntity"
        );
    }


    /** A list that came out empty would let every mapping through, forever */
    public function testTheMutableTypesAreKnown() : void
    {
        foreach(['date', 'datetime', 'datetimetz', 'time'] as $type) {
            $this->assertContains($type, self::getMutableDateTypes());
        }

        $this->assertNotContains(Types::DATETIME_IMMUTABLE, self::getMutableDateTypes());
    }


    public function testNoDateTimeInTheCode() : void
    {
        $root        = dirname(__DIR__, 2);
        $arrProblems = [];

        foreach( ["$root/src", "$root/tests"] as $dir ) {

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach( $files as $file ) {

                $code = $file->getExtension() == 'php' ? file_get_contents($file->getPathname()) : '';
                if( stripos($code, 'datetime') === false ) {
                    continue;
                }

                foreach( $this->findDateTimes($code) as $problem ) {
                    $arrProblems[] = str_replace("$root/", '', $file->getPathname()) . ":$problem";
                }
            }
        }

        $this->assertSame([], $arrProblems,
            "Dates must be DateTimeImmutable, never DateTime: Doctrine converts a DateTime, query parameters " .
            "included, with a type deprecated since DBAL 4.6"
        );
    }


    /** A scan that stops recognizing a form would pass forever: it has to be seen failing on each one */
    #[DataProvider('dateTimesProvider')]
    public function testDateTimeIsReported(string $code, array $arrExpected) : void
        { $this->assertSame($arrExpected, $this->findDateTimes("<?php $code")); }


    public static function dateTimesProvider() : iterable
    {
        yield 'new, imported'       => ['use DateTime; $d = new DateTime();', ['1: new \DateTime()']];
        yield 'new, qualified'      => ['namespace A; $d = new \DateTime("-1 day");', ['1: new \DateTime("-1 day")']];
        yield 'aliased import'      => ['use DateTime as When; $d = new When();', ['1: new \DateTime()']];
        yield 'lowercase'           => ['$d = new datetime();', ['1: new \datetime()']];
        yield 'static call'         => ['$d = DateTime::createFromFormat("U", "0");', ['1: \DateTime::createFromFormat("U", "0")']];
        yield 'class constant'      => ['$c = DateTime::class;', ['1: \DateTime::class']];
        yield 'instanceof'          => ['$b = $d instanceof DateTime;', ['1: $d instanceof \DateTime']];
        yield 'parameter type'      => ['function f(DateTime $d) {}', ['1: \DateTime (type)']];
        yield 'nullable type'       => ['function f(?DateTime $d = null) {}', ['1: \DateTime (type)']];
        yield 'union type'          => ['function f(DateTime|int $d) {}', ['1: \DateTime (type)']];
        yield 'return type'         => ['function f() : DateTime {}', ['1: \DateTime (type)']];
        yield 'property type'       => ['class A { protected DateTime $d; }', ['1: \DateTime (type)']];
        yield 'closure type'        => ['$f = fn(DateTime $d) => $d;', ['1: \DateTime (type)']];
        yield 'multi-line'          => ["\$d =\n    new DateTime();", ['2: new \DateTime()']];
        yield 'converted'           => ['$d = DateTime::createFromImmutable(new DateTimeImmutable());',
                                        ['1: \DateTime::createFromImmutable(new \DateTimeImmutable())']];
        yield 'converted, argument' => ['f( DateTime::createFromInterface($d) );', ['1: \DateTime::createFromInterface($d)']];
    }


    #[DataProvider('safeCodeProvider')]
    public function testSafeCodeIsNotReported(string $code) : void
        { $this->assertSame([], $this->findDateTimes("<?php $code")); }


    public static function safeCodeProvider() : iterable
    {
        yield 'immutable'           => ['$d = new DateTimeImmutable(); $e = DateTimeImmutable::createFromFormat("U", "0");'];
        yield 'interface'           => ['function f(DateTimeInterface $d) : ?DateTimeInterface { return $d; }'];
        yield 'time zone'           => ['$z = new DateTimeZone("Europe/Rome");'];
        yield 'import alone'        => ['use DateTime;'];
        yield 'another namespace'   => ['namespace A; $d = new DateTime();'];
        yield 'method named so'     => ['$x->DateTime(); $y = $x->dateTime;'];
        yield 'comment'             => ['// $d = new DateTime();'];
        yield 'docblock'            => ['/** @param DateTime $d */ function f($d) {}'];
        yield 'string'              => ['$s = "DateTime";'];
    }


    /** @return string[] the values of the Types::*_MUTABLE constants: the date types DBAL 4.6 deprecates */
    private static function getMutableDateTypes() : array
    {
        return
            array_values(array_filter(
                (new ReflectionClass(Types::class))->getConstants(),
                fn(string $name) => str_ends_with($name, '_MUTABLE'), ARRAY_FILTER_USE_KEY
            ));
    }


    /** @return string[] "line: code" for each place the code names DateTime */
    private function findDateTimes(string $code) : array
    {
        $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($code);

        // the names as PHP reads them: an import, an alias, the namespace...
        $traverser = new NodeTraverser(new NameResolver());
        $ast = $traverser->traverse($ast);
        $ast = (new NodeTraverser(new ParentConnectingVisitor()))->traverse($ast);

        $printer = new Standard();
        $arrFound = [];

        foreach( (new NodeFinder())->findInstanceOf($ast, Name::class) as $name ) {

            $parent = $name->getAttribute('parent');
            if( $name->toLowerString() != 'datetime' || $parent instanceof UseItem ) {
                continue;
            }

            $arrFound[] =
                $name->getStartLine() . ': ' .
                ( $parent instanceof Expr ? $printer->prettyPrintExpr($parent) : $name->toCodeString() . ' (type)' );
        }

        return $arrFound;
    }
}
