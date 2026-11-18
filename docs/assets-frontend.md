# [Gestione degli asset frontend](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/assets-frontend.md)

Questo documento tratta la gestione di JS, CSS e icone utilizzate dal tema.


## Asset "regolari" (Webpack Encore) - assets/ ➡ public/build/

1. sorgenti: [assets/](https://github.com/TurboLabIt/TurboLab.it/tree/main/assets)
2. build prodotta da Symfony Webpack Encore (config: [webpack.config.js](https://github.com/TurboLabIt/TurboLab.it/blob/main/webpack.config.js))
3. output: `public/build/`, serviti direttamente da lì (`try_files` di nginx)

Il comando per generare i file è:

- dev: [scripts/watch.sh](https://github.com/TurboLabIt/TurboLab.it/tree/main/scripts/watch.sh)
- staging e prod: [scripts/build.sh](https://github.com/TurboLabIt/TurboLab.it/tree/main/scripts/build.sh) - eseguita automaticamente durante il deploy

`assets/app.js` è l'entry caricato su **ogni pagina** (dal layout base). Le singole pagine hanno poi entrypoint dedicati (`home`, `article`, ...).

Struttura di assets/:

- `*.js` nella radice — i 10 entry point
- `js/` — moduli JS custom (jQuery + vanilla JS); include `js/ckeditor-plugins/` (i plugin CKEditor 5) e `js/forum/`
- `styles/` — CSS semplice; include il tema `styles/newspark/`, più `styles/forum/` e `styles/email/`
- `images/` — immagini sorgente, copiate in `public/build/images/` durante la build
- `dictionaries/` — `stopwords-it.txt` (elenco di stopword italiane)
- `test/` — fixture HTML per i test dell'editor (encoding/XSS, vedi `tests/Editor/`)
- `themes/2024 newspark/` — archivio originale del tema acquistato, conservato come riferimento


### Cooldown delle dipendenze: niente versioni con meno di 5 giorni

Per lasciare il tempo di scoprire una release compromessa, Yarn scarta le versioni pubblicate da meno di 5 giorni ([.yarnrc.yml](https://github.com/TurboLabIt/TurboLab.it/blob/main/.yarnrc.yml)). Il limite vale solo quando Yarn deve risolvere una versione: quanto è già in `yarn.lock` si installa senza controlli, quindi il deploy non ne risente.

Una modifica a `package.json` va sempre committata insieme a `yarn.lock`. Senza, il deploy deve risolvere la dipendenza e, se tutte le versioni compatibili hanno meno di 5 giorni, `yarn install` fallisce con `All versions satisfying ">=..." are quarantined`. Webstackup a quel punto ferma il build e `public/build/` resta intatta, ma il deploy prosegue e riavvia i servizi, perché il build gira con nginx fermo e interrompere tutto lascerebbe il sito offline. Il sito resta online con gli asset precedenti e il deploy termina in rosso, con exit code 1.

Su dev, `npm-check-updates` aggiorna le dipendenze dirette con lo stesso cooldown di 5 giorni (`NPM_CHECK_UPDATES_COOLDOWN`, default di webstackup). Subito dopo, [webstackup](https://github.com/TurboLabIt/webstackup/blob/master/script/node.js/webpack_script_begin.sh) cancella `yarn.lock` e Yarn risolve di nuovo tutte le dipendenze, sempre con il cooldown. `yarn install` non tocca le versioni già nel lock finché rientrano nel loro range: senza questo passaggio le dipendenze indirette restavano ferme alla versione con cui erano entrate nel lock, correzioni di sicurezza comprese, e a novembre 2026 Dependabot ne segnalava 41. È lo stesso meccanismo di `composer.lock`, che su dev viene cancellato da `scripts/cache-clear.sh`. A differenza di Composer, però, Yarn non scarta le versioni con vulnerabilità note mentre risolve: nei 5 giorni dopo una release di sicurezza l'install non fallisce, installa la versione precedente e la correzione arriva con il primo build dopo il cooldown. Se l'install fallisce per altri motivi, `git checkout yarn.lock` ripristina il lock precedente.


### Build fallito: il sito resta sugli asset precedenti

Un `yarn build` di produzione fallito lascia `public/build/` com'era, `manifest.json` compreso: il sito continua a servire gli asset del build precedente. Durante il deploy, come per un `yarn install` fallito, il deploy arriva comunque in fondo, riavvia i servizi e termina in rosso, con exit code 1.

In produzione webpack non scrive nulla se il build ha errori, ma Encore di default scrive `manifest.json` direttamente su disco anche in quel caso. Il manifest finiva così per puntare a file mai generati, o perdeva `build/app.css`: `inline_css('build/app.css')` in `base.html.twig` lanciava un'eccezione e tutte le pagine rispondevano 500, come il 2026-07-15 (`File not found: public//build/app.a7906321.css`). Per questo [webpack.config.js](https://github.com/TurboLabIt/TurboLab.it/blob/main/webpack.config.js) disattiva quella scrittura in produzione (`writeToFileEmit`): il manifest lo scrive webpack insieme agli altri file, quindi solo se il build riesce. Su dev resta il default di Encore, che serve a `dev-server`.

Dopo un aggiornamento di Encore conviene ricontrollarlo: con un errore di sintassi in `assets/app.js`, `yarn build` deve uscire con 1 e lasciare `manifest.json` identico.


### article.css: agganciare al corpo dell'articolo, non al wrapper

`build/article.css` è caricato anche da pagine che **non** mostrano un articolo: `/scrivi` (`templates/article/editor/new.html.twig`) e `/info`. Entrambe usano il wrapper `.tli-article-main-content` della pagina articolo e ci mettono dentro tabelle Bootstrap (`.table`, `.table-sm`, `thead.table-primary`).

Le regole che stilizzano il contenuto redazionale (tabelle, heading, blocchi di codice, ...) vanno quindi agganciate a `#tli-article-body` — più `.ck.ck-content.ck-editor__editable` quando devono valere anche in editing — e **mai** a `.tli-article-main-content`. Agosto 2026 il CSS delle tabelle degli articoli era scritto sul wrapper: sovrascriveva Bootstrap e rendeva illeggibili gli elenchi articoli di `/scrivi` (bordo su ogni cella, header grigio al posto di `table-primary`, e `overflow-wrap: anywhere` che spezzava "Modificato" in "Mod ifica to").


### Iframe e immagini del corpo: solo i nostri

Nel corpo dell'articolo le regole valgono solo per gli elementi che produciamo noi, perché nel testo può finire anche altro (un annuncio, un'immagine esterna in un corpo storico):

- **iframe**: tutta larghezza e 16:9 solo per YouTube, cioè `src` che inizia per `https://www.youtube-nocookie.com/embed/`, sia in visualizzazione (`HtmlProcessorForDisplay`) sia in editing (plugin `youtube.js`). Un iframe di altro tipo mantiene le sue dimensioni: con la regola su ogni iframe, un annuncio 336x280 diventava largo quanto la colonna e alto 9/16.
- **zoom** (`js/zoom-images.js`): cursore `zoom-in` sulle immagini in `/immagini/` e lightbox solo per quelle presenti nella sua galleria. Il clic su qualunque altra immagine resta un clic normale: prima il gestore lo annullava sempre, e un'immagine fuori dalla galleria apriva la prima foto.


### Parole più lunghe della riga: vanno a capo, non allargano la pagina

Diversi titoli contengono una parola più lunga della riga: un codice d'errore (`MOZILLA_PKIX_ERROR_OCSP_RESPONSE_FOR_CERT_MISSING`, `KMODE_EXCEPTION_NOT_HANDLED`), il nome di un file o di un componente (`Windows.SecurityCenter.SecurityAppBroker`). Il trattino basso e il punto non sono punti in cui andare a capo, quindi su un telefono il titolo di un articolo così allargava la pagina fino a 1253 px invece di 390, e su desktop usciva dalla sua colonna finendo sopra quella laterale. Lo stesso succedeva alle card degli elenchi e al testo degli articoli (un link, un `<strong>`).

Per questo [assets/styles/app.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/app.css) mette `overflow-wrap: break-word` su `body`: una parola si spezza solo quando da sola non sta nella riga, quindi il resto del testo non cambia. Non `anywhere`, che cambia anche la larghezza minima del contenuto: nelle tabelle spezza parole che ci starebbero (vedi sopra, `/scrivi`).

`break-word` non basta a un elemento flex accanto a una miniatura: la sua larghezza minima resta quella della parola più lunga, e la riga esce lo stesso. Per questo `.gallery_item_content` (liste della homepage e del footer, in `app.css`) e il titolo dei video in homepage (`.tli-video-thumb > .flex-grow-1`, in [assets/styles/youtube-integration.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/youtube-integration.css)) hanno `min-width: 0`. Un nuovo layout flex con un titolo accanto a un'immagine ha bisogno della stessa regola.


### Logo: 360 px, sui telefoni stretti si restringe in proporzione

Il logo dell'intestazione e quello del footer (`.logo img`, `.footer-logo img` in [assets/styles/app.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/app.css)) sono larghi 360 px e alti 62, con `all: revert`: toglie la regola del tema `img { max-width: 100% }`, che con l'altezza fissa schiacciava il logo quando la colonna era più stretta (con lo zoom del browser). Sotto i 576 px, però, la colonna è larga quanto lo schermo meno 24 px, e su un telefono più stretto di 384 px il logo ne usciva: a 360 px, una larghezza comune su Android, ogni pagina del sito era larga 373 px e scorreva di lato.

Per questo, solo sotto i 576 px, il logo ha `max-width: 100%` con `height: auto` e `aspect-ratio: 360 / 62`, e si restringe in proporzione: 296 px a 320, 336 a 360, 351 a 375; da 390 in su resta di 360. ⚠️ `aspect-ratio` sembra superfluo, visto che l'`<img>` ha gli attributi `width` e `height`, ma `all: revert` scarta anche quelli: senza, il logo è alto 0 px finché l'immagine non arriva, e quando arriva sposta la pagina.

Sopra i 576 px resta di 360 px anche dove la sua colonna è più stretta (l'intestazione tra 992 e 1199 px, larga 296; il footer tra 768 e 991, larga 276): accanto non c'è niente e il logo non esce dallo schermo, mentre restringerlo lì lo cambierebbe su schermi comuni, come i 1024 px di un tablet in orizzontale.

Il forum ha un logo suo ([assets/styles/forum/logo.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/forum/logo.css)): un'immagine di sfondo larga 340 px (`.site_logo`) dentro il link `.logo`, che sui telefoni è un `inline-block` con 10 px di padding per lato. Il `max-width: 100%` di `.site_logo` non basta, perché il link prende la larghezza proprio dal logo: largo 360 px, allargava ogni pagina del forum (366 px a 360). Per questo anche il link ha `max-width: 100%`, con `box-sizing: border-box` (senza, il padding resterebbe fuori dal 100%), e il logo cambia solo sotto i 370 px: 290 px a 320, 330 a 360.


### Coppia di annunci 336x280: fine articolo, homepage ed elenchi

I due annunci prima dei tag ([templates/ads/clickio/square-pair.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/ads/clickio/square-pair.html.twig)) stanno fianco a fianco quando c'è posto e si impilano altrimenti (flex con `flex-wrap` di Bootstrap). Affiancati servono 688 px (2 × 336 più 16 di spazio), ma tra 1200 e 1399 px la colonna del testo è larga 676 px: per questo `.tli-ad-pair` ([assets/styles/article.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/article.css)) si allarga sui 30 px di padding laterale del riquadro dell'articolo (`.post-layout-top-content-3` del tema), come fa Newspark stesso con `.post-layout-top-content-2`. Solo da 576 px in su: sotto, il tema porta il padding a 15 px, e un margine di -30 px farebbe scorrere la pagina di lato.

Risultato: affiancati da 1200 px in su e tra 768 e 991 px (articolo a tutta larghezza), impilati tra 992 e 1199 px e sui telefoni.

Ognuno sta al centro della sua metà della riga: ogni posto (`.tli-ad-pair > div` in [assets/styles/ads.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/ads.css), globale perché la coppia è negli articoli, in homepage e negli elenchi) parte da 336 px e cresce quanto l'altro, e centra il contenitore che Clickio ci inserisce. Base di 336 px, non 0: con 0 i due posti starebbero sempre sulla stessa riga e non si impilerebbero mai; e nemmeno `auto`, che con un solo annuncio arrivato darebbe due metà diverse. In homepage le metà cadono così sotto le due colonne di card.

In homepage la stessa coppia segue tutte e quattro le card dello slider in alto, prima del mosaico ([templates/home/index.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/home/index.html.twig)). Lì sta sotto la prima schermata a tutte le risoluzioni comuni (comincia a 1229 px dall'alto a 1366, a 1157 px da 1536 in su): non spinge giù le notizie, e il suo arrivo in ritardo non sposta nulla di visibile al caricamento. Tra le due righe di card sarebbe entrata nella prima schermata di un monitor 1920x1080. La colonna principale è abbastanza larga senza allargarsi sul padding (736 px tra 1200 e 1399, 856 da 1400, 696 tra 768 e 991; `article.css` in homepage non c'è), quindi affiancati e impilati nelle stesse fasce dell'articolo. La colonna di destra finisce prima di quella principale: a 1366 lo spazio vuoto sotto di lei passa da circa 690 a circa 990 px.

Negli elenchi di articoli ([templates/archive/index.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/archive/index.html.twig): tag, news, pagine successive della homepage, autori, viste) la griglia procede a cicli di dieci card (due grandi, due righe da tre, due grandi), e la coppia occupa una riga intera (`col-12`), due volte per pagina al massimo, dopo le prime due righe: dopo le due card grandi (la 2ª card) e dopo la prima riga da tre (la 5ª). Tutte e due in cima per scelta di Zane, pur sapendo che la prima entra nella prima schermata a 1920x1080 (da 912 px, su circa 970 visibili) e su un tablet verticale 820x1180 (da 944 px, su circa 1100), dove gli annunci che arrivano in ritardo spostano in vista la seconda riga di card. Alle altre risoluzioni comuni la prima comincia sotto la prima schermata: tra 912 e 1156 px dall'alto su desktop e tablet, tra 1500 e 2000 px circa sui telefoni. La seconda comincia tra 1770 e 2240 px su desktop e tablet, oltre i 3700 sui telefoni: sui desktop le separa una sola riga di card, circa 630 px, quindi scorrendo se ne vedono parti di tutte e due insieme. Il contenitore a tutta larghezza basta sempre (1296 px da 1400, 1116 tra 1200 e 1399, 936 tra 992 e 1199, 696 tra 768 e 991): affiancati da 768 px in su, impilati sui telefoni.

Ogni coppia compare solo se dopo di lei restano almeno due card (`loop.revindex0 >= 2`), per via di uno dei Better Ads Standards: con annunci oltre il 30% dell'altezza della pagina mobile, Chrome può bloccare gli annunci dell'intero sito. Su un telefono, contando lo sticky in basso, un elenco di due card chiuso dalla coppia è per il 37% annunci, uno di tre per il 30%, uno di quattro tra il 22 e il 26%. Per lo stesso motivo non è più fitta di così: su un telefono ogni coppia è un blocco di quasi 600 px.


### Evidenziazione del codice (highlight.js)

I blocchi `<pre><code class="language-*">` degli articoli sono evidenziati client-side da highlight.js, inserito nell'entry `article`: core + sole lingue di `HtmlProcessorForStorage::ALLOWED_CODE_LANGUAGES`, mai autodetection — un blocco senza classe resta com'è. Il modulo è `js/article-code-highlight.js`; il tema (`github.css`) porta solo i colori dei token, perché sfondo e padding del blocco restano quelli di `article.css` (specificità maggiore). In editing non si evidenzia nulla: il div è di CKEditor.


## Asset del tema - public/assets/

La cartella [public/assets/](https://github.com/TurboLabIt/TurboLab.it/tree/main/public/assets) contiene alcune risorse del tema Newspark che non possono essere gestite via Webpack. Sono caricate con `<script>` diretti in fondo a `base.html.twig`:

````html
<script src="/assets/js/stellarnav.min.js"></script>
<script src="/assets/js/main.js"></script>
````


### main.js

JS principale (custom) del tema Newspark.


### StellarNav

Menu di navigazione responsive ([GitHub](https://github.com/vinnymoreira/stellarnav)). È composto da:

- JS: `public/assets/js/stellarnav.min.js`: originale da GitHub
- CSS: `assets/styles/newspark/stellarnav.css`: modificato da Newspark

Nel CSS è indicata in uso la versione `2.5.0`; non ci sono altri riferimenti di versione sul sito.

Non è gestibile via Yarn perché il repo upstream è privo di `package.json`:

````shell
yarn add stellarnav@https://github.com/vinnymoreira/stellarnav.git
````

> Error: Manifest not found

Per questo motivo il JS è dunque quello fornito da Newspark e resta versionato manualmente in `public/assets/`.


## Barra "Condividi sui social" e icone social generate

La barra di condivisione degli articoli è 100% first-party: nessun servizio esterno (ShareThis è stato rimosso), quindi visibile anche con gli ad-blocker attivi. È composta da:

- template: [templates/parts/social-share.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/parts/social-share.html.twig)
- JS: [assets/js/social-share.js](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/js/social-share.js) — gestisce l'espansione dei pulsanti, con logiche adeguate per prevenire il CLS
- CSS: [assets/styles/social-share.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/social-share.css)

Le icone (disco nel colore del brand + glifo bianco) non sono file committati, ma sono generati da [assets/build-social-icons.mjs](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/build-social-icons.mjs) e scritti in `assets/images/social-icons/` (gitignored), da cui Encore le copia in `public/build/` con hash. `yarn build:icons` è agganciato automaticamente a `dev`, `watch` e `build`. Le stesse icone servono anche la sezione "Seguici" ([FrontendHelper](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/FrontendHelper.php)) e il player YouTube della home.

I glifi provengono da tre pacchetti Yarn, perché nessuno da solo copre tutto:

- **simple-icons**: fonte principale (glifi e colori ufficiali dei brand), ma non include più i marchi Microsoft e Slack (rimossi per questioni di tutela dei marchi) e non ha un'icona email generica
- **Font Awesome**: glifi "positivi" dove il logo simple-icons è a spazio negativo (facebook, linkedin e reddit renderebbero invertiti su disco colorato), più slack e la busta email
- **bootstrap-icons**: solo `microsoft-teams`

Per aggiungere un canale di condivisione, è necessario agire in due punti:

1. una riga nella tabella `ICONS` del generatore
2. una riga in `ShareChannels` del template (il colore va allineato a mano in entrambi i punti).


## Altre immagini (loghi, icone, forum, contenuti, ...)

🔗 Vedi: [Gestione delle immagini](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md)
