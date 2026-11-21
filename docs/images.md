# [Gestione delle immagini](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images.md)

TurboLab.it deve salvare, gestire ed erogare diverse tipologie di immagini.


## 📃 Immagini del sito

**Logo, icone e altre immagini decorative**

Sono Git-versionate e salvate direttamente nella cartella [public/images](https://github.com/TurboLabIt/TurboLab.it/tree/main/public/images), quindi risultano direttamente accessibili via web.

I formati da utilizzare per aggiungere nuove immagini sono, nell'ordine di preferenza: SVG, AVIF trasparente, PNG trasparente. Il logo fa eccezione: deve rimanere in PNG trasparente per massimizzare la compatibilità.

Le immagini PNG da erogare ai client sono quelle con suffisso `-tiny`, processate con [tinypng.com](https://tinypng.com). ⚠ Aggiungere sempre al repo sia l'immagine originale, sia quella processata. Solo in caso sia presente l'"originale" in formato PSD, PDN, non è necessario versionare il PNG, ma bastano l'originale e la copia compressa.

Eventuali "originali" in formato PSD, PDN, ... dovrebbero essere caricate insieme alle rispettive grafiche per il web, utilizzando il medesimo nome ed estensione differente. Ad esempio:

1. [turbolab.it-2013-finale.pdn](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/images/logo/2013/turbolab.it-2013-finale.pdn): originale PDN
2. [turbolab.it-2013-finale-tiny.png](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/images/logo/2013/turbolab.it-2013-finale-tiny.png): PNG processata partendo dal PDN

Alcune immagini speciali, come [favicon.ico](https://turbolab.it/favicon.ico) oppure [apple-touch-icon.png](https://turbolab.it/apple-touch-icon.png), devono restare raggiungibili dai percorsi di default attesi da browser e sistemi operativi. Per non accatastare questi file nella cartella `public`, una [rewrite in nginx.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/nginx.conf) li serve direttamente dalle versioni in `public/images/logo/2013/`:

- `/favicon.ico` ➡ `/images/logo/2013/favicon.ico`
- `/apple-touch-icon.png` ➡ `/images/logo/2013/ttt-192px-tiny.png`
- `/images/logo/icon.png` ➡ `/images/logo/2013/ttt-192px-tiny.png`


## 🗄 Immagini indicate dal database: badge e icone dei forum

Due gruppi di immagini sono indicati per nome dal database, non dal codice: cercarne il nome nel repo non trova nulla, ma sono in uso.

- [assets/images/](https://github.com/TurboLabIt/TurboLab.it/tree/main/assets/images) — quasi tutte sono le icone dei *badge*: [article/badges.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/article/badges.html.twig) compone il percorso da `badge.image_url` (`asset('build/images/' ~ Badge.imageUrl)`) ed Encore le pubblica in `public/build/images/` (`copyFiles` in [webpack.config.js](https://github.com/TurboLabIt/TurboLab.it/blob/main/webpack.config.js)). Un articolo mostra un badge se è collegato all'articolo (`article_badge`) o a uno dei suoi tag (`tag_badge`). Il codice assegna solo quello AI (`Badge::ID_AI`, con la spunta su `/scrivi`): gli altri si collegano a mano nel database, senza interfaccia
- [public/images/forum/icons/](https://github.com/TurboLabIt/TurboLab.it/tree/main/public/images/forum/icons) — le icone dei forum, scelte dal pannello di amministrazione di phpBB e salvate in `phpbb_forums.forum_image` come `../images/forum/icons/<nome>.png`

⚠️ Prima di cancellarne una, controllare il database di produzione: la copia su dev può non avere un forum o un collegamento creato dopo l'ultima importazione.

````sql
SELECT forum_id, forum_name, forum_image FROM turbolab_it_forum.phpbb_forums WHERE forum_image <> '';

SELECT b.id, b.title, b.image_url,
  (SELECT COUNT(*) FROM turbolab_it.article_badge ab WHERE ab.badge_id = b.id) AS articoli,
  (SELECT COUNT(*) FROM turbolab_it.tag_badge tb WHERE tb.badge_id = b.id) AS tag
FROM turbolab_it.badge b;
````

I badge ereditati da TLI1 che nessun tag o articolo porta (1, 3, 4, 5, 7, 11, 16: `news`, `prova`, `no-tag`, `bozza`, `vecchio`, `top-visite`, `low-rating`) puntano a immagini che non esistono: alcune non sono mai arrivate in TLI2, le altre sono state rimosse il 2026-11-21 perché inutilizzate. Prima di collegarne uno va rimessa la sua immagine: quelle rimosse sono nella storia di Git (`git log --diff-filter=D -- assets/images/`).


## 🏟 Immagini di phpBB

Pulsanti, smiley ecc. propri della piattaforma phpBB non sono Git-versionate, ma vengono prese dal pacchetto originale di phpBB e utilizzate *as is* (non ci sono particolari benefici a processarle di nuovo).

Ad ogni aggiornamento effettuato tramite [phpbb-upgrade.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/phpbb-upgrade.sh), le immagini di phpBB vengono sostituite dalle versioni presenti nel nuovo pacchetto originale di phpBB. Eventuali immagini aggiunte fuori dal percorso del tema sono rimosse.


## 🧔 Immagini caricate dagli utenti tramite il forum

Gli avatar sono salvati nella cartella `public/forum/images/avatars/upload/`.

I file allegati dagli utenti ai post sono salvati nella cartella `public/forum/files/`.

La gestione è totalmente in carico a phpBB. Questi file non sono Git-versionati.


## 📸 Immagini degli articoli

🔗 Vedi: [Gestione delle immagini caricate negli articoli](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-articles.md)


## 🎡 Asset del tema

🔗 Vedi: [Gestione degli asset frontend](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/assets-frontend.md)
