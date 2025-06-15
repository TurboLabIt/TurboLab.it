<?php
namespace App\Tests\Unit;

use App\Service\Cms\ArticleEditor;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;


class ArticleEditorTest extends BaseT
{
    protected static function buildEditor() : ArticleEditor
    {
        return static::getService('App\\Service\\Factory')->createArticleEditor();
    }


    public static function titlesProvider() : array
    {
        return [
            [
                'input' => 'Come mostrare un “messaggio” con ‘JS’ – <script>alert("bòòm");</script>',
                'output'=> 'Come mostrare un &quot;messaggio&quot; con &apos;JS&apos; - &lt;script&gt;alert(&quot;bòòm&quot;);&lt;/script&gt;'
            ],
            [
                'input' => 'Come mostrare un “messaggio&rdquo; con &lsquo;JS’ – <script>alert("b&ograve;òm");</script>',
                'output'=> 'Come mostrare un &quot;messaggio&quot; con &apos;JS&apos; - &lt;script&gt;alert(&quot;bòòm&quot;);&lt;/script&gt;'
            ]
        ];
    }


    #[DataProvider('titlesProvider')]
    #[Group('editor')]
    public function testSetTitle(string $input, string $output)
    {
        $editor =
            static::buildEditor()
                ->setTitle($input);

        $actualTitle = $editor->getTitle();

        $this->assertNoLegacyEntities($actualTitle);
        $this->assertEquals($output, $actualTitle);
    }


    public static function bodiesProvider() : array
    {
        return [
            [
                'input' => '
                    <p>Come mostrare un “messaggio&rdquo; con &lsquo;JS’ – &lt;script&gt;alert(&quot;bòòm&quot;);&lt;/script&gt;</p>
                    <p><img src="https://dev0.turbolab.it/immagini/med/2/come-svolgere-test-automatici-9513.avif" alt="some alt text"></p>
                    <p>Perch&egrave; troppi &euro;!</p>
                ',
                'output'=> '<p>Come mostrare un "messaggio" con \'JS\' - &lt;script&gt;alert("bòòm");&lt;/script&gt;</p><p><img src="==###immagine::id::9513###=="></p><p>Perchè troppi €!</p>'
            ]
        ];
    }


    #[DataProvider('bodiesProvider')]
    #[Group('editor')]
    public function testSetBody(string $input, string $output)
    {
        $editor =
            static::buildEditor()
                ->setBody($input);

        $actualBody = $editor->getBody();

        $this->assertNoLegacyEntities($actualBody);
        $this->assertEquals( trim($output), $actualBody);
    }
}
