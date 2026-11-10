# [SEO per le notizie e tracciamento delle campagne](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/seo-news.md)

Una notizia vive poche ore: non può aspettare che Google la trovi da solo, come avviene per le guide. Questa pagina raccoglie ciò che il sito espone affinché gli articoli siano idonei alle "vetrine" di Google per i contenuti freschi (*Notizie principali*, *Google News*, *Discover*), e ciò che serve a Google Analytics per capire da dove arrivano i lettori.

Il contesto: a novembre 2026 una notizia otteneva in media meno di 100 visualizzazioni. Le pagine non avevano dati strutturati, non consentivano le anteprime grandi e non esisteva una Sitemap per le notizie; i click da newsletter e social risultavano quasi tutti come traffico "diretto".


## Cosa espone ogni articolo

Solo gli articoli pubblicati (non le bozze, né quelli programmati per il futuro):

| Cosa | Dove | A cosa serve |
|------|------|--------------|
| `<meta name="robots" content="index,follow,max-image-preview:large">` | [base.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/base.html.twig), `Article::getMetaRobots()` | senza `max-image-preview:large` Discover mostra solo la miniatura piccola, o nulla. Vale per tutte le pagine indicizzabili |
| `og:type = article`, `article:published_time`, `article:modified_time`, `article:section` | [article/index.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/article/index.html.twig) | data e sezione per social e aggregatori |
| JSON-LD `NewsArticle` (notizie) oppure `Article` (guide) | `Article::getStructuredData()` in [Service/Cms/Article.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/Article.php) | titolo, immagine, date, autori ed editore in forma leggibile dai motori |

Note sul JSON-LD:

- l'immagine è lo *spotlight* a dimensione massima: Discover richiede almeno 1200 px di larghezza
- `dateModified` non è mai precedente a `datePublished` (`Article::getModifiedAt()`): un articolo programmato viene modificato prima di uscire
- un articolo firmato dal solo utente *System* ha come autore l'organizzazione TurboLab.it
- il JSON è generato da `Article::getStructuredDataJson()`, che lo rende sicuro all'interno di `<script>`: per questo il template lo stampa con `|raw`

Per verificare una pagina: [Rich Results Test](https://search.google.com/test/rich-results).


## Sitemap per Google News

[https://turbolab.it/sitemap-news.xml](https://turbolab.it/sitemap-news.xml) elenca gli articoli usciti negli ultimi due giorni (Google News ignora quelli più vecchi), nel [formato richiesto da Google](https://developers.google.com/search/docs/crawling-indexing/sitemaps/news-sitemap).

- è una pagina dinamica (`FeedController::newsSitemap()`), non un file generato da cron: usa la stessa cache dei feed RSS (tag `app_feed`), quindi si aggiorna appena un articolo viene pubblicato. La [Sitemap generale](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/sitemaps.md), invece, è rigenerata solo di notte
- sono esclusi gli articoli sponsorizzati e la copia web della newsletter
- è dichiarata in [robots.txt](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/misc/robots.txt); conviene anche inviarla da Search Console


## Tracciamento delle campagne (utm)

Un click da un client di posta o da un'app (Telegram, Facebook, ...) arriva sul sito senza *referrer*, e Analytics lo conta come "diretto". Per distinguerlo, i link che pubblichiamo noi portano i parametri `utm_*`, aggiunti da `UrlGenerator::addCampaignParams()`:

| Canale | `utm_source` | `utm_medium` | `utm_campaign` | Dove |
|--------|--------------|--------------|----------------|------|
| Newsletter | `newsletter` | `email` | `questa-settimana-su-tli` | `NewsletterController::opener()`: tutti i link interni della mail passano da lì |
| Condivisione automatica | `telegram`, `facebook`, `twitter`, `linkedin` | `social` | `auto-share` | `ShareOnSocialCommand::buildCampaignUrl()` |

- i link verso siti esterni (es. i video su YouTube) non vengono toccati
- un URL che ha già `utm_source` resta com'è
- i parametri non cambiano la pagina servita: il `canonical` resta l'URL pulito

In Analytics: *Acquisizione ➡ Acquisizione traffico*, dimensione *Sorgente/mezzo sessione*.


## Test

[tests/Smoke/SeoNewsTest.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/tests/Smoke/SeoNewsTest.php)
