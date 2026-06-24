# [Gestione delle statistiche](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/stats.md)

Le statistiche di accesso al sito vengono rilevate tramite [Google Analytics 4](https://analytics.google.com) (GA4).

Per essere *GDPR-compliant*, è necessario aggiungere manualmente il parametro `anonymize_ip` al codice di integrazione fornito da GA, in questo modo:

````
...
gtag('config', '...', {'anonymize_ip': true});
````


## Tracciamento

### Sito

Sul sito, il codice di integrazione è in [base.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/base.html.twig).


### Forum

Sul forum, il codice di integrazione è generato dall'estensione di phpBB: [Google Analytics](https://www.phpbb.com/customise/db/extension/googleanalytics/).

Si configura da `ACP ➡ Impostazioni ➡ Google Analytics`.


## Pagina pubblica `/statistiche`

La pagina [https://turbolab.it/statistiche](https://turbolab.it/statistiche) mostra pubblicamente l'andamento del traffico del sito e del forum. **"Oggi" è sempre escluso**, dato che il giorno è ancora in corso e il dato risulterebbe artificialmente più basso. Dove ha senso, il confronto è con lo **stesso giorno della settimana di un anno fa** (52 settimane prima, in modo che il giorno della settimana coincida).

Alcune sezioni più sensibili sono **visibili solo ai membri dello staff** (utenti con `ROLE_EDITOR`).

I dati GA4 vengono letti in tempo reale dalla [GA4 Data API](https://developers.google.com/analytics/devguides/reporting/data/v1) e poi messi in cache per alcune ore (`InfoController::CACHE_DEFAULT_EXPIRY`).

I dati phpBB (utenti registrati, post nel forum, ecc.) sono letti via Doctrine.

I file coinvolti:

- Route + controller: [InfoController.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/InfoController.php) ➡ `app_stats` ➡ `/statistiche` (renderizza solo lo **scheletro** della pagina) e `app_stats_ajax` ➡ `/ajax/statistiche?days=N` (restituisce il JSON dei dati — usato sia al primo caricamento sia al cambio intervallo — con cache per-intervallo; omette le sezioni staff-only ai non-staff)
- Service: [Service/GoogleAnalytics.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/GoogleAnalytics.php) — gli intervalli ammessi sono dichiarati nella costante `ALLOWED_RANGE_DAYS`; il service orchestra anche le chiamate ai repository phpBB
- Template: [templates/info/stats.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/info/stats.html.twig) — solo lo scheletro (nessun dato GA inline); le card sensibili sono avvolte in `{% if CurrentUser.isEditor %}` per il gating staff-only
- Frontend: [assets/js/stats.js](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/js/stats.js) (entry [assets/stats.js](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/stats.js)) — i grafici sono renderizzati con [Chart.js](https://www.chartjs.org/) **bundled via Webpack/yarn** (`chart.js` è in `package.json`); al primo caricamento esegue il fetch di `app_stats_ajax` e costruisce i grafici, al cambio intervallo li aggiorna in-place via `chart.data` + `chart.update()`


### Configurazione necessaria

Per far funzionare la pagina servono **due elementi**:

1. la **property ID numerica** della property GA4
2. una **chiave JSON** di un *service account* di Google Cloud, con accesso in lettura alla property GA4

#### 1. Property ID numerica

Da [Google Analytics](https://analytics.google.com) ➡ ⚙ *Admin* ➡ *Property settings* ➡ *Property details*: in alto a destra appare un numero a 9–10 cifre (NON la stringa `G-XXXXXXXX`, che è la *measurement ID*, una cosa diversa).

Va valorizzata nella variabile di ambiente, all'interno del file `.env.<dev|staging|prod>.local:

````
APP_GOOGLE_ANALYTICS_PROPERTY_ID=123456789
````


#### 2. Service account + chiave JSON

[GA4 Data API](https://developers.google.com/analytics/devguides/reporting/data/v1) richiede un *service account* di Google Cloud con accesso *Viewer* alla property GA4. **Non si può usare una semplice API key**.

Procedura passo-passo:

1. Aprire la [Google Cloud Console](https://console.cloud.google.com/).

2. Creare un nuovo progetto (oppure riutilizzarne uno esistente, ad esempio `turbolabit`):
    - In alto, *Select a project* ➡ *New project*
    - Nome: ad es. `turbolabit-stats`

3. Abilitare la *Google Analytics Data API*:
    - [APIs & Services ➡ Library](https://console.cloud.google.com/apis/library)
    - Cercare *Google Analytics Data API*
    - *Enable*

4. Creare il *service account*:
    - [IAM & Admin ➡ Service Accounts](https://console.cloud.google.com/iam-admin/serviceaccounts)
    - *Create service account*
    - Nome: ad es. `tli-stats-reader`
    - Concedere il ruolo *Viewer* a livello di progetto **NON è necessario**: per la GA4 Data API il permesso si concede direttamente sulla property (vedi punto 6).

5. Generare la chiave JSON:
    - Dal dettaglio del service account ➡ *Keys* ➡ *Add key* ➡ *Create new key* ➡ *JSON*
    - Il file viene scaricato sul PC; **conservarlo in modo sicuro**, non è ri-scaricabile

6. Concedere al service account l'accesso alla property GA4:
    - Su [Google Analytics](https://analytics.google.com) ➡ ⚙ *Admin* ➡ *Property access management*
    - *+* ➡ *Add users*
    - L'indirizzo da inserire è quello del service account (visibile come `client_email` dentro il JSON, ha la forma `nome@progetto.iam.gserviceaccount.com`)
    - Ruolo: *Viewer* (sufficiente; non serve *Analyst* o superiore)
    - **Disattivare** *Notify new users by email* (tanto è un account di servizio)

7. Caricare il file JSON sul server:

    ````
    /var/www/turbolab.it/var/google-analytics-credentials.json
    ````

    Permessi consigliati:

    ````
    chown www-data:www-data var/google-analytics-credentials.json
    chmod 640 var/google-analytics-credentials.json
    ````

8. Verificare aprendo [https://turbolab.it/statistiche](https://turbolab.it/statistiche). Se la configurazione è corretta i grafici compaiono entro pochi secondi.


### Funzionamento interno

- **Autenticazione**: il service implementa direttamente il flusso *JWT bearer* di OAuth2 (firma RS256 con `openssl_sign`, scambio JWT ➡ access token su `https://oauth2.googleapis.com/token`). Non è stato installato il pacchetto `google/auth` per evitare un albero di dipendenze pesante: il pattern segue [Service/YouTubeChannelApi.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/YouTubeChannelApi.php) e [Service/GoogleProgrammableSearchEngine.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/GoogleProgrammableSearchEngine.php).
- **Access token**: cache di 50 minuti (i token Google durano 60 minuti).
- **Report GA4**: per ogni intervallo richiesto vengono lanciate più `runReport` (serie giornaliera + report aggregati per ranking), in alcuni casi affiancate dal report dell'anno precedente sfalsato di **364 giorni** (52 settimane: in questo modo il giorno della settimana è preservato). I singoli report sono messi in cache con chiave indipendente per ogni intervallo: la prima visita "scalda" la cache, le seguenti sono istantanee.
- **Cache**: la response HTML/JSON aggregata viene memorizzata per alcune ore (`InfoController::CACHE_DEFAULT_EXPIRY`); il caching avviene solo per le richieste anonime — le richieste autenticate (per cui la response varia in base al ruolo, vedi gating staff-only) sono sempre rigenerate.


### Troubleshooting

| Sintomo | Causa probabile |
|---|---|
| "Statistiche non ancora configurate" | manca `APP_GOOGLE_ANALYTICS_PROPERTY_ID` o il file `var/google-analytics-credentials.json` |
| `403 PERMISSION_DENIED` | il service account non è stato aggiunto come *Viewer* sulla property GA4, oppure è stato aggiunto su una property diversa |
| `400 INVALID_ARGUMENT: Invalid value at 'property'` | è stata configurata la *measurement ID* (`G-...`) invece della *property ID* numerica |
| `Google Analytics Data API has not been used in project ... before or it is disabled` | l'API non è abilitata sul progetto Cloud (vedi punto 3) |
| Grafici vuoti / a zero | la property GA4 è nuova e non ha ancora dati, oppure è la property sbagliata |

Per forzare un refresh dopo aver corretto la configurazione, svuotare la cache:

````shell
bash scripts/cache-clear.sh
````
