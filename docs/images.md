# Gestione delle immagini

TurboLab.it deve salvare, gestire ed erogare diverse tipologie di immagini.


## Immagini del sito

Logo, icone e altre immagini decorative.

Sono Git-versionate e salvate direttamente nella cartella [public/images](https://github.com/TurboLabIt/TurboLab.it/tree/main/public/images), quindi risultano direttamente accessibili via web.

I formati da utilizzare per aggiungere nuove immagini sono, nell'ordine di preferenza: SVG, AVIF trasparente, PNG trasparente. Il logo fa eccezione: deve rimanere in PNG trasparente per massimizzare la compatibilità.

Le immagini PNG da erogare ai client sono quelle con suffisso `-tiny`, processate con [tinypng.com](https://tinypng.com). Aggiungere sempre al repo sia l'immagine originale, sia quella processata.

Eventuali "originali" in formato PSD, PDN, ... dovrebbero essere caricati assieme alle rispettive grafiche per il web, utilizzando il medesimo nome ed estensione differente. Ad esempio:

1. [turbolab.it-2013-finale.pdn](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/images/logo/2013/turbolab.it-2013-finale.pdn): originale PDN
2. [turbolab.it-2013-finale-tiny.png](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/images/logo/2013/turbolab.it-2013-finale-tiny.png): PNG processata partendo dal PDN


## Immagini di phpBB

Pulsanti, smiley ecc propri della piattaforma phpBB.

Non sono Git-versionate, ma vengono prese dal pacchetto originale di phpBB e utilizzate *as is* (non ci sono particolari benefici a processarle di nuovo).

Ad ogni aggiornamento effettuato tramite [phpbb-upgrade.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/phpbb-upgrade.sh), le immagini di phpBB vengono sovrascritte con le versioni presenti nel nuovo pacchetto originale di phpBB.


## Immagini caricate dagli utenti tramite il forum

Avatar ed eventuali altre immagini caricate dagli utenti sul forum.

Gli avatar sono salvati nella cartella `public/forum/images/avatars/upload/`. La gestione è totalmente in carico a phpBB.

Abbiamo scelto di disattivare la funzione di phpBB che consente agli utenti di caricare immagini direttamente sul nostro server per i seguenti motivi:

1. limitare il consumo di storage sul server
2. aumentare la sicurezza
3. prevenire contestazioni di copyright
4. ridurre spazio e tempo di backup

Le immagini caricate dagli utenti sono invece ospitate esternamente: [vedi #13](https://github.com/TurboLabIt/TurboLab.it/issues/13).


## Immagini degli articoli

Screenshot, foto e altre grafiche caricate dagli autori negli articoli.

Il flusso richiesto è il seguente:

1. l'autore sceglie il file immagine -alla massima risoluzione e senza watermark- dal proprio PC e la carica all'interno dell'articolo
2. il server di TurboLab.it salva il file originale ricevuto *as is*
3. tramite PHP, il server processa il file originale e ne deriva diverse copie, con dimensioni e formati diversi
4. le copie processate vengono salvate in un percorso raggiungibile pubblicamente via web
5. alla richiesta di visualizzazione di un'immagine da parte di un client, il server web eroga direttamente l'appropriata copia generata in precedenza, senza più bisogno di attivare l'interprete PHP ad ogni accesso
