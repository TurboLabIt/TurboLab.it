<?php
namespace App\Tests\Editor;

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



    public static function articleEditorTestProvider() : array
    {
        return [
            [
                [
                    'input-title'           => <<<'END_OF_TEXTBLOCK'
                            Come mostrare un “messaggio” con ‘JS’ – <script>alert("bòòm");</script>
                        END_OF_TEXTBLOCK,
                    'input-body'            => <<<'END_OF_TEXTBLOCK'
                            Come mostrare un “messaggio” con ‘JS’ – <script>alert("bòòm");</script>
                        END_OF_TEXTBLOCK,
                    'output-title-db'       => <<<'END_OF_TEXTBLOCK'
                            Come mostrare un "messaggio" con 'JS' - <script>alert("bòòm");</script>
                        END_OF_TEXTBLOCK,
                    'output-title-page'     => <<<'END_OF_TEXTBLOCK'
                            Come mostrare un "messaggio" con 'JS' - &lt;script&gt;alert("bòòm");&lt;/script&gt;
                        END_OF_TEXTBLOCK,
                    'output-abstract-db'    => <<<'END_OF_TEXTBLOCK'
                            Come mostrare un "messaggio" con 'JS' -
                        END_OF_TEXTBLOCK,
                    'output-abstract-page'  => <<<'END_OF_TEXTBLOCK'
                            Come mostrare un "messaggio" con 'JS' -
                        END_OF_TEXTBLOCK,
                    'output-body-db'        => <<<'END_OF_TEXTBLOCK'
                            <p>Come mostrare un "messaggio" con 'JS' - </p>
                        END_OF_TEXTBLOCK,
                    'output-body-page'      => <<<'END_OF_TEXTBLOCK'
                            <p>Come mostrare un "messaggio" con 'JS' - </p>
                        END_OF_TEXTBLOCK,
                    'output-spotlight-id'   => null
                ]
            ],
            /* TEMPLATE
            [
                [
                    'input-title'           => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'input-body'            => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'output-title-db'       => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'output-title-page'     => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'output-abstract-db'    => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'output-abstract-page'  => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'output-body-db'        => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'output-body-page'      => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'output-spotlight-id'   => null
                ]
            ],
            */
        ];
    }


    #[DataProvider('articleEditorTestProvider')]
    public function testArticleEditorTitle(array $arrTestData)
    {
        $editor =
            static::buildEditor()
                ->setTitle($arrTestData['input-title'])
                ->setBody($arrTestData['input-body']);

        $entityTitle = $editor->getEntity()->getTitle();
        $editorTitle = $editor->getTitle();

        // title
        $this->assertNoEntities($entityTitle);
        $this->assertEquals( trim($arrTestData['output-title-db']), $entityTitle );
        $this->assertNoLegacyEntities($editorTitle);
        $this->assertEquals( trim($arrTestData['output-title-page']), $editorTitle);
    }


    #[DataProvider('articleEditorTestProvider')]
    public function testArticleEditorAbstract(array $arrTestData)
    {
        $editor =
            static::buildEditor()
                ->setTitle($arrTestData['input-title'])
                ->setBody($arrTestData['input-body']);

        $entityAbstract = $editor->getEntity()->getAbstract();
        $editorAbstract = $editor->getAbstract();

        $this->assertNoLegacyEntities($entityAbstract);
        $this->assertEquals( trim($arrTestData['output-abstract-db']), $entityAbstract);
        $this->assertNoLegacyEntities($editorAbstract);
        $this->assertEquals( trim($arrTestData['output-abstract-page']), $editorAbstract);
    }


    #[DataProvider('articleEditorTestProvider')]
    public function testArticleEditorBody(array $arrTestData)
    {
        $editor =
            static::buildEditor()
                ->setTitle($arrTestData['input-title'])
                ->setBody($arrTestData['input-body']);

        $entityBody = $editor->getEntity()->getBody();
        $editorBody = $editor->getBodyForDisplay();

        // body
        $this->assertNoLegacyEntities($entityBody);
        $this->assertEquals( trim($arrTestData['output-body-db']), $entityBody);
        $this->assertNoLegacyEntities($editorBody);
        $this->assertEquals( trim($arrTestData['output-body-page']), $editorBody);
    }


    #[DataProvider('articleEditorTestProvider')]
    public function testArticleEditorSpotlight(array $arrTestData)
    {
        $editor =
            static::buildEditor()
                ->setTitle($arrTestData['input-title'])
                ->setBody($arrTestData['input-body']);

        $entitySpotlightId = $editor->getEntity()->getSpotlight()?->getId();
        $editorSpotlightId = $editor->getSpotlight()?->getId();

        $this->assertEquals($arrTestData['output-spotlight-id'], $entitySpotlightId);
        $this->assertEquals($arrTestData['output-spotlight-id'], $editorSpotlightId);
    }
}
