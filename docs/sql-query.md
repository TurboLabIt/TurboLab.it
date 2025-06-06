# [Query SQL per analisi dati](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/sql-query.md)


## Articoli con titolo più lungo/corto

````sql
SELECT id, title, LENGTH(title) AS num FROM article ORDER BY num ASC_DESC LIMIT 10;
````
