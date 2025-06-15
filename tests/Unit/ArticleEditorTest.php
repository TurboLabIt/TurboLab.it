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
                'input' => 'Come mostrare un messaggio con JS: <script>alert("bòòm");</script>',
                'output'=> 'Come mostrare un messaggio con JS: &lt;script&gt;alert(&quot;bòòm&quot;);&lt;/script&gt;'
            ],
            [
                'input' => 'XSS con entities: <script>alert("b&ograve;&ograve;m");</script>',
                'output'=> 'XSS con entities: &lt;script&gt;alert(&quot;bòòm&quot;);&lt;/script&gt;'
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
                    <p>SCRIPT: &lt;script&gt;alert("bòòm");&lt;/script&gt;</p>
                    <p><img src="my-image.png" alt="some alt text"></p>
                    <p>Perch&grave; troppi &euro;</p>
                ',
                'output'=> '
                    <p>SCRIPT: &lt;script&gt;alert("bòòm");&lt;/script&gt;</p>
                    <p><img src="my-image.png"></p>
                    <p>Perchè troppi €</p>
                '
            ],
            [
                'input'=> '
                    <p>SCRIPT: <script>alert("bòòm")</script></p>
                    <p><img src="my-image.png" alt="some alt text"></p>
                    <p>Perch&grave; troppi &euro;</p>
                ',
                'output'=> '
                    <p><img src="my-image.png"></p>
                    <p>Perchè troppi €</p>
                '
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
