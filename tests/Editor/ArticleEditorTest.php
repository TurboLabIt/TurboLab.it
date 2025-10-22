<?php
namespace App\Tests\Editor;

use App\Service\Cms\ArticleEditor;
use App\Service\Factory;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;


class ArticleEditorTest extends BaseT
{
    protected static function buildEditor() : ArticleEditor
    {
        return static::getService(Factory::class)->createArticleEditor();
    }


    public static function articleEditorTestProvider() : array
    {
        return [
            [
                [
                    'test-id'               => 'test-aaa',
                    'input-title'           => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un “messaggio” con ‘JS’ – <script>alert("bòòm");</script>
                        END_OF_TEXTBLOCK,
                    'input-body'            => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un “messaggio” con ‘JS’ – <script>alert("bòòm");</script>
                        END_OF_TEXTBLOCK,
                    'title-stored'          => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' - <script>alert("bòòm");</script>
                        END_OF_TEXTBLOCK,
                    'title-output'          => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' - &lt;script&gt;alert("bòòm");&lt;/script&gt;
                        END_OF_TEXTBLOCK,
                    'abstract-stored'       => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' -
                        END_OF_TEXTBLOCK,
                    'abstract-output'       => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' -
                        END_OF_TEXTBLOCK,
                    'body-stored'           => <<<'END_OF_TEXTBLOCK'
                        <p>Come mostrare un "messaggio" con 'JS' - </p>
                        END_OF_TEXTBLOCK,
                    'body-output'           => <<<'END_OF_TEXTBLOCK'
                        <p>Come mostrare un "messaggio" con 'JS' - </p>
                        END_OF_TEXTBLOCK,
                    'spotlight-id'          => null
                ]
            ],
            [
                [
                    'test-id'               => 'test-bbb',
                    'input-title'           => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un “messaggio&rdquo; con &lsquo;JS’ – <script>alert("b&ograve;òm");</script>
                        END_OF_TEXTBLOCK,
                    'input-body'            => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un “messaggio&rdquo; con &lsquo;JS’ – <script>alert("b&ograve;òm");</script>
                        END_OF_TEXTBLOCK,
                    'title-stored'          => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' - <script>alert("bòòm");</script>
                        END_OF_TEXTBLOCK,
                    'title-output'          => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' - &lt;script&gt;alert("bòòm");&lt;/script&gt;
                        END_OF_TEXTBLOCK,
                    'abstract-stored'       => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' -
                        END_OF_TEXTBLOCK,
                    'abstract-output'       => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' -
                        END_OF_TEXTBLOCK,
                    'body-stored'           => <<<'END_OF_TEXTBLOCK'
                        <p>Come mostrare un "messaggio" con 'JS' - </p>
                        END_OF_TEXTBLOCK,
                    'body-output'           => <<<'END_OF_TEXTBLOCK'
                        <p>Come mostrare un "messaggio" con 'JS' - </p>
                        END_OF_TEXTBLOCK,
                    'spotlight-id'          => null
                ]
            ],
            [
                [
                    'test-id'               => 'test-ccc',
                    'input-title'           => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' - <script>alert("bòòm");</script>
                        END_OF_TEXTBLOCK,
                    'input-body'            => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' - <script>alert("bòòm");</script>
                        END_OF_TEXTBLOCK,
                    'title-stored'          => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' - <script>alert("bòòm");</script>
                        END_OF_TEXTBLOCK,
                    'title-output'          => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' - &lt;script&gt;alert("bòòm");&lt;/script&gt;
                        END_OF_TEXTBLOCK,
                    'abstract-stored'       => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' -
                        END_OF_TEXTBLOCK,
                    'abstract-output'       => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' -
                        END_OF_TEXTBLOCK,
                    'body-stored'           => <<<'END_OF_TEXTBLOCK'
                        <p>Come mostrare un "messaggio" con 'JS' - </p>
                        END_OF_TEXTBLOCK,
                    'body-output'           => <<<'END_OF_TEXTBLOCK'
                        <p>Come mostrare un "messaggio" con 'JS' - </p>
                        END_OF_TEXTBLOCK,
                    'spotlight-id'          => null
                ]
            ],
            [
                [
                    'test-id'               => 'test-ddd',
                    'input-title'           => <<<'END_OF_TEXTBLOCK'
                        O&amp;O AppBuster rimuove e reinstalla le app da Windows 10 e 11
                        END_OF_TEXTBLOCK,
                    'input-body'            => static::getTestAssetContent('article-oo-input-body.html'),
                    'title-stored'          => <<<'END_OF_TEXTBLOCK'
                        O&O AppBuster rimuove e reinstalla le app da Windows 10 e 11
                        END_OF_TEXTBLOCK,
                    'title-output'          => <<<'END_OF_TEXTBLOCK'
                        O&amp;O AppBuster rimuove e reinstalla le app da Windows 10 e 11
                        END_OF_TEXTBLOCK,
                    'abstract-stored'       => <<<'END_OF_TEXTBLOCK'
                        Le app presenti nelle versioni più recenti di Windows sono sempre di più, sono spesso criticate, apprezzate, non utilizzate (dai tradizionalisti come me), in ogni caso ormai fanno parte del sistema operativo e se proprio ne vogliamo/possiamo farne a meno, dobbiamo procedere con la loro rimozione per liberare spazio sul disco fisso. Quando la cosa va a ripetersi ad ogni reinstallazione, o grosso aggiornamento periodico, diventa necessario trovare un automatismo che ci aiuti in questa operazione. Avevamo visto alcune modalità di rimozione in un precedente articolo, oggi conosceremo O&amp;O AppBuster.
                        END_OF_TEXTBLOCK,
                    'abstract-output'       => <<<'END_OF_TEXTBLOCK'
                        Le app presenti nelle versioni più recenti di Windows sono sempre di più, sono spesso criticate, apprezzate, non utilizzate (dai tradizionalisti come me), in ogni caso ormai fanno parte del sistema operativo e se proprio ne vogliamo/possiamo farne a meno, dobbiamo procedere con la loro rimozione per liberare spazio sul disco fisso. Quando la cosa va a ripetersi ad ogni reinstallazione, o grosso aggiornamento periodico, diventa necessario trovare un automatismo che ci aiuti in questa operazione. Avevamo visto alcune modalità di rimozione in un precedente articolo, oggi conosceremo O&amp;O AppBuster.
                        END_OF_TEXTBLOCK,
                    'body-stored'           => static::getTestAssetContent('article-oo-body-stored.html'),
                    'body-output'           => static::getTestAssetContent('article-oo-body-output.html'),
                    'spotlight-id'          => 22106
                ]
            ],
            [
                [
                    'test-id'               => 'test-eee',
                    'input-title'           => static::ARTICLE_QUALITY_TEST_STORED_TITLE,
                    'input-body'            => static::getTestAssetContent('article-quality-test-input-body.html'),
                    'title-stored'          => static::ARTICLE_QUALITY_TEST_STORED_TITLE,
                    'title-output'          => static::ARTICLE_QUALITY_TEST_OUTPUT_TITLE,
                    'abstract-stored'       => <<<'END_OF_TEXTBLOCK'
                        Questo è un articolo <em>di prova 🧪</em>, utilizzato dai test automatici per svolgere un "collaudo" dell'impianto &amp; verificare che il sistema funzioni correttamente. Questa serie di caratteri va gestita con particolare attenzione: <code>@ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine</code> 🫆
                        END_OF_TEXTBLOCK,
                    'abstract-output'       => <<<'END_OF_TEXTBLOCK'
                        Questo è un articolo <em>di prova 🧪</em>, utilizzato dai test automatici per svolgere un "collaudo" dell'impianto &amp; verificare che il sistema funzioni correttamente. Questa serie di caratteri va gestita con particolare attenzione: <code>@ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine</code> 🫆
                        END_OF_TEXTBLOCK,
                    'body-stored'           => static::getTestAssetContent('article-quality-test-body-stored.html'),
                    'body-output'           => static::getTestAssetContent('article-quality-test-body-output.html'),
                    'spotlight-id'          => 9513
                ]
            ]
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
        $this->assertNoEntities($entityTitle);
        $this->assertEquals( trim($arrTestData['title-stored']), $entityTitle );

        $editorTitle = $editor->getTitle();
        $this->assertNoLegacyEntities($editorTitle);
        $this->assertEquals( trim($arrTestData['title-output']), $editorTitle);
    }


    #[DataProvider('articleEditorTestProvider')]
    public function testArticleEditorAbstract(array $arrTestData)
    {
        $editor =
            static::buildEditor()
                ->setTitle($arrTestData['input-title'])
                ->setBody($arrTestData['input-body']);

        $entityAbstract = $editor->getEntity()->getAbstract();
        $this->assertNoLegacyEntities($entityAbstract);
        $this->assertEquals( trim($arrTestData['abstract-stored']), $entityAbstract);

        $editorAbstract = $editor->getAbstract();
        $this->assertNoLegacyEntities($editorAbstract);
        $this->assertEquals( trim($arrTestData['abstract-output']), $editorAbstract);
    }


    #[DataProvider('articleEditorTestProvider')]
    public function testArticleEditorBody(array $arrTestData)
    {
        $editor =
            static::buildEditor()
                ->setTitle($arrTestData['input-title'])
                ->setBody($arrTestData['input-body']);

        $entityBody = $editor->getEntity()->getBody();
        $this->assertNoLegacyEntities($entityBody);
        $this->assertEquals( trim($arrTestData['body-stored']), $entityBody);

        $editorBody = $editor->getBodyForDisplay();
        $this->assertNoLegacyEntities($editorBody);
        $this->assertEquals( trim($arrTestData['body-output']), $editorBody);
    }


    #[DataProvider('articleEditorTestProvider')]
    public function testArticleEditorSpotlight(array $arrTestData)
    {
        $editor =
            static::buildEditor()
                ->setTitle($arrTestData['input-title'])
                ->setBody($arrTestData['input-body']);

        $entitySpotlightId = $editor->getEntity()->getSpotlight()?->getId();
        $this->assertEquals($arrTestData['spotlight-id'], $entitySpotlightId);

        $editorSpotlightId = $editor->getSpotlight()?->getId();
        $this->assertEquals($arrTestData['spotlight-id'], $editorSpotlightId);
    }
}
