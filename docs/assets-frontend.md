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

Le icone (disco nel colore del brand + glifo bianco) non sono file committati, ma sono generati da [scripts/build-social-icons.mjs](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/build-social-icons.mjs) e scritti in `assets/images/social-icons/` (gitignored), da cui Encore le copia in `public/build/` con hash. `yarn build:icons` è agganciato automaticamente a `dev`, `watch` e `build`. Le stesse icone servono anche la sezione "Seguici" ([FrontendHelper](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/FrontendHelper.php)) e il player YouTube della home.

I glifi provengono da tre pacchetti Yarn, perché nessuno da solo copre tutto:

- **simple-icons**: fonte principale (glifi e colori ufficiali dei brand), ma non include più i marchi Microsoft e Slack (rimossi per questioni di tutela dei marchi) e non ha un'icona email generica
- **Font Awesome**: glifi "positivi" dove il logo simple-icons è a spazio negativo (facebook, linkedin e reddit renderebbero invertiti su disco colorato), più slack e la busta email
- **bootstrap-icons**: solo `microsoft-teams`

Per aggiungere un canale di condivisione, è necessario agire in due punti:

1. una riga nella tabella `ICONS` del generatore
2. una riga in `ShareChannels` del template (il colore va allineato a mano in entrambi i punti).


## Altre immagini (loghi, icone, forum, contenuti, ...)

🔗 Vedi: [Gestione delle immagini](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md)
