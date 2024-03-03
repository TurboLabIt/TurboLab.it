# [Gestione delle immagini](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md)

TurboLab.it deve salvare, gestire ed erogare diverse tipologie di immagini.


## 📃 Immagini del sito

**Logo, icone e altre immagini decorative**

Sono Git-versionate e salvate direttamente nella cartella [public/images](https://github.com/TurboLabIt/TurboLab.it/tree/main/public/images), quindi risultano direttamente accessibili via web.

I formati da utilizzare per aggiungere nuove immagini sono, nell'ordine di preferenza: SVG, AVIF trasparente, PNG trasparente. Il logo fa eccezione: deve rimanere in PNG trasparente per massimizzare la compatibilità.

Le immagini PNG da erogare ai client sono quelle con suffisso `-tiny`, processate con [tinypng.com](https://tinypng.com). ⚠ Aggiungere sempre al repo sia l'immagine originale, sia quella processata. Solo in caso sia presente l'"originale" in formato PSD, PDN, non è necessario versionare il PNG, ma bastano l'originale e la copia compressa.

Eventuali "originali" in formato PSD, PDN, ... dovrebbero essere caricati insieme alle rispettive grafiche per il web, utilizzando il medesimo nome ed estensione differente. Ad esempio:

1. [turbolab.it-2013-finale.pdn](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/images/logo/2013/turbolab.it-2013-finale.pdn): originale PDN
2. [turbolab.it-2013-finale-tiny.png](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/images/logo/2013/turbolab.it-2013-finale-tiny.png): PNG processata partendo dal PDN

Alcune immagini speciali, come [https://turbolab.it/favicon.ico](https://turbolab.it/favicon.ico) oppure [apple-touch-icon.png](https://turbolab.it/apple-touch-icon.png), in realtà vengono lette da `public/images/logo/` tramite [rewrite in nginx.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/nginx.conf) allo scopo di mantenere i percorsi di default ma, allo stesso tempo, non accatastare decine di file nella cartella `public`. Detti file sono a loro volta *symlink* al file finale:

- `public/favicon.ico` ➡ `public/images/logo/favicon.ico` ➡ `public/images/logo/2013/favicon.ico`
- `public/apple-touch-icon.png` ➡ `public/images/logo/apple-touch-icon.png` ➡ `public/images/logo/2013/ttt-tiny.png`


## 🏟 Immagini di phpBB

**Pulsanti, smiley ecc. propri della piattaforma phpBB**

Non sono Git-versionate, ma vengono prese dal pacchetto originale di phpBB e utilizzate *as is* (non ci sono particolari benefici a processarle di nuovo).

Ad ogni aggiornamento effettuato tramite [phpbb-upgrade.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/phpbb-upgrade.sh), le immagini di phpBB vengono sostituite dalle versioni presenti nel nuovo pacchetto originale di phpBB. Eventuali immagini aggiunte fuori dal percorso del tema sono rimosse.


## 🧔 Immagini caricate dagli utenti tramite il forum

**Avatar e altre immagini caricate dagli utenti sul forum**

Gli avatar sono salvati nella cartella `public/forum/images/avatars/upload/`. La gestione è totalmente in carico a phpBB.

La funzione di phpBB che consente agli utenti di caricare immagini direttamente sul nostro server è disaattivata, e deve rimanerlo per i seguenti motivi:

1. limitare il consumo di storage sul server
2. aumentare la sicurezza
3. prevenire contestazioni di copyright
4. ridurre spazio e tempo di backup

Le immagini caricate dagli utenti sono invece ospitate esternamente: [vedi #13](https://github.com/TurboLabIt/TurboLab.it/issues/13).


## 📸 Immagini degli articoli

**Screenshot, foto e altre grafiche caricate dagli autori negli articoli** | 🖼 [Immagini a campione qui](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-sample.md)

🔗 Vedi: [Gestione delle immagini caricate negli articoli](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-articles.md)
