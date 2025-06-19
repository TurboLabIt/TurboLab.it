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


    public static function titlesProvider() : array
    {
        return [
            [
                'input' => 'Come mostrare un “messaggio” con ‘JS’ – <script>alert("bòòm");</script>',
                'output'=> 'Come mostrare un "messaggio" con \'JS\' - &lt;script&gt;alert("bòòm");&lt;/script&gt;'
            ],
            [
                'input' => 'Come mostrare un “messaggio&rdquo; con &lsquo;JS’ – <script>alert("b&ograve;òm");</script>',
                'output'=> 'Come mostrare un "messaggio" con \'JS\' - &lt;script&gt;alert("bòòm");&lt;/script&gt;'
            ],
            [
                'input' => 'Come mostrare un "messaggio" con \'JS\' - <script>alert("bòòm");</script>',
                'output'=> 'Come mostrare un "messaggio" con \'JS\' - &lt;script&gt;alert("bòòm");&lt;/script&gt;'
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
                    <p>Come mostrare un “messaggio&rdquo; con &lsquo;JS’ – &lt;script&gt;alert(&quot;bòòm&quot;);&lt;/script&gt;<img src="https://turbolab.it/immagini/med/2/come-svolgere-test-automatici-9513.avif" alt="some alt text"></p>
                    <p><img src="https://example.com/immagini/med/2/come-svolgere-test-automatici-9999999.avif" alt="some alt text"></p>
                    <p>Perch&egrave; troppi &euro;!</p>
                    <script>alert("bòòm")</script>
                ',
                'output'=> '
                    <p>Come mostrare un "messaggio" con \'JS\' - &lt;script&gt;alert("bòòm");&lt;/script&gt;</p><p><img src="==###immagine::id::9513###=="></p><p>*** IMMAGINE ESTERNA RIMOSSA AUTOMATICAMENTE ***</p><p>Perchè troppi €!</p>
                ',
                'abstract' => 'Come mostrare un "messaggio" con \'JS\' - &lt;script&gt;alert("bòòm");&lt;/script&gt;',
                'spotlight'=> 9513
            ],
            [
                'input' => '
                    <p>Quando una applicazione smette di rispondere, e non riusciamo a chiuderla normalmente, non ci rimane altro da fare che aprire la Gestione attività (il Task Manager) di Windows, cercare il programma bloccato (e nella lunga lista di nomi presenti non è sempre immediato trovarlo) e terminarlo sperando di riuscire a chiuderlo senza dover riavviare il computer. Oppure, tramite una piccola modifica nelle Impostazioni di Windows, è possibile aggiungere l’opzione&nbsp;<code style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, &quot;Liberation Mono&quot;, &quot;Courier New&quot;, monospace; color: rgb(214, 51, 132);">Termina attività</code>&nbsp;al menu contestuale di tutti i programmi aperti nella barra delle applicazioni.</p><p><img src="https://turbolab.it/immagini/med/6/come-aggiungere-termina-attivita-menu-contestuale-barra-applicazioni-windows-11-26428.avif" title="Immagine 1 image1" alt="Immagine 1 image1" style="width: 676px;"></p><p>Non è detto che faccia “miracoli” con tutte le applicazioni che non rispondono, però è sicuramente più veloce, e comodo, che farlo da Gestione attività di Windows.</p><p><span style="font-weight: bolder;">Non è possibile chiudere in questo modo processi di sistema</span>, come Esplora risorse,&nbsp;<span style="font-weight: bolder;">per questi bisogna sempre usare Gestione attività</span>.</p><p>Per abilitare questa opzione bisogna andare nelle&nbsp;<code style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, &quot;Liberation Mono&quot;, &quot;Courier New&quot;, monospace; color: rgb(214, 51, 132);">Impostazioni</code>&nbsp;di Windows,&nbsp;<code style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, &quot;Liberation Mono&quot;, &quot;Courier New&quot;, monospace; color: rgb(214, 51, 132);">Sistema</code>,&nbsp;<code style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, &quot;Liberation Mono&quot;, &quot;Courier New&quot;, monospace; color: rgb(214, 51, 132);">Per sviluppatori</code>.</p><p><img src="https://turbolab.it/immagini/med/6/come-aggiungere-termina-attivita-menu-contestuale-barra-applicazioni-windows-11-26429.avif" title="Immagine 2 image2" alt="Immagine 2 image2" style="width: 192.953px;"></p><p>E qui si sposta lo switch su&nbsp;<code style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, &quot;Liberation Mono&quot;, &quot;Courier New&quot;, monospace; color: rgb(214, 51, 132);">Attivato</code>&nbsp;per&nbsp;<code style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, &quot;Liberation Mono&quot;, &quot;Courier New&quot;, monospace; color: rgb(214, 51, 132);">Termina attività</code>. La modifica è immediatamente attiva, non serve riavviare il computer.</p><p><img src="https://turbolab.it/immagini/med/6/come-aggiungere-termina-attivita-menu-contestuale-barra-applicazioni-windows-11-26430.avif" title="Immagine 3 image3" alt="Immagine 3 image3" style="width: 192.953px;"></p>
                ',
                'output' => '
                    <p>Quando una applicazione smette di rispondere, e non riusciamo a chiuderla normalmente, non ci rimane altro da fare che aprire la Gestione attività (il Task Manager) di Windows, cercare il programma bloccato (e nella lunga lista di nomi presenti non è sempre immediato trovarlo) e terminarlo sperando di riuscire a chiuderlo senza dover riavviare il computer. Oppure, tramite una piccola modifica nelle Impostazioni di Windows, è possibile aggiungere l\'opzione <code>Termina attività</code> al menu contestuale di tutti i programmi aperti nella barra delle applicazioni.</p><p><img src="==###immagine::id::26428###=="></p><p>Non è detto che faccia "miracoli" con tutte le applicazioni che non rispondono, però è sicuramente più veloce, e comodo, che farlo da Gestione attività di Windows.</p><p>Non è possibile chiudere in questo modo processi di sistema, come Esplora risorse, per questi bisogna sempre usare Gestione attività.</p><p>Per abilitare questa opzione bisogna andare nelle <code>Impostazioni</code> di Windows, <code>Sistema</code>, <code>Per sviluppatori</code>.</p><p><img src="==###immagine::id::26429###=="></p><p>E qui si sposta lo switch su <code>Attivato</code> per <code>Termina attività</code>. La modifica è immediatamente attiva, non serve riavviare il computer.</p><p><img src="==###immagine::id::26430###=="></p>
                ',
                'abstract' => '
                    Quando una applicazione smette di rispondere, e non riusciamo a chiuderla normalmente, non ci rimane altro da fare che aprire la Gestione attività (il Task Manager) di Windows, cercare il programma bloccato (e nella lunga lista di nomi presenti non è sempre immediato trovarlo) e terminarlo sperando di riuscire a chiuderlo senza dover riavviare il computer. Oppure, tramite una piccola modifica nelle Impostazioni di Windows, è possibile aggiungere l\'opzione <code>Termina attività</code> al menu contestuale di tutti i programmi aperti nella barra delle applicazioni.
                ',
                'spotlight'=> 26428
            ]
        ];
    }


    #[DataProvider('bodiesProvider')]
    public function testSetBody(string $input, string $output, string $abstract, int $spotlight)
    {
        $editor =
            static::buildEditor()
                ->setBody($input);

        $actualBody = $editor->getBody();

        $this->assertNoLegacyEntities($actualBody);
        $this->assertEquals( trim($output), $actualBody);
    }


    #[DataProvider('bodiesProvider')]
    public function testAbstract(string $input, string $output, string $abstract, int $spotlight)
    {
        $editor =
            static::buildEditor()
                ->setBody($input);

        $actualAbstract = $editor->getAbstract();

        $this->assertNoLegacyEntities($actualAbstract);
        $this->assertEquals( trim($abstract), $actualAbstract);
    }


    #[DataProvider('bodiesProvider')]
    public function testSpotlight(string $input, string $output, string $abstract, int $spotlight)
    {
        $editor =
            static::buildEditor()
                ->setBody($input);

        $actualSpotlightId = $editor->getSpotlight()?->getId();

        $this->assertEquals($spotlight, $actualSpotlightId);
    }
}
