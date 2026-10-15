# [Gestione delle immagini caricate negli articoli](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-articles.md)

Questo documento tratta, nello specifico, la gestione degli screenshot, foto e altre grafiche **caricate dagli autori negli articoli**.

🔗 Vedi anche: [Gestione delle immagini](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md)

---

Questo è il tipo di immagine che richiede il maggior numero di elaborazioni. I file caricati dagli autori devono infatti essere:

- **validati** per evitare che qualcuno carichi un *.exe* come immagine
- **ridimensionati** in varie "taglie", adatte alla home page, ai listati, all'articolo o alla visualizzazione ingrandita di una singola immagine
- **compressi** per ridurre il "peso" e rendere più veloce il download
- **timbrati** con watermark


## Flusso generale

Il flusso è il seguente:

1. l'autore sceglie il file-immagine dal proprio PC e lo carica all'interno dell'articolo. L'autore deve caricare il file alla massima risoluzione di cui dispone, senza applicare watermark, in uno dei formati supportati (elencati in [Entity/Image::getUploadFormats()](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Entity/Cms/Image.php)). La GIF è accettata solo in ingresso: il server ne usa **il primo fotogramma**, quindi una GIF animata viene mostrata come immagine statica. All'upload, le immagini con più di un fotogramma (GIF, WebP animate, APNG, sequenze AVIF) sono marcate con `image.animated = 1`, leggendo solo la struttura del file e mai i pixel ([Service/ImageFrames](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/ImageFrames.php)). Per GIF e WebP il flag decide l'URL dell'immagine (vedi [Immagini animate](#immagini-animate)); le immagini caricate prima del suo arrivo restano a `0`.
   Limiti (errore `422` all'upload, sempre senza decodificare i pixel, in [ImageEditor](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/ImageEditor.php)):
   - qualsiasi immagine: ogni fotogramma deve stare dentro i bordi dell'immagine
   - immagini statiche: massimo `Image::RESOLUTION_MAX` (12000px) per lato
   - immagini animate: massimo `Image::RESOLUTION_MAX_ANIMATED` (4096px) per lato, `Image::ANIMATED_FRAMES_MAX` (1860) fotogrammi e `Image::ANIMATED_PIXEL_BUDGET` (400 megapixel, cioè larghezza × altezza × fotogrammi). Per elaborarle si decodificano tutti i fotogrammi insieme, fuori dal `memory_limit` di PHP: circa 13 MB di RAM per megapixel, quindi circa 5,2 GB. Il limite regge solo con la *policy* di ImageMagick distribuita sui server, `config/custom/imagemagick-policy.xml` (8 GiB di RAM + 8 GiB mappati + 8 GiB su disco), che sostituisce quella di sistema (`/etc/ImageMagick-7/policy.xml`: la installa il deploy di webstackup in produzione e staging, `scripts/cache-clear.sh` in dev, prima del riavvio di PHP-FPM, l'unico momento in cui Imagick la legge): il codice non può alzarla, e con quella predefinita di Ubuntu (1 GiB + 2 GiB + 2 GiB) oltre i ~300 megapixel ImageMagick scarta fotogrammi invece di fallire
2. il server di TurboLab.it riceve il file e lo ri-salva (per motivi di sicurezza). Questo è considerato "**l'Originale**". A questo file non devono essere apportate altre modifiche
3. tramite PHP, il server processa il file originale e ne deriva molteplici copie, ognuna con un set di dimensioni diverse (elencate in [Service/Image::SIZE_DIMENSIONS](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/Image.php))
4. le copie elaborate vengono ri-compresse nel miglior formato grafico disponibile | attualmente: **AVIF**, che offre una compressione superiore ed [è supportato](https://caniuse.com/avif) da tutti i browser web moderni
5. viene applicato il *watermark* (il cui percorso è impostato in [Service/Image::WATERMARK_FILEPATH](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/Image.php))
6. i file così elaborati sono salvati in una cache su disco
7. ricevuta la richiesta di un file-immagine da parte di un client, il server web eroga direttamente il file generato ai passi precedenti, leggendolo dalla cache e senza più bisogno di attivare l'interprete PHP

Il processo di elaborazione dell'immagine via PHP, ovvero il flusso che termina con il salvataggio dell'immagine modificata nella cache sul disco, avviene dunque solo la prima volta che viene richiesta una determinata immagine (dall'autore stesso, presumibilmente). Allo scopo, si attiva il file [ImageController.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/ImageController.php).


## Immagini animate

Le GIF e le WebP animate (`image.animated = 1`) sono elaborate come le altre: al momento della richiesta, nella stessa cache, con gli stessi percorsi. Cambia solo il risultato, e l'URL: `Image::getUrl()` punta sempre alla versione WebP, quindi animata, a prescindere dai formati supportati dal client.

- richieste in formato **WebP**, in qualsiasi taglia, diventano una WebP animata ([Service/Image::buildAnimated()](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/Image.php)): Imagick ne legge tutti i fotogrammi, li ridimensiona, applica il watermark a ciascuno e li ricodifica, sia *lossless* sia *lossy*, tenendo il file più piccolo (il *lossless* vince sulle registrazioni dello schermo, il *lossy* sui filmati)
- in qualsiasi altro formato (AVIF, PNG, JPG) diventano un'immagine statica: il primo fotogramma, ricomposto sull'intera area dell'immagine come lo mostrerebbe il browser
- le APNG e le AVIF animate restano `animated = 1`, ma sono sempre elaborate come statiche: Imagick, su questi server, ne legge solo il primo fotogramma (le APNG richiedono ffmpeg, le sequenze AVIF una libheif più recente). Animate in AVIF non si possono produrre per lo stesso motivo
- una WebP "animata" con un solo fotogramma è a tutti gli effetti statica (`animated = 0`); GD però non sa aprirla, quindi anche lei passa da Imagick

Un'elaborazione animata può richiedere secondi e qualche GB di RAM, quindi due lock ([Symfony Lock](https://symfony.com/doc/current/components/lock.html), `LOCK_DSN`):

1. uno per variante (immagine + taglia + formato): richieste simultanee della stessa variante non ancora pronta attendono un'unica elaborazione
2. uno globale: una sola elaborazione animata alla volta, in tutto il server. Chi non ottiene il turno entro `Image::$animatedBuildWaitSeconds` (60 secondi) riceve un errore: un'eccezione generica (`RuntimeException`), senza nulla in cache; la richiesta successiva riproverà

Il file viene scritto a parte e rinominato solo a lavoro finito (Nginx serve qualsiasi file trovi nella cache, mai uno parziale). Il risultato non viene verificato: l'unica protezione contro i fotogrammi scartati in silenzio da ImageMagick quando la memoria non basta è il limite di 400 megapixel all'upload, che dipende dalla *policy* di ImageMagick (`config/custom/imagemagick-policy.xml`). Se un aggiornamento la sostituisse con quella predefinita, le animazioni più pesanti verrebbero servite troncate, senza errori. Il numero di fotogrammi non sarebbe comunque un buon controllo: l'encoder WebP fonde in uno i fotogrammi consecutivi identici (in *lossy* anche quasi identici), sommandone la durata.


## Trasferimento delle immagini via Nginx

L'URL delle immagini è:

- canonico: [/immagini/reg/1/something-1.avif](https://turbolab.it/immagini/reg/1/t-turbolab.it-1.avif)
- breve (short URL, redirect): [/immagini/1/reg/](https://turbolab.it/immagini/1/reg/)

Quando Nginx riceve la richiesta tramite l'URL canonico (ma non quello "breve"), prova a servire direttamente il file `public/immagini/med/2/7654.avif` ([c'è una rewrite di Nginx](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/nginx.conf) che trasforma `titolo-articolo-7654.avif` (presente nell'URL) in `7654.avif` (nome fisico del file su filesystem)):

- se lo trova: significa che il file è stato già elaborato via PHP e salvato in precedenza. Il file viene dunque restituito direttamente al client, senza attivare nuovamente PHP
- se non lo trova: l'immagine viene elaborata "al volo" tramite PHP partendo dall'originale, scritta nella cache su disco e restituita al client

I file serviti direttamente da Nginx sono riconoscibile perché NON è presente l'*header HTTP* `x-tli-xsent`, che è invece presente quando il file è stato gestito da PHP.


## Percorso delle immagini su filesystem

La cartella `public/immagini/`, in realtà, non esiste. Si tratta piuttosto di un symlink che punta a `var/uploaded-assets/images/cache/`. Tale file è creato da [scripts/deploy_moment_030.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/deploy_moment_030.sh) (eseguito al deploy e al cache-clear).

Di conseguenza, il reale percorso dal quale Nginx tenta di leggere l'URL `/immagini/med/2/titolo-articolo-7654.avif` è `var/uploaded-assets/images/cache/med/2/7654.avif`.

I percorsi su filesystem sono:

1. originali: `var/uploaded-assets/images/originals/<imageFolderMod>/<id>.<formato>`. Non vengono esposti direttamente via web
2. cache: `var/uploaded-assets/images/cache/<taglia>/<imageFolderMod>/<id>.<formato>` (dove `<taglia>` è `min`/`med`/`reg`/`max`)

`<imageFolderMod>` è un numero. Si tratta di una sotto-cartella, fisicamente presente su file system, derivata dall'ID dell'immagine. Serve a suddividere blandamente le immagini in sotto-cartelle, per evitare che ci siano *centomila* file in una sola cartella.

La cache contiene una copia per ogni combinazione taglia/formato effettivamente richiesta (AVIF per il web, PNG per `og:image` e newsletter, ...), e Nginx serve direttamente qualsiasi file vi trovi. Per questo, quando si cambia la posizione del watermark o si elimina l'immagine, [ImageEditor::clearCached()](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/ImageEditor.php) rimuove le copie di **tutte** le taglie in **tutti** i formati. Il deploy, invece, svuota l'intera cache ([scripts/cache-clear.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/cache-clear.sh)).


## X-Sendfile

Per il trasferimento del file al client, lo script PHP usa [X-Sendfile](https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/):

1. l'applicazione PHP [ImageController.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/ImageController.php) termina settando l'header `X-Accel-Redirect` valorizzato a `/xsend-uploaded-assets/images/cache/med/2/7654.avif`
2. tale header fa scattare un *redirect interno* (invisibile al client) alla omonima location configurata in [nginx.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/nginx.conf)
3. il client riceve il file in modo trasparente

Questo libera il processo PHP dall'onere di trasferire il file, che torna a essere totalmente a carico di Nginx (come è giusto che sia).

X-Sendfile viene attivato SOLO quando il file-immagine è stato processato da PHP, e non quando la risposta arriva direttamente da Nginx (la differenza è osservabile dall'header `x-tli-xsent`, descritto sopra).


## Percorsi legacy

- [/immagini/med/something-24255.img](https://turbolab.it/immagini/med/something-24255.img) [legacyNoFolderMod](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/ImageController.php)


## Esempi di immagini

🖼 [Immagini a campione qui](https://turbolab.it/1939)


## Cancellazione delle immagini

Le immagini possono essere eliminate istantaneamente cliccando sull'icona 🗑️, visualizzata sotto ogni immagine.

In alternativa, la gestione delle immagini caricate negli articoli viene svolta automaticamente da
[uploaded-assets-maintenance.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/uploaded-assets-maintenance.sh) (eseguito tramite [cron](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/cron)) tramite due comandi.

[ImagesToArticlesCommand](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/ImagesToArticlesCommand.php): scansiona il testo di ogni articolo, rileva quali immagini sono mostrate nel corpo dell'articolo e crea la relazione *articolo-immagine*. Questo fa sì che l'immagine sia mostrata (anche) nella gallery di quell'articolo. Se un'immagine precedentemente mostrata nell'articolo non viene più utilizzata, la stessa procedura elimina la relazione *articolo-immagine* dopo qualche mese dall'ultima modifica.

Le immagini non-relazionate ad alcun articolo sono chiamate "orfane" e mostrate in [immagini/orfane](https://turbolab.it/immagini/orfane). La pagina è linkata in [/scrivi](https://turbolab.it/scrivi).

[ImagesDeleteCommand](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/ImagesDeleteCommand.php): elimina fisicamente il file di tutte le immagini orfane dopo qualche mese che sono divenute tali.
