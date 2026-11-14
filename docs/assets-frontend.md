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
