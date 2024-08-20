# [Generazione delle Sitemap](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/sitemaps.md)

Gli URL dei contenuti di TurboLab.it (sito e forum) devono essere indicizzati all'interno di una [📚 Sitemap XML](https://www.sitemaps.org/protocol.html).

Il protocollo Sitemap impone un limite massimo al numero di URL indicizzabili da ogni singolo file. Tale limite è inferiore al numero di URL presenti su TLI. Di conseguenza, è necessario suddividere gli URL in molteplici file, che saranno poi indicizzati a loro volta da una "Sitemap index file".

**Quando si parla della "Sitemap di TLI" si fa riferimento a questo "Sitemap index file"**, non ai singoli file parziali.


## URL

La Sitemap di TLI è raggiungibile all'URL [https://turbolab.it/sitemap/sitemap.xml.gz](https://turbolab.it/sitemap/sitemap.xml.gz)

L'URL è indicato ai crawler tramite [robots.txt](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/misc/robots.txt).


## Path su filesystem

I file che compongono la Sitemap sono salvati fisicamente nella cartella `var/sitemaps/`, ma esposti sul web dalla *location /sitemap/* presente in [config/custom/nginx.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/nginx.conf).


## File compressi e non-compressi.

Il protocollo Sitemap prevede che i file possano essere compressi (gzip).

La procedura che genera la Sitemap si occupa automaticamente di creare le Sitemap già in versione gzippata.

**Nei soli ambienti non-prod** le Sitemap non-compresse vengono comunque salvate su disco per semplificare il debug. Nell'ambiente di prod, invece, **questi file non-compressi non sono disponibili**.


## Paginazione esclusa

Da [📚 SEO-Friendly Pagination: A Complete Best Practices Guide](https://www.searchenginejournal.com/technical-seo/pagination/):

> **Don’t Include Paginated Pages in XML Sitemaps**
> While paginated URLs are technically indexable, they aren’t an SEO priority to spend crawl budget on.
> As such, they don’t belong in your XML sitemap.

Il relativo codice è dunque stato rimosso. In caso fosse necessario ripristinarlo, vedi [revision a040efc
](https://github.com/TurboLabIt/TurboLab.it/blob/a040efcdb3f3fb75fef64560524f6354f8016938/src/Command/SitemapGeneratorCommand.php#L136)


## Nessuna segnalazione (ping) ai motori

La vecchia pratica di inviare un "ping" ai motori di ricerca dopo l'aggiornamento della sitemap [📚 non è più necessaria, né possibile](https://developers.google.com/search/blog/2023/06/sitemaps-lastmod-ping).


## Sitemap all'interno di una cartella...?

Da specifiche, il dominio degli URL di una Sitemap posizionata in una cartella inizia dalla cartella stessa, e non può contenere URL relativi alla root o ad altre cartelle.

[📚 sitemaps.org](https://www.sitemaps.org/protocol.html#location):

> A Sitemap file located at http://example.com/catalog/sitemap.xml can include any URLs starting with http://example.com/catalog/ but can not include URLs starting with http://example.com/images/

[📚 Google Search Central](https://developers.google.com/search/docs/crawling-indexing/sitemaps/large-sitemaps?hl=en#sitemap-index-best-practices):

> Sitemaps that are referenced in the sitemap index file must be in the same directory as the sitemap index file, or lower in the site hierarchy. For example, if the sitemap index file is at https://example.com/public/sitemap_index.xml, it can only contain sitemaps that are in the same or deeper directory, like https://example.com/public/shared/....

In realtà, *Google Search Console* non segnala alcuna violazione. Inoltre, l'esperienza diretta e le testimonianze su *Stack Exchange* (rif.: [Can a sitemap index file list sitemaps in a different directory?](https://webmasters.stackexchange.com/a/23933/87608), [Does sitemap.xml have to be in the root directory?](https://webmasters.stackexchange.com/q/29564/87608)) confermano che questa restrizione o non è implementata, oppure la presenza dell'URL nel file [robots.txt](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/misc/robots.txt) la disattiva similmente a quanto avviene per il *cross submit*.


## Comando di generazione

La Sitemap viene generata quotidianamente tramite *cron* ([staging](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/staging/cron) | [prod](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/prod/cron)).

Il comando utilizzato è [scripts/sitemap-generate.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/sitemap-generate.sh), che a sua volta esegue [Command/SitemapGeneratorCommand.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/SitemapGeneratorCommand.php).

La procedura genera dapprima i file nella cartella temporanea `var/sitemaps_new/`. Solo se la generazione termina correttamente questa cartella sostituisce la "vera" `var/sitemaps/`.

Se viene specificata l'opzione `--dry-run`, il comando genera i file nella cartella temporanea, ma poi non li sposta nella cartella accessibile tramite server web, lasciando dunque disponibile la copia precedente dei file.
