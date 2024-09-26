# [Gestione delle statistiche](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/stats.md)

Le statistiche di accesso al sito vengono rilevate tramite [Google Analytics](https://www.google.com/analytics) (GA).

Per essere *GDPR-compliant*, è necessario aggiungere manualmente il parametro `anonymize_ip` al codice di integrazione fornito da GA:
in questo modo:

````
...
gtag('config', '...', {'anonymize_ip': true});
````


## Sito

Sul sito, il codice di integrazione è in [base.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/base.html.twig).


## Forum

Sul forum, il codice di integrazione è generato dall'estensione ufficiale di phpBB: [Google Analytics](https://www.phpbb.com/customise/db/extension/googleanalytics/).

Si configura da `ACP ➡ Impostazioni ➡ Google Analytics`.
