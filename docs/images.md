# Gestione delle immagini

TurboLab.it deve salvare, gestire ed erogare diverse tipologie di immagini.


## 📃 Immagini del sito

**Logo, icone e altre immagini decorative**

Sono Git-versionate e salvate direttamente nella cartella [public/images](https://github.com/TurboLabIt/TurboLab.it/tree/main/public/images), quindi risultano direttamente accessibili via web.

I formati da utilizzare per aggiungere nuove immagini sono, nell'ordine di preferenza: SVG, AVIF trasparente, PNG trasparente. Il logo fa eccezione: deve rimanere in PNG trasparente per massimizzare la compatibilità.

Le immagini PNG da erogare ai client sono quelle con suffisso `-tiny`, processate con [tinypng.com](https://tinypng.com). Aggiungere sempre al repo sia l'immagine originale, sia quella processata.

Eventuali "originali" in formato PSD, PDN, ... dovrebbero essere caricati assieme alle rispettive grafiche per il web, utilizzando il medesimo nome ed estensione differente. Ad esempio:

1. [turbolab.it-2013-finale.pdn](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/images/logo/2013/turbolab.it-2013-finale.pdn): originale PDN
2. [turbolab.it-2013-finale-tiny.png](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/images/logo/2013/turbolab.it-2013-finale-tiny.png): PNG processata partendo dal PDN


## 🏟 Immagini di phpBB

**Pulsanti, smiley ecc propri della piattaforma phpBB**

Non sono Git-versionate, ma vengono prese dal pacchetto originale di phpBB e utilizzate *as is* (non ci sono particolari benefici a processarle di nuovo).

Ad ogni aggiornamento effettuato tramite [phpbb-upgrade.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/phpbb-upgrade.sh), le immagini di phpBB vengono sovrascritte con le versioni presenti nel nuovo pacchetto originale di phpBB.


## 🧔 Immagini caricate dagli utenti tramite il forum

**Avatar e altre immagini caricate dagli utenti sul forum**

Gli avatar sono salvati nella cartella `public/forum/images/avatars/upload/`. La gestione è totalmente in carico a phpBB.

Abbiamo scelto di disattivare la funzione di phpBB che consente agli utenti di caricare immagini direttamente sul nostro server per i seguenti motivi:

1. limitare il consumo di storage sul server
2. aumentare la sicurezza
3. prevenire contestazioni di copyright
4. ridurre spazio e tempo di backup

Le immagini caricate dagli utenti sono invece ospitate esternamente: [vedi #13](https://github.com/TurboLabIt/TurboLab.it/issues/13).


## 📸 Immagini degli articoli

**Screenshot, foto e altre grafiche caricate dagli autori negli articoli** | 🖼 [Immagini a campione qui](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-sample.md)

Questo è il tipo di immagine che richiede il maggior numero di elaborazioni. I file caricati dagli autori devono infatti essere validati (per evitare che qualcuno carichi un *.exe*), ridimensionati (in varie "taglie", adatte alla home page, ai listati, all'articolo o alla visualizzazione ingrandita di una singola immagine), minimizzati, dotati di watermark ecc.

Il flusso di lavoro è il seguente:

1. l'autore sceglie il file-immagine dal proprio PC e lo carica all'interno dell'articolo - l'autore deve caricare il file alla massima risoluzione di cui dispone, e senza watermark
2. il server di TurboLab.it salva il file originale ricevuto *as is*
3. tramite PHP, il server processa il file originale e ne deriva molteplici copie, ognuna con un set di dimensioni diverse
4. i file vengono ri-compressi in AVIF, che offre la migliore compressione ed [è supportato](https://caniuse.com/avif) da tutti i browser web moderni
5. viene applicato il *watermark*
5. i file così generati vengono salvati in un percorso raggiungibile pubblicamente via web
6. alla richiesta di visualizzazione di un'immagine da parte di un client, il server web eroga direttamente il file appropriato generato ai passi precedenti, senza più bisogno di attivare l'interprete PHP ad ogni accesso

Il processo di elaborazione via PHP avviene la prima volta che viene richiesta una determinata immagine (dall'autore stesso, presumibilmente).


### Trasferimento diretto delle immagini via Nginx

L'URL canonico delle immagini è `https://turbolab.it/immagini/med/2/nome-file-7654.avif`. Non appena Nginx riceve la richiesta, prova a servire direttamente il file che si trova nel percorso `public/immagini/med/2/nome-file-7654.avif`. Se lo trova, significa che il file è stato già elaborato via PHP e salvato in precedenza. Il file viene dunque restituito direttamente al client, senza attivare nuovamente PHP.

La cartella `public/immagini/`, in verità, non esiste. Si tratta piuttosto di un symlink (Git-versionato) che punta a `var/uploaded-asset/images/cache/`. Il reale percorso dal quale Nginx tenta di leggere l'immagine è dunque `var/uploaded-asset/images/cache/med/2/nome-file-7654.avif`.


### Elaborazione delle immagini via PHP

Se il file non esiste, è necessario:

1. processare "al volo" l'immagine originale caricata dall'autore (precedentemente salvata in `var/uploaded-asset/images/`)
2. salvarla nel percorso `var/uploaded-asset/images/cache/`
3. restituirla al client

Allo scopo, si attiva il file [ImageController.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/ImageController.php) (route: `app_image`).


### X-Sendfile

Per il trasferimento del file al client, lo script PHP usa [X-Sendfile](https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/):

1. l'applicazione PHP termina settando l'header `X-Accel-Redirect` valorizzato a `/xsend-uploaded-assets/images/cache/med/2/nome-file-7654.avif`
2. tale header fa scattare un *redirect interno* (invisibile al client) alla omonima location configurata in [nginx.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/nginx.conf)
3. il client riceve il file in modo trasparente

Questo libera il processo PHP dall'onere di trasferire il file, che torna ad essere totalmente a carico di Nginx (come è giusto che sia).
