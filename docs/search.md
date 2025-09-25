# [Motore di ricerca interno](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/search.md)

Per la [ricerca interna sul sito](https://turbolab.it/cerca) utilizziamo [Meilisearch](https://www.meilisearch.com/docs/home).

Il pacchetto va installato manualmente, con [webstackup (meilisearch/install.sh)](https://github.com/TurboLabIt/webstackup/blob/master/script/meilisearch/install.sh).

Per l'integrazione in Symfony abbiamo installato:

- [meilisearch/meilisearch-symfony](https://github.com/meilisearch/meilisearch-symfony/wiki/installation)
- [symfony/serializer-pack](https://symfony.com/doc/current/serializer.html)

L'indicizzazione inizia si avvia con [reindex.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/bashrc-dev.sh)
(eseguito autmaticamente via [cron](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/cron)).
Da lì in poi, l'integrazione con Symfony aggiorna automaticamente l'indice al salvataggio delle entity [configurate](https://github.com/TurboLabIt/TurboLab.it/tree/main/config/packages/meilisearch.yaml).

Al momento, è configurata solo l'indicizzazione dell'entity [Article](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Entity/Cms/Article.php).

È necessario indicizzare plain-text, senza tag HTML. Per farlo, le procedure di indicizzazione invocano automaticamente
[Serializer/ArticleSearchNormalizer.php](https://github.com/TurboLabIt/TurboLab.it/tree/main/src/Serializer/ArticleSearchNormalizer.php).


## Aggiornamento

L'aggiornamento di Meilisearch avviene tramite apt, insieme al resto.

Il formato dei dati di ogni versione è però compatibile solo con quella specifica versione: provando ad avviare la nuova versione del servizio con i dati vecchi, si ottiene un errore critico (il servizio non riparte):

> Your database version (1.21.0) is incompatible with your current engine version (1.22.0).

C'è un tool, [Meilisearch Version Migration Script](https://github.com/meilisearch/meilisearch-migration), che dovrebbe fare la migrazione, ma richiede che i dati siano salvati in una cartella diversa da quella utilizzata attualmente dall'installer.

Come workaround:

1. eseguire `webstackup` ➡ Meilisearch GUI ➡ Wipe
2. aggiornare la nuova API key mostrata a schermo nel file `/var/www/turbolab.it/.env.prod.local`
3. lanciare [reindex.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/bashrc-dev.sh) per rigenerare gli indici


## Documentazione Meilisearch

- [FAQ](https://www.meilisearch.com/docs/learn/resources/faq)
- [Getting started with self-hosted Meilisearch](https://www.meilisearch.com/docs/learn/self_hosted/getting_started_with_self_hosted_meilisearch)
- [/keys](https://www.meilisearch.com/docs/reference/api/keys)
- [Update to the latest Meilisearch version](https://www.meilisearch.com/docs/learn/update_and_migration/updating)


## In precedenza

Al lancio di TLI 2.0, eravamo partiti con [Programmable Search Engine](https://programmablesearchengine.google.com/about/) di Google, modalità "Programmatic Access" (Custom Search JSON API), integrato tramite [Service/GoogleProgrammableSearchEngine.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/GoogleProgrammableSearchEngine.php).

La ricerca ha però smesso di funzionare praticamente subito ([🪲 #73](https://github.com/TurboLabIt/TurboLab.it/issues/73)) a causa del superamento della quota. [Il problema è che](https://developers.google.com/custom-search/v1/overview):

> Custom Search JSON API provides 100 search queries per day for free. If you need more, you may sign up for billing in the API Console. Additional requests cost $5 per 1000 queries, up to 10k queries per day.
