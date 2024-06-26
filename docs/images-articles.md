# [Gestione delle immagini caricate negli articoli](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-articles.md)

Questo documento tratta, nello specifico, la gestione degli screenshot, foto e altre grafiche caricate dagli autori negli articoli.

🔗 Vedi anche: [Gestione delle immagini](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md)

🖼 [Immagini a campione qui](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-sample.md)

---

Questo è il tipo di immagine che richiede il maggior numero di elaborazioni. I file caricati dagli autori devono infatti essere:

- **validati** per evitare che qualcuno carichi un *.exe* come immagine
- **ridimensionati** in varie "taglie", adatte alla home page, ai listati, all'articolo o alla visualizzazione ingrandita di una singola immagine
- **compressi** per ridurre il "peso" e rendere più veloce il download
- **timbrati** con watermark


## Flusso generale

Il flusso è il seguente:

1. l'autore sceglie il file-immagine dal proprio PC e lo carica all'interno dell'articolo. L'autore deve caricare il file alla massima risoluzione di cui dispone, senza applicare watermark, in uno dei formati supportati (elencati in [Entity/Image::getFormats()](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Entity/Cms/Image.php))
2. il server di TurboLab.it riceve il file ricevuto e lo ri-salva (per motivi di sicurezza). Questo è considerato "**l'Originale**" e, eccezion fatta per il ri-salvataggio, non vengono apportate altre modifiche
3. tramite PHP, il server processa il file originale e ne deriva molteplici copie, ognuna con un set di dimensioni diverse (elencate in [Service/Image::SIZE_DIMENSIONS](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/Image.php))
4. le copie elaborate vengono ri-compresse nel miglior formato grafico disponibile | attualmente: AVIF, che offre una compressione superiore ed [è supportato](https://caniuse.com/avif) da tutti i browser web moderni
5. viene applicato il *watermark* (il cui percorso è impostato in [Service/Image::WATERMARK_FILEPATH](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/Image.php))
5. i file così elaborati sono salvati in una cache su disco
6. ricevuta la richiesta di un file-immagine da parte di un client, il server web eroga direttamente il file generato ai passi precedenti, leggendolo dalla cache e senza più bisogno di attivare l'interprete PHP

Il processo di elaborazione dell'immagine via PHP, ovvero il flusso che termina con il salvataggio dell'immagine modificata nella cache sul disco, avviene dunque solo la prima volta che viene richiesta una determinata immagine (dall'autore stesso, presumibilmente). Allo scopo, si attiva il file [ImageController.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/ImageController.php).


### Trasferimento delle immagini via Nginx

L'URL delle immagini è:

- canonico: [https://turbolab.it/immagini/med/2/titolo-articolo-7654.avif](https://turbolab.it/immagini/med/2/titolo-articolo-7654.avif)
- breve: [https://turbolab.it/immagini/7654](https://turbolab.it/immagini/7654)

Quando Nginx riceve la richiesta tramite l'URL canonico (ma non quello "breve"), prova a servire direttamente il file `public/immagini/med/2/7654.avif` ([c'è una rewrite di Nginx](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/nginx.conf) che trasforma `titolo-articolo-7654.avif` (presente nell'URL) in `7654.avif` (nome fisico del file su filesystem)):

- se lo trova: significa che il file è stato già elaborato via PHP e salvato in precedenza. Il file viene dunque restituito direttamente al client, senza attivare nuovamente PHP
- se non lo trova: l'immagine viene elaborata "al volo" tramite PHP partendo dall'originale, scritta nella cache su disco e restituita al client

I file serviti direttamente da Nginx sono riconoscibile perché NON è presente l'*header HTTP* `x-tli-xsent`, che è invece presente quando il file è stato gestito da PHP.


### Percorso delle immagini su filesystem

La cartella `public/immagini/`, in realtà, non esiste. Si tratta piuttosto di un symlink (Git-versionato) che punta a `var/uploaded-asset/images/cache/`.

Il reale percorso dal quale Nginx tenta di leggere l'URL `/immagini/med/2/titolo-articolo-7654.avif` è dunque `var/uploaded-asset/images/cache/med/2/7654.avif`.

I percorsi su filesystem sono:

1. originali: `var/uploaded-assets/images/originals/<imageFolderMod>/<id>.<formato>`. Non vengono esposti direttamente via web
2. cache: `var/uploaded-assets/images/cache/med/<imageFolderMod>/<id>.<formato>`

`<imageFolderMod>` è un numero. Si tratta di una sotto-cartella, fisicamente presente su file system, derivata dall'ID dell'immagine. Serve a suddividere blandamente le immagini in sotto-cartelle, per evitare che ci siano *centomila* file in una sola cartella.


### X-Sendfile

Per il trasferimento del file al client, lo script PHP usa [X-Sendfile](https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/):

1. l'applicazione PHP [ImageController.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/ImageController.php) termina settando l'header `X-Accel-Redirect` valorizzato a `/xsend-uploaded-assets/images/cache/med/2/7654.avif`
2. tale header fa scattare un *redirect interno* (invisibile al client) alla omonima location configurata in [nginx.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/nginx.conf)
3. il client riceve il file in modo trasparente

Questo libera il processo PHP dall'onere di trasferire il file, che torna ad essere totalmente a carico di Nginx (come è giusto che sia).

X-Sendfile viene attivato SOLO quando il file-immagine è stato processato da PHP, e non quando la risposta arriva direttamente da Nginx. Per rendere manifesta la differenza, nei file serviti direttamente da Nginx NON è presente l'*header HTTP* `x-tli-xsent`, che è invece presente quando il file è stato gestito da PHP.
