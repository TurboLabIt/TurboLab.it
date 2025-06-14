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
    public function testSetTitleFromScrivi(string $input, string $output)
    {
        $editor =
            static::buildEditor()
                ->setTitle($input);

        $this->assertEquals($output, $editor->getTitle());
    }
}
