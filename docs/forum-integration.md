# [Integrazione fra sito e forum](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/forum-integration.md)

Per generare i file necessari all'integrazione fra il sito e phpBB è sufficiente eseguire [scripts/cache-clear.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/cache-clear.sh).


## Estensioni phpBB custom

Le estensioni phpBB sviluppate per TurboLab.it vivono in [src/Forum/ext-turbolabit/](https://github.com/TurboLabIt/TurboLab.it/tree/main/src/Forum/ext-turbolabit); un unico symlink (`public/forum/ext/turbolabit` ➡ `src/Forum/ext-turbolabit`, creato da [cache-clear.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/cache-clear.sh)) le espone tutte a phpBB:

- **forumintegration** — inietta nel forum le personalizzazioni del sito: per esempio l'header/menu e il footer del sito attorno alle pagine del forum, pulsanti extra sui post, pulsante per inserire il link a un articolo, ...
- **httpsonimg** — forza l'HTTPS sulle immagini ospitate su una whitelist di domini
- **unreadpostslink** — link sempre visibile a *Messaggi non letti* e *Segna tutti come letti*; la voce *I tuoi argomenti* fra i *Collegamenti rapidi* (la ricerca di *I tuoi messaggi* limitata al primo post: `sf=firstpost`), con titolo e breadcrumb propri nella pagina dei risultati ([issue #105](https://github.com/TurboLabIt/TurboLab.it/issues/105)). phpBB non ha un evento subito sotto *I tuoi messaggi*: la voce entra con `navbar_header_quick_links_before`, quindi sopra, e una regola in [assets/styles/forum/app.css](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/styles/forum/app.css) nasconde il separatore che phpBB stampa fra le due. Il sito ha la stessa voce nel menu *Forum*
- [(rimossa) tapatalkstripsign](https://github.com/TurboLabIt/TurboLab.it/commit/469c918c498d6c33e10aa4d99be4b2aa4f747db0) — rimuove la firma automatica *"Sent ... via Tapatalk"*

Il core di phpBB non va mai modificato: ogni personalizzazione passa da queste estensioni.


## Implementazione

L'integrazione (estensione `forumintegration`) è gestita tramite:

1. il modulo per phpBB: [src/Forum/ext-turbolabit/forumintegration](https://github.com/TurboLabIt/TurboLab.it/tree/main/src/Forum/ext-turbolabit/forumintegration)
2. i template HTML: [templates/forum](https://github.com/TurboLabIt/TurboLab.it/tree/main/templates/forum)
3. il comando Symfony: [ForumIntegrationBuilder](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/ForumIntegrationBuilderCommand.php)

Il modulo per phpBB ha una cartella non versionata: `Forum/ext-turbolabit/forumintegration/styles/prosilver/template/event`.  I file che si trovano in questa cartella vengono iniettati nel tema da phpBB.

Il comando `ForumIntegrationBuilder` provvede dunque a:

1. renderizzare i template Twig dedicati
2. salvare l'output renderizzato nella cartella non-versionata del modulo


## 🔗 Vedi anche

- [Commenti agli articoli](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/comments.md)
- [Gestione utenti](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/users.md) — login e autenticazione
- [Integrazione issue GitHub](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/bug.md)
