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
                    'input-body'            => <<<'END_OF_TEXTBLOCK'
                        <p>Le app presenti nelle versioni più recenti di Windows sono sempre di più, sono spesso criticate, apprezzate, non utilizzate (dai tradizionalisti come me), in ogni caso ormai fanno parte del sistema operativo e se proprio ne vogliamo/possiamo farne a meno, dobbiamo procedere con la loro rimozione per liberare spazio sul disco fisso. Quando la cosa va a ripetersi ad ogni reinstallazione, o grosso aggiornamento periodico, diventa necessario trovare un automatismo che ci aiuti in questa operazione. Avevamo visto alcune modalità di rimozione in un <a href="https://turbolab.it/manutenzione-156/come-disinstallare-reinstallare-app-integrate-windows-10-2004-720" title="Come disinstallare e reinstallare le app integrate in Windows 10 2004">precedente articolo</a>, oggi conosceremo <a href="https://www.oo-software.com/en/ooappbuster">O&amp;O AppBuster</a>.</p><p><img src="https://turbolab.it/immagini/med/amp-appbuster-rimuove-reinstalla-app-windows-10-11-22106.img" title="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" alt="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" loading="lazy"></p><p><ins>Revisionato articolo per adattarlo alle versioni più recenti del programma e del sistema operativo.</ins></p>

                        <p>Il programma è distribuito in versione portable, al suo avvio parte una ricerca delle App installate nel computer.</p>

                        <p>Prima di procedere con una disinstallazione “selvaggia” di tutte le App, controllate l’elenco e selezionate solo quelle che non vi interessano. Tra le App sono incluse anche la calcolatrice, notepad e paint che, bene o male, in molti utilizziamo.</p>

                        <p>Nello <code>Status</code> delle App è indicato Installed, se sono installate nel nostro account, o <code>Available,</code> che indica che non sono presenti nel nostro account, ma sono disponibili per essere reinstallate o per altre persone che si collegano al computer.</p>

                        <p>Cliccando sopra una singola App si vedono maggiori dettagli sul programma. </p><p><img src="https://turbolab.it/immagini/med/amp-appbuster-rimuove-reinstalla-app-windows-10-11-22107.img" title="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" alt="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" loading="lazy"></p><p>Si possono selezionare più app alla volta e avviare la disinstallazione con il pulsante <code>Remove</code> </p><p><img src="https://turbolab.it/immagini/med/amp-appbuster-rimuove-reinstalla-app-windows-10-11-22108.img" title="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" alt="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" loading="lazy"></p><p>Si può scegliere di rimuovere l’app solo per utente attualmente collegato al computer o per tutti quanti gli utenti. Se si rimuovono le app per tutti gli utenti si potrà poi andare a riscaricarle dallo <a href="https://apps.microsoft.com/store/apps?hl=it-it&amp;gl=it">Store Microsoft</a>. </p><p><img src="https://turbolab.it/immagini/med/amp-appbuster-rimuove-reinstalla-app-windows-10-11-22109.img" title="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" alt="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" loading="lazy"></p><p>Si raccomandano di creare un punto di ripristino. </p><p><img src="https://turbolab.it/immagini/med/amp-appbuster-rimuove-reinstalla-app-windows-10-11-22110.img" title="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" alt="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" loading="lazy"></p><p>E poi non rimane che attendere il termine della disinstallazione delle app scelte in precedenza. </p><p><img src="https://turbolab.it/immagini/med/amp-appbuster-rimuove-reinstalla-app-windows-10-11-22111.img" title="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" alt="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" loading="lazy"></p>

                        <h2>Reinstallazione</h2>

                        <p>Una volta disinstallata l’app, sul singolo utente collegato, questa diventa <code>Available</code> e la possiamo reinstallare, basta selezionarla e premere <code>Install</code>. Confermando poi quando richiesto. </p><p><img src="https://turbolab.it/immagini/med/amp-appbuster-rimuove-reinstalla-app-windows-10-11-22112.img" title="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" alt="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" loading="lazy"></p><p>Se volete rendervi conto di quanto spazio ho recuperato con la disinstallazione delle 31 App che avevo scelto vedete la differenza qui nell’immagine. </p><p><img src="https://turbolab.it/immagini/med/amp-appbuster-rimuove-reinstalla-app-windows-10-11-22113.img" title="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" alt="&amp;amp;amp; AppBuster rimuove reinstalla app Windows 10 11" loading="lazy"></p>
                        END_OF_TEXTBLOCK,
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
                    'output-body-db'        => <<<'END_OF_TEXTBLOCK'
                        <p>Le app presenti nelle versioni più recenti di Windows sono sempre di più, sono spesso criticate, apprezzate, non utilizzate (dai tradizionalisti come me), in ogni caso ormai fanno parte del sistema operativo e se proprio ne vogliamo/possiamo farne a meno, dobbiamo procedere con la loro rimozione per liberare spazio sul disco fisso. Quando la cosa va a ripetersi ad ogni reinstallazione, o grosso aggiornamento periodico, diventa necessario trovare un automatismo che ci aiuti in questa operazione. Avevamo visto alcune modalità di rimozione in un <a href="==###contenuto::id::720###==">precedente articolo</a>, oggi conosceremo <a href="https://www.oo-software.com/en/ooappbuster">O&amp;O AppBuster</a>.</p><p><img src="==###immagine::id::22106###=="></p><p><ins>Revisionato articolo per adattarlo alle versioni più recenti del programma e del sistema operativo.</ins></p><p>Il programma è distribuito in versione portable, al suo avvio parte una ricerca delle App installate nel computer.</p><p>Prima di procedere con una disinstallazione "selvaggia" di tutte le App, controllate l'elenco e selezionate solo quelle che non vi interessano. Tra le App sono incluse anche la calcolatrice, notepad e paint che, bene o male, in molti utilizziamo.</p><p>Nello <code>Status</code> delle App è indicato Installed, se sono installate nel nostro account, o <code>Available,</code> che indica che non sono presenti nel nostro account, ma sono disponibili per essere reinstallate o per altre persone che si collegano al computer.</p><p>Cliccando sopra una singola App si vedono maggiori dettagli sul programma. </p><p><img src="==###immagine::id::22107###=="></p><p>Si possono selezionare più app alla volta e avviare la disinstallazione con il pulsante <code>Remove</code> </p><p><img src="==###immagine::id::22108###=="></p><p>Si può scegliere di rimuovere l'app solo per utente attualmente collegato al computer o per tutti quanti gli utenti. Se si rimuovono le app per tutti gli utenti si potrà poi andare a riscaricarle dallo <a href="https://apps.microsoft.com/store/apps?hl=it-it&amp;gl=it">Store Microsoft</a>. </p><p><img src="==###immagine::id::22109###=="></p><p>Si raccomandano di creare un punto di ripristino. </p><p><img src="==###immagine::id::22110###=="></p><p>E poi non rimane che attendere il termine della disinstallazione delle app scelte in precedenza. </p><p><img src="==###immagine::id::22111###=="></p><h2>Reinstallazione</h2><p>Una volta disinstallata l'app, sul singolo utente collegato, questa diventa <code>Available</code> e la possiamo reinstallare, basta selezionarla e premere <code>Install</code>. Confermando poi quando richiesto. </p><p><img src="==###immagine::id::22112###=="></p><p>Se volete rendervi conto di quanto spazio ho recuperato con la disinstallazione delle 31 App che avevo scelto vedete la differenza qui nell'immagine. </p><p><img src="==###immagine::id::22113###=="></p>
                        END_OF_TEXTBLOCK,
                    'output-body-page'      => <<<'END_OF_TEXTBLOCK'
                        <p>Le app presenti nelle versioni più recenti di Windows sono sempre di più, sono spesso criticate, apprezzate, non utilizzate (dai tradizionalisti come me), in ogni caso ormai fanno parte del sistema operativo e se proprio ne vogliamo/possiamo farne a meno, dobbiamo procedere con la loro rimozione per liberare spazio sul disco fisso. Quando la cosa va a ripetersi ad ogni reinstallazione, o grosso aggiornamento periodico, diventa necessario trovare un automatismo che ci aiuti in questa operazione. Avevamo visto alcune modalità di rimozione in un <a href="https://dev0.turbolab.it/manutenzione-156/come-disinstallare-reinstallare-app-integrate-windows-10-2004-720" title="Come disinstallare e reinstallare le app integrate in Windows 10 2004">precedente articolo</a>, oggi conosceremo <a href="https://www.oo-software.com/en/ooappbuster">O&amp;O AppBuster</a>.</p><p><img src="https://dev0.turbolab.it/immagini/med/5/oo-appbuster-rimuove-reinstalla-app-windows-10-11-22106.avif" title="Immagine 1 image1" alt="Immagine 1 image1"></p><p><ins>Revisionato articolo per adattarlo alle versioni più recenti del programma e del sistema operativo.</ins></p><p>Il programma è distribuito in versione portable, al suo avvio parte una ricerca delle App installate nel computer.</p><p>Prima di procedere con una disinstallazione "selvaggia" di tutte le App, controllate l'elenco e selezionate solo quelle che non vi interessano. Tra le App sono incluse anche la calcolatrice, notepad e paint che, bene o male, in molti utilizziamo.</p><p>Nello <code>Status</code> delle App è indicato Installed, se sono installate nel nostro account, o <code>Available,</code> che indica che non sono presenti nel nostro account, ma sono disponibili per essere reinstallate o per altre persone che si collegano al computer.</p><p>Cliccando sopra una singola App si vedono maggiori dettagli sul programma. </p><p><img src="https://dev0.turbolab.it/immagini/med/5/oo-appbuster-rimuove-reinstalla-app-windows-10-11-22107.avif" title="Immagine 2 image2" alt="Immagine 2 image2"></p><p>Si possono selezionare più app alla volta e avviare la disinstallazione con il pulsante <code>Remove</code> </p><p><img src="https://dev0.turbolab.it/immagini/med/5/oo-appbuster-rimuove-reinstalla-app-windows-10-11-22108.avif" title="Immagine 3 image3" alt="Immagine 3 image3"></p><p>Si può scegliere di rimuovere l'app solo per utente attualmente collegato al computer o per tutti quanti gli utenti. Se si rimuovono le app per tutti gli utenti si potrà poi andare a riscaricarle dallo <a href="https://apps.microsoft.com/store/apps?hl=it-it&amp;gl=it">Store Microsoft</a>. </p><p><img src="https://dev0.turbolab.it/immagini/med/5/oo-appbuster-rimuove-reinstalla-app-windows-10-11-22109.avif" title="Immagine 4 image4" alt="Immagine 4 image4"></p><p>Si raccomandano di creare un punto di ripristino. </p><p><img src="https://dev0.turbolab.it/immagini/med/5/oo-appbuster-rimuove-reinstalla-app-windows-10-11-22110.avif" title="Immagine 5 image5" alt="Immagine 5 image5"></p><p>E poi non rimane che attendere il termine della disinstallazione delle app scelte in precedenza. </p><p><img src="https://dev0.turbolab.it/immagini/med/5/oo-appbuster-rimuove-reinstalla-app-windows-10-11-22111.avif" title="Immagine 6 image6" alt="Immagine 6 image6"></p><h2>Reinstallazione</h2><p>Una volta disinstallata l'app, sul singolo utente collegato, questa diventa <code>Available</code> e la possiamo reinstallare, basta selezionarla e premere <code>Install</code>. Confermando poi quando richiesto. </p><p><img src="https://dev0.turbolab.it/immagini/med/5/oo-appbuster-rimuove-reinstalla-app-windows-10-11-22112.avif" title="Immagine 7 image7" alt="Immagine 7 image7"></p><p>Se volete rendervi conto di quanto spazio ho recuperato con la disinstallazione delle 31 App che avevo scelto vedete la differenza qui nell'immagine. </p><p><img src="https://dev0.turbolab.it/immagini/med/5/oo-appbuster-rimuove-reinstalla-app-windows-10-11-22113.avif" title="Immagine 8 image8" alt="Immagine 8 image8"></p>
                        END_OF_TEXTBLOCK,
                    'output-spotlight-id'   => 22106
                ]
            ],
            [
                [
                    'test-id'               => 'test-eee',
                    'input-title'           => <<<'END_OF_TEXTBLOCK'
                        Come svolgere test automatici su TurboLab.it (verifica dell'impianto &amp; "collaudo") | @ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine
                        END_OF_TEXTBLOCK,
                    'input-body'            => <<<'END_OF_TEXTBLOCK'
                            <p>Questo è un articolo <em>di prova</em>, utilizzato dai <strong>test automatici</strong> per svolgere un "collaudo" dell'impianto &amp; verificare che il sistema funzioni correttamente. Questa serie di caratteri va gestita con particolare attenzione: <code>@ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine</code></p><p><iframe src="https://www.youtube-nocookie.com/embed/F1qLIws8H7E?rel=0" frameborder="0" width="100%" height="540px" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen="allowfullscreen"></iframe></p><p>Deve contenere:</p><ul>
                            <li>alcuni video da YouTube</li>
                            <li>tutti gli stili di formattazione previsti</li>
                            <li>alcuni link ad altri articoli</li>
                            <li>alcuni link a pagine di tag</li>
                            <li>alcuni link a file</li>
                            <li>alcuni link alle pagine degli autori</li>
                            <li>caratteri "delicati", ma <strong>NON inserire emoji su TLI1</strong> (corrompono tutto l'articolo)</li>
                            <li>tutte le immagini indicate in <a href="https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-sample.md">docs/images-sample.md</a></li>
                        </ul><h2>Stili di formattazione</h2><ol>
                            <li><strong>io sono grassetto</strong></li>
                            <li><em>io sono corsivo</em></li>
                            <li><code>io istruzioni</code></li>
                            <li><ins>io sono update</ins></li>
                        </ol><h2>Link ad articoli</h2><p><a href="https://dev0.turbolab.it/windows-10/grande-guida-windows-11-3282" title="La Grande Guida a Windows 11">Windows 11</a>, <a href="https://dev0.turbolab.it/aggiornamenti-software-282/ubuntu-22-04-cosa-nuovo-link-download-iso-versione-2022-video-3525" title="Ubuntu 22.04: cosa c'è di nuovo e link download ISO (versione 2022, video)">Ubuntu 22.04</a>.</p><p><strong>» Leggi:</strong> <a href="https://dev0.turbolab.it/windows-10/ms-dos-ad-oggi-storia-completa-windows-432" title="Da MS-DOS ad oggi: La storia completa di Windows">Da MS-DOS ad oggi: La storia completa di Windows</a></p><p></p><p><img src="https://dev0.turbolab.it/immagini/med/2/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-9513.avif" title="Immagine 1 pulsante start windows 95" alt="Immagine 1 pulsante start windows 95"></p><p></p><p><a href="https://dev0.turbolab.it/windows-10/scaricare-windows-11-dvd-iso-italiano-download-diretto-ufficiale-versione-24h2-aggiornamento-2024-video-3281" title="Scaricare Windows 11 DVD/ISO in italiano: download diretto ufficiale (versione 24H2, Aggiornamento 2024) (video)">Scaricare Windows 11 DVD/ISO in italiano: download diretto ufficiale (versione 23H2, Aggiornamento 2023) (video)</a></p><h2>Link a tag</h2><p><a href="https://dev0.turbolab.it/windows-10" title="windows: guide e articoli">tag windows</a>, <a href="https://dev0.turbolab.it/linux-27" title="linux: guide e articoli">tag linux</a>, <a href="https://dev0.turbolab.it/android-28" title="android: guide e articoli">tag android</a>.</p><h2>Link a file</h2><ol>
                            <li><a href="http://turbolab.it/scarica/1">Windows Bootable DVD Generator 2021</a></li>
                            <li><a href="https://turbolab.it/scarica/400">Estensioni video HEVC (appx 64 bit)</a></li>
                            <li><a href="http://turbolab.it/scarica/362">Batch configurazione macOS in VirtualBox</a></li>
                            <li><a href="https://turbolab.it/scarica/149">Microsoft Visual C++ 2019, VS16, 64 bit (installazione offline)</a></li>
                            <li><a href="https://turbolab.it/scarica/19">Rufus</a></li>
                            <li><a href="https://turbolab.it/scarica/117">Android Platform Tools (ADB e Fastboot) per Windows</a></li>
                        </ol><h2>link alle pagine degli autori</h2><ul>
                            <li><a href="https://turbolab.it/utenti/crazy.cat">crazy.cat</a></li>
                            <li><a href="https://turbolab.it/utenti/zane">Zane</a></li>
                            <li><a href="https://turbolab.it/utenti/system">System</a></li>
                        </ul><h2>Caratteri "delicati"</h2><p>@ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine</p><h2>Immagini da docs/images-sample.md</h2><p><strong>standard</strong></p><p></p><p><img src="https://dev0.turbolab.it/immagini/med/5/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-24206.avif" title="Immagine 2 Cypt2" alt="Immagine 2 Cypt2"></p><p></p><p><strong>cover (no WM)</strong></p><p></p><p><img src="https://dev0.turbolab.it/immagini/med/5/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-24010.avif" title="Immagine 3 controllo remoto smartphone android spotlight" alt="Immagine 3 controllo remoto smartphone android spotlight"></p><p></p><p><strong>strip (diagramma VPN)</strong></p><p></p><p><img src="https://dev0.turbolab.it/immagini/med/2/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-5735.avif" title="Immagine 4 diagramma VPN" alt="Immagine 4 diagramma VPN"></p><p></p><p><strong>strip (task manager)</strong></p><p></p><p><img src="https://dev0.turbolab.it/immagini/med/1/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-4003.avif" title="Immagine 5 core4" alt="Immagine 5 core4"></p><p></p><p><strong>verticale, no max</strong></p><p></p><p><img src="https://dev0.turbolab.it/immagini/med/4/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-18033.avif" title="Immagine 6 zShotVM_1603035520" alt="Immagine 6 zShotVM_1603035520"></p><p></p><p><strong>alpha PNG sfondo nero (HTC)</strong></p><p></p><p><img src="https://dev0.turbolab.it/immagini/med/1/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-324.avif" title="Immagine 7 HTC One S.png" alt="Immagine 7 HTC One S.png"></p><p></p><p><strong>alpha PNG sfondo nero (Apache)</strong></p><p></p><p><img src="https://dev0.turbolab.it/immagini/med/2/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-8923.avif" title="Immagine 8 apache logo" alt="Immagine 8 apache logo"></p><p></p><p><strong>alpha PNG 8 bit sfondo nero (PHP)</strong></p><p></p><p><img src="https://dev0.turbolab.it/immagini/med/1/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-3513.avif" title="Immagine 9 php logo" alt="Immagine 9 php logo"></p><p></p><p><strong>alpha PNG sfondo nero (Firefox/Android)</strong></p><p></p><p><img src="https://dev0.turbolab.it/immagini/med/2/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-7697.avif" title="Immagine 10 titolo2" alt="Immagine 10 titolo2"></p><p></p><p><iframe src="https://www.youtube-nocookie.com/embed/yeUyxjLhAxU?rel=0" frameborder="0" width="100%" height="540px" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen="allowfullscreen"></iframe></p>
                        END_OF_TEXTBLOCK,
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
                    'output-body-db'        => <<<'END_OF_TEXTBLOCK'
                        <p>Questo è un articolo <em>di prova</em>, utilizzato dai <strong>test automatici</strong> per svolgere un "collaudo" dell'impianto &amp; verificare che il sistema funzioni correttamente. Questa serie di caratteri va gestita con particolare attenzione: <code>@ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine</code></p><p>==###youtube::code::F1qLIws8H7E###==</p><p>Deve contenere:</p><ul>
                         <li>alcuni video da YouTube</li>
                         <li>tutti gli stili di formattazione previsti</li>
                         <li>alcuni link ad altri articoli</li>
                         <li>alcuni link a pagine di tag</li>
                         <li>alcuni link a file</li>
                         <li>alcuni link alle pagine degli autori</li>
                         <li>caratteri "delicati", ma <strong>NON inserire emoji su TLI1</strong> (corrompono tutto l'articolo)</li>
                         <li>tutte le immagini indicate in <a href="https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-sample.md">docs/images-sample.md</a></li>
                        </ul><h2>Stili di formattazione</h2><ol>
                         <li><strong>io sono grassetto</strong></li>
                         <li><em>io sono corsivo</em></li>
                         <li><code>io istruzioni</code></li>
                         <li><ins>io sono update</ins></li>
                        </ol><h2>Link ad articoli</h2><p><a href="==###contenuto::id::3282###==">Windows 11</a>, <a href="==###contenuto::id::3525###==">Ubuntu 22.04</a>.</p><p><strong>» Leggi:</strong> <a href="==###contenuto::id::432###==">Da MS-DOS ad oggi: La storia completa di Windows</a></p><p><img src="==###immagine::id::9513###=="></p><p><a href="==###contenuto::id::3281###==">Scaricare Windows 11 DVD/ISO in italiano: download diretto ufficiale (versione 23H2, Aggiornamento 2023) (video)</a></p><h2>Link a tag</h2><p><a href="==###tag::id::10###==">tag windows</a>, <a href="==###tag::id::27###==">tag linux</a>, <a href="==###tag::id::28###==">tag android</a>.</p><h2>Link a file</h2><ol>
                         <li><a href="http://turbolab.it/scarica/1">Windows Bootable DVD Generator 2021</a></li>
                         <li><a href="https://turbolab.it/scarica/400">Estensioni video HEVC (appx 64 bit)</a></li>
                         <li><a href="==###file::id::362###==">Batch configurazione macOS in VirtualBox</a></li>
                         <li><a href="==###file::id::149###==">Microsoft Visual C++ 2019, VS16, 64 bit (installazione offline)</a></li>
                         <li><a href="==###file::id::19###==">Rufus</a></li>
                         <li><a href="==###file::id::117###==">Android Platform Tools (ADB e Fastboot) per Windows</a></li>
                        </ol><h2>link alle pagine degli autori</h2><ul>
                         <li><a href="https://turbolab.it/utenti/crazy.cat">crazy.cat</a></li>
                         <li><a href="https://turbolab.it/utenti/zane">Zane</a></li>
                         <li><a href="https://turbolab.it/utenti/system">System</a></li>
                        </ul><h2>Caratteri "delicati"</h2><p>@ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine</p><h2>Immagini da docs/images-sample.md</h2><p><strong>standard</strong></p><p><img src="==###immagine::id::24206###=="></p><p><strong>cover (no WM)</strong></p><p><img src="==###immagine::id::24010###=="></p><p><strong>strip (diagramma VPN)</strong></p><p><img src="==###immagine::id::5735###=="></p><p><strong>strip (task manager)</strong></p><p><img src="==###immagine::id::4003###=="></p><p><strong>verticale, no max</strong></p><p><img src="==###immagine::id::18033###=="></p><p><strong>alpha PNG sfondo nero (HTC)</strong></p><p><img src="==###immagine::id::324###=="></p><p><strong>alpha PNG sfondo nero (Apache)</strong></p><p><img src="==###immagine::id::8923###=="></p><p><strong>alpha PNG 8 bit sfondo nero (PHP)</strong></p><p><img src="==###immagine::id::3513###=="></p><p><strong>alpha PNG sfondo nero (Firefox/Android)</strong></p><p><img src="==###immagine::id::7697###=="></p><p>==###youtube::code::yeUyxjLhAxU###==</p>
                        END_OF_TEXTBLOCK,
                    'output-body-page'      => <<<'END_OF_TEXTBLOCK'
                        <p>Questo è un articolo <em>di prova</em>, utilizzato dai <strong>test automatici</strong> per svolgere un "collaudo" dell'impianto &amp; verificare che il sistema funzioni correttamente. Questa serie di caratteri va gestita con particolare attenzione: <code>@ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine</code></p><p><iframe src="https://www.youtube-nocookie.com/embed/F1qLIws8H7E?rel=0" frameborder="0" width="100%" height="540px" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen="allowfullscreen"></iframe></p><p>Deve contenere:</p><ul>
                         <li>alcuni video da YouTube</li>
                         <li>tutti gli stili di formattazione previsti</li>
                         <li>alcuni link ad altri articoli</li>
                         <li>alcuni link a pagine di tag</li>
                         <li>alcuni link a file</li>
                         <li>alcuni link alle pagine degli autori</li>
                         <li>caratteri "delicati", ma <strong>NON inserire emoji su TLI1</strong> (corrompono tutto l'articolo)</li>
                         <li>tutte le immagini indicate in <a href="https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-sample.md">docs/images-sample.md</a></li>
                        </ul><h2>Stili di formattazione</h2><ol>
                         <li><strong>io sono grassetto</strong></li>
                         <li><em>io sono corsivo</em></li>
                         <li><code>io istruzioni</code></li>
                         <li><ins>io sono update</ins></li>
                        </ol><h2>Link ad articoli</h2><p><a href="https://dev0.turbolab.it/windows-10/grande-guida-windows-11-3282" title="La Grande Guida a Windows 11">Windows 11</a>, <a href="https://dev0.turbolab.it/aggiornamenti-software-282/ubuntu-22-04-cosa-nuovo-link-download-iso-versione-2022-video-3525" title="Ubuntu 22.04: cosa c'è di nuovo e link download ISO (versione 2022, video)">Ubuntu 22.04</a>.</p><p><strong>» Leggi:</strong> <a href="https://dev0.turbolab.it/windows-10/ms-dos-ad-oggi-storia-completa-windows-432" title="Da MS-DOS ad oggi: La storia completa di Windows">Da MS-DOS ad oggi: La storia completa di Windows</a></p><p><img src="https://dev0.turbolab.it/immagini/med/2/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-9513.avif" title="Immagine 1 pulsante start windows 95" alt="Immagine 1 pulsante start windows 95"></p><p><a href="https://dev0.turbolab.it/windows-10/scaricare-windows-11-dvd-iso-italiano-download-diretto-ufficiale-versione-24h2-aggiornamento-2024-video-3281" title="Scaricare Windows 11 DVD/ISO in italiano: download diretto ufficiale (versione 24H2, Aggiornamento 2024) (video)">Scaricare Windows 11 DVD/ISO in italiano: download diretto ufficiale (versione 23H2, Aggiornamento 2023) (video)</a></p><h2>Link a tag</h2><p><a href="https://dev0.turbolab.it/windows-10" title="windows: guide e articoli">tag windows</a>, <a href="https://dev0.turbolab.it/linux-27" title="linux: guide e articoli">tag linux</a>, <a href="https://dev0.turbolab.it/android-28" title="android: guide e articoli">tag android</a>.</p><h2>Link a file</h2><ol>
                         <li><a href="http://turbolab.it/scarica/1">Windows Bootable DVD Generator 2021</a></li>
                         <li><a href="https://turbolab.it/scarica/400">Estensioni video HEVC (appx 64 bit)</a></li>
                         <li><a href="https://dev0.turbolab.it/scarica/362" title="Scarica Batch configurazione macOS in VirtualBox">Batch configurazione macOS in VirtualBox</a></li>
                         <li><a href="https://dev0.turbolab.it/scarica/149" title="Scarica Microsoft Visual C++ 2022, 64 bit (installazione offline)">Microsoft Visual C++ 2019, VS16, 64 bit (installazione offline)</a></li>
                         <li><a href="https://dev0.turbolab.it/scarica/19" title="Scarica Rufus">Rufus</a></li>
                         <li><a href="https://dev0.turbolab.it/scarica/117" title="Scarica Android Platform Tools (ADB e Fastboot) per Windows">Android Platform Tools (ADB e Fastboot) per Windows</a></li>
                        </ol><h2>link alle pagine degli autori</h2><ul>
                         <li><a href="https://turbolab.it/utenti/crazy.cat">crazy.cat</a></li>
                         <li><a href="https://turbolab.it/utenti/zane">Zane</a></li>
                         <li><a href="https://turbolab.it/utenti/system">System</a></li>
                        </ul><h2>Caratteri "delicati"</h2><p>@ &amp; òàùèéì # § |!"£$%&amp;/()=?^ &lt; &gt; "double-quoted" 'single quoted' \ / | » fine</p><h2>Immagini da docs/images-sample.md</h2><p><strong>standard</strong></p><p><img src="https://dev0.turbolab.it/immagini/med/5/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-24206.avif" title="Immagine 2 Cypt2" alt="Immagine 2 Cypt2"></p><p><strong>cover (no WM)</strong></p><p><img src="https://dev0.turbolab.it/immagini/med/5/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-24010.avif" title="Immagine 3 controllo remoto smartphone android spotlight" alt="Immagine 3 controllo remoto smartphone android spotlight"></p><p><strong>strip (diagramma VPN)</strong></p><p><img src="https://dev0.turbolab.it/immagini/med/2/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-5735.avif" title="Immagine 4 diagramma VPN" alt="Immagine 4 diagramma VPN"></p><p><strong>strip (task manager)</strong></p><p><img src="https://dev0.turbolab.it/immagini/med/1/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-4003.avif" title="Immagine 5 core4" alt="Immagine 5 core4"></p><p><strong>verticale, no max</strong></p><p><img src="https://dev0.turbolab.it/immagini/med/4/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-18033.avif" title="Immagine 6 zShotVM_1603035520" alt="Immagine 6 zShotVM_1603035520"></p><p><strong>alpha PNG sfondo nero (HTC)</strong></p><p><img src="https://dev0.turbolab.it/immagini/med/1/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-324.avif" title="Immagine 7 HTC One S.png" alt="Immagine 7 HTC One S.png"></p><p><strong>alpha PNG sfondo nero (Apache)</strong></p><p><img src="https://dev0.turbolab.it/immagini/med/2/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-8923.avif" title="Immagine 8 apache logo" alt="Immagine 8 apache logo"></p><p><strong>alpha PNG 8 bit sfondo nero (PHP)</strong></p><p><img src="https://dev0.turbolab.it/immagini/med/1/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-3513.avif" title="Immagine 9 php logo" alt="Immagine 9 php logo"></p><p><strong>alpha PNG sfondo nero (Firefox/Android)</strong></p><p><img src="https://dev0.turbolab.it/immagini/med/2/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-double-quoted-single-quoted-fine-7697.avif" title="Immagine 10 titolo2" alt="Immagine 10 titolo2"></p><p><iframe src="https://www.youtube-nocookie.com/embed/yeUyxjLhAxU?rel=0" frameborder="0" width="100%" height="540px" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen="allowfullscreen"></iframe></p>
                        END_OF_TEXTBLOCK,
                    'output-spotlight-id'   => 9513
                ]
            ],
            /* TEMPLATE
            [
                [
                    'test-id'               => 'test-ZZZZ',
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
