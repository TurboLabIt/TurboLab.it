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
                    'test-id'               => 'test-aaa',
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
            [
                [
                    'test-id'               => 'test-bbb',
                    'input-title'           => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un “messaggio&rdquo; con &lsquo;JS’ – <script>alert("b&ograve;òm");</script>
                        END_OF_TEXTBLOCK,
                    'input-body'            => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un “messaggio&rdquo; con &lsquo;JS’ – <script>alert("b&ograve;òm");</script>
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
            [
                [
                    'test-id'               => 'test-ccc',
                    'input-title'           => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' - <script>alert("bòòm");</script>
                        END_OF_TEXTBLOCK,
                    'input-body'            => <<<'END_OF_TEXTBLOCK'
                        Come mostrare un "messaggio" con 'JS' - <script>alert("bòòm");</script>
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
            [
                [
                    'test-id'               => 'test-ddd',
                    'input-title'           => <<<'END_OF_TEXTBLOCK'
                        O&amp;O AppBuster rimuove e reinstalla le app da Windows 10 e 11
                        END_OF_TEXTBLOCK,
                    'input-body'            => static::getTestAssetContent('oo-body-input-from-browser.html'),
                    'output-title-db'       => <<<'END_OF_TEXTBLOCK'
                        O&O AppBuster rimuove e reinstalla le app da Windows 10 e 11
                        END_OF_TEXTBLOCK,
                    'output-title-page'     => <<<'END_OF_TEXTBLOCK'
                        O&amp;O AppBuster rimuove e reinstalla le app da Windows 10 e 11
                        END_OF_TEXTBLOCK,
                    'output-abstract-db'    => <<<'END_OF_TEXTBLOCK'
                        Le app presenti nelle versioni più recenti di Windows sono sempre di più, sono spesso criticate, apprezzate, non utilizzate (dai tradizionalisti come me), in ogni caso ormai fanno parte del sistema operativo e se proprio ne vogliamo/possiamo farne a meno, dobbiamo procedere con la loro rimozione per liberare spazio sul disco fisso. Quando la cosa va a ripetersi ad ogni reinstallazione, o grosso aggiornamento periodico, diventa necessario trovare un automatismo che ci aiuti in questa operazione. Avevamo visto alcune modalità di rimozione in un precedente articolo, oggi conosceremo O&amp;O AppBuster.
                        END_OF_TEXTBLOCK,
                    'output-abstract-page'  => <<<'END_OF_TEXTBLOCK'
                        Le app presenti nelle versioni più recenti di Windows sono sempre di più, sono spesso criticate, apprezzate, non utilizzate (dai tradizionalisti come me), in ogni caso ormai fanno parte del sistema operativo e se proprio ne vogliamo/possiamo farne a meno, dobbiamo procedere con la loro rimozione per liberare spazio sul disco fisso. Quando la cosa va a ripetersi ad ogni reinstallazione, o grosso aggiornamento periodico, diventa necessario trovare un automatismo che ci aiuti in questa operazione. Avevamo visto alcune modalità di rimozione in un precedente articolo, oggi conosceremo O&amp;O AppBuster.
                        END_OF_TEXTBLOCK,
                    'output-body-db'        => static::getTestAssetContent('oo-body-input-stored.html'),
                    'output-body-page'      => static::getTestAssetContent('oo-body-output.html'),
                    'output-spotlight-id'   => 22106
                ]
            ],
            [
                [
                    'test-id'               => 'test-eee',
                    'input-title'           => <<<'END_OF_TEXTBLOCK'
                        Come svolgere test automatici su TurboLab.it (verifica dell'impianto &amp; "collaudo") | @ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine
                        END_OF_TEXTBLOCK,
                    'input-body'            => static::getTestAssetContent('acceptance-test-body-input-from-browser.html'),
                    'output-title-db'       => <<<'END_OF_TEXTBLOCK'
                        Come svolgere test automatici su TurboLab.it (verifica dell'impianto & "collaudo") | @ & òàùèéì # § |!"£$%&/()=?^ < > "double-quoted" 'single quoted' \ / | » fine
                        END_OF_TEXTBLOCK,
                    'output-title-page'     => <<<'END_OF_TEXTBLOCK'
                        Come svolgere test automatici su TurboLab.it (verifica dell'impianto &amp; "collaudo") | @ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine
                        END_OF_TEXTBLOCK,
                    'output-abstract-db'    => <<<'END_OF_TEXTBLOCK'
                        Questo è un articolo <em>di prova</em>, utilizzato dai test automatici per svolgere un "collaudo" dell'impianto &amp; verificare che il sistema funzioni correttamente. Questa serie di caratteri va gestita con particolare attenzione: <code>@ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine</code>
                        END_OF_TEXTBLOCK,
                    'output-abstract-page'  => <<<'END_OF_TEXTBLOCK'
                        Questo è un articolo <em>di prova</em>, utilizzato dai test automatici per svolgere un "collaudo" dell'impianto &amp; verificare che il sistema funzioni correttamente. Questa serie di caratteri va gestita con particolare attenzione: <code>@ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine</code>
                        END_OF_TEXTBLOCK,
                    'output-body-db'        => static::getTestAssetContent('acceptance-test-body-input-stored.html'),
                    'output-body-page'      => static::getTestAssetContent('acceptance-test-body-output.html'),
                    'output-spotlight-id'   => 9513
                ]
            ],
            /* TEMPLATE
            [
                [
                    'test-id'               => 'test-ZZZZ',
                    'input-title'           => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'input-body'            => static::getTestAssetContent('XXXXXXXXXXXX-body-input-from-browser.html'),
                    'output-title-db'       => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'output-title-page'     => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'output-abstract-db'    => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'output-abstract-page'  => <<<'END_OF_TEXTBLOCK'

                        END_OF_TEXTBLOCK,
                    'output-body-db'        => static::getTestAssetContent('XXXXXXXXXXXX-body-input-stored.html'),
                    'output-body-page'      => static::getTestAssetContent('XXXXXXXXXXXX-body-output.html'),
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
