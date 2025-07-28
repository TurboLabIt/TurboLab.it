# [Query SQL per analisi dati](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/sql-query.md)


## Articoli con titolo più lungo

````sql
SELECT id, title, LENGTH(title) AS num FROM article ORDER BY num DESC LIMIT 10;
````
