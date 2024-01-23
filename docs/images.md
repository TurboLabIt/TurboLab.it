# Gestione delle immagini

TurboLab.it deve salvare, gestire ed erogare diverse tipologie di immagini.


## Immagini del sito

Logo, icone e altre immagini decorative.

Sono Git-versionate e salvate direttamente nella cartella [public/images](https://github.com/TurboLabIt/TurboLab.it/tree/main/public/images), quindi risultano direttamente accssibili via web.

I formati da utilizzare sono, nell'ordine di preferenza: SVG, AVIF trasparente, PNG trasparente. Il logo fa eccezione: deve rimanere in PNG trasparente per massimizzare la compatibilità.

Le immagini PNG da utilizzare concretamente nel codice sono quelle con suffisso `-tiny`, processate con [tinypng.com](https://tinypng.com). Aggiungere sempre al repo sia l'immagine originale, sia quella processata.


## Immagini di phpBB

Pulsanti, smiley ecc propri della piattaforma phpBB.

Non sono Git-versionate, ma vengono prese dal pacchetto originale di phpBB e utilizzate *as-is* (non ci sono particolari benefici a processarle di nuovo).

Ad ogni aggiornamento effettuato tramite [phpbb-upgrade.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/phpbb-upgrade.sh), le immagini di phpBB vengono sovrascritte con le versioni presenti nel nuovo pacchetto originale di phpBB.


## Immagini caricate dagli utenti tramite il forum

Avatar ed eventuali altre immagini caricate dagli utenti sul forum.

Gli avatar sono salvati nella cartella `public/forum/images/avatars/upload/`. La gestione è totalmente in carico a phpBB.

Per semplificare la gestione, aumentare la sicurezza e prevenire contestazioni di vario tipo, abbiamo scelto di disattivare la funzione di phpBB che consente agli utenti di caricare immagini direttamente sul nostro server.

Le immagini caricate dagli utenti vengono invece ospitate esternamente: [vedi #13](https://github.com/TurboLabIt/TurboLab.it/issues/13).


## Immagini degli articoli
