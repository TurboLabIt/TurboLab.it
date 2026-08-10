# [Motore di ricerca interno](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/search.md)

Per la [ricerca interna sul sito](https://turbolab.it/cerca) utilizziamo [Meilisearch](https://www.meilisearch.com/docs/home).

Il pacchetto va installato manualmente, con [webstackup (meilisearch/install.sh)](https://github.com/TurboLabIt/webstackup/blob/master/script/meilisearch/install.sh).

Per l'integrazione in Symfony abbiamo installato:

- [meilisearch/meilisearch-symfony](https://github.com/meilisearch/meilisearch-symfony/wiki/installation)
- [symfony/serializer-pack](https://symfony.com/doc/current/serializer.html)

L'indicizzazione si avvia con [reindex.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/reindex.sh), eseguito in notturna via [cron](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/cron). Durante il giorno, l'integrazione con Symfony aggiorna automaticamente l'indice al salvataggio delle entity [configurate](https://github.com/TurboLabIt/TurboLab.it/tree/main/config/packages/meilisearch.yaml) - al momento, è configurata solo l'indicizzazione dell'entity [Article](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Entity/Cms/Article.php).

È necessario indicizzare plain-text, senza tag HTML. Per farlo, le procedure di indicizzazione invocano automaticamente
[Serializer/ArticleSearchNormalizer.php](https://github.com/TurboLabIt/TurboLab.it/tree/main/src/Serializer/ArticleSearchNormalizer.php).


## Aggiornamento

L'aggiornamento di Meilisearch avviene tramite apt, insieme al resto.

Il formato dei dati è però legato alla versione dell'engine: avviando la nuova versione sui dati vecchi si ottiene un errore critico e **il servizio non riparte**. Con `Restart=on-failure` nel unit file (default), il risultato è un crash-loop infinito e la ricerca va in 500 finché qualcuno non se ne accorge:

> Your database version (1.49.0) is incompatible with your current engine version (1.52.0).

Per intercettare la cosa, [reindex.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/reindex.sh) interroga `/health` prima di indicizzare e si ferma con `fxCatastrophicError` (exit 1) se il servizio non risponde: senza questo controllo i comandi `meili:*` fallirebbero uno per uno e lo script terminerebbe comunque con successo, nascondendo il problema nel log del cron.

Per questo `/etc/meilisearch.toml` ([webstackup: config/meilisearch/config.toml](https://github.com/TurboLabIt/webstackup/blob/master/config/meilisearch/config.toml)) contiene:

```toml
upgrade_db = true
```

Con questa opzione l'engine migra il database in-place al primo avvio, senza dump e **senza toccare le API key**: `MEILISEARCH_API_KEY` resta valida, non c'è nulla da aggiornare nei file `.env*`. La migrazione 1.49 ➡ 1.52 (458 MB, 3.864 documenti) ha richiesto 20 ms. Quando non c'è niente da migrare l'opzione è un no-op, quindi può restare attiva in permanenza.

Due avvertenze:

- l'opzione è stata stabilizzata solo in **Meilisearch 1.51** (prima era `--experimental-dumpless-upgrade`) e non è documentata come chiave del file di configurazione. Il parser TOML è strict: su un engine più vecchio il servizio rifiuta di avviarsi con `unknown field upgrade_db`. Va aggiunta solo su box con engine ≥ 1.51.
- `install.sh` scarica `config.toml` da GitHub **al momento dell'installazione**: modificarlo nel repo webstackup vale solo per le installazioni nuove, sui box già in piedi va aggiunto a mano in `/etc/meilisearch.toml`.

---

Il vecchio workaround resta valido come ultima risorsa (l'indice è derivato: [reindex.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/reindex.sh) lo ricostruisce da MySQL), ma non dovrebbe più servire:

1. eseguire `webstackup` ➡ `Meilisearch GUI` ➡ `Wipe`
2. aggiornare la nuova API key mostrata a schermo nel file `/var/www/turbolab.it/.env.prod.local` — le API key di default sono **casuali per database**, quindi un wipe le invalida sempre
3. lanciare [cache-clear.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/cache-clear.sh) per il dump-env
4. lanciare [reindex.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/reindex.sh) per rigenerare gli indici


## Documentazione Meilisearch

- [FAQ](https://www.meilisearch.com/docs/learn/resources/faq)
- [Getting started with self-hosted Meilisearch](https://www.meilisearch.com/docs/learn/self_hosted/getting_started_with_self_hosted_meilisearch)
- [/keys](https://www.meilisearch.com/docs/reference/api/keys)
- [Update to the latest Meilisearch version](https://www.meilisearch.com/docs/learn/update_and_migration/updating)


## In precedenza

Al lancio di TLI 2.0, eravamo partiti con [Programmable Search Engine](https://programmablesearchengine.google.com/about/) di Google, modalità "Programmatic Access" (Custom Search JSON API), integrato tramite [Service/GoogleProgrammableSearchEngine.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/GoogleProgrammableSearchEngine.php).

La ricerca ha però smesso di funzionare praticamente subito ([🪲 #73](https://github.com/TurboLabIt/TurboLab.it/issues/73)) a causa del superamento della quota. [Il problema è che](https://developers.google.com/custom-search/v1/overview):

> Custom Search JSON API provides 100 search queries per day for free. If you need more, you may sign up for billing in the API Console. Additional requests cost $5 per 1000 queries, up to 10k queries per day.
