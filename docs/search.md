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


## Documentazione Meilisearch

- [FAQ](https://www.meilisearch.com/docs/learn/resources/faq)
- [Getting started with self-hosted Meilisearch](https://www.meilisearch.com/docs/learn/self_hosted/getting_started_with_self_hosted_meilisearch)
- [/keys](https://www.meilisearch.com/docs/reference/api/keys)


## In precedenza

Al lancio di TLI 2.0, eravamo partiti con [Programmable Search Engine](https://programmablesearchengine.google.com/about/)
di Google, modalità "Programmatic Access" (Custom Search JSON API),
integrato tramite [Service/GoogleProgrammableSearchEngine.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/GoogleProgrammableSearchEngine.php).

La ricerca ha però smesso di funzionare praticamente subito (#73) a causa del superamento della quota

![image](https://i.postimg.cc/FsDbHCPt/sshot-1758487506.png)

[Il problema è che](https://developers.google.com/custom-search/v1/overview):

> Custom Search JSON API provides 100 search queries per day for free. If you need more, you may sign up for billing in the API Console. Additional requests cost $5 per 1000 queries, up to 10k queries per day.
