# [Integrazione fra sito e forum](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/forum-integration.md)

Per generare i file necessari all'integrazione fra il sito e phpBB è sufficiente eseguire [scripts/cache-clear.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/cache-clear.sh).


## Estensioni phpBB custom

Le estensioni phpBB sviluppate per TurboLab.it vivono in [src/Forum/ext-turbolabit/](https://github.com/TurboLabIt/TurboLab.it/tree/main/src/Forum/ext-turbolabit); un unico symlink (`public/forum/ext/turbolabit` ➡ `src/Forum/ext-turbolabit`, creato da [cache-clear.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/cache-clear.sh)) le espone tutte a phpBB:

- **forumintegration** — inietta nel forum le personalizzazioni del sito: per esempio l'header/menu e il footer del sito attorno alle pagine del forum, pulsanti extra sui post, pulsante per inserire il link a un articolo, ...
- **httpsonimg** — forza l'HTTPS sulle immagini ospitate su una whitelist di domini
- **tapatalkstripsign** — rimuove la firma automatica *"Sent ... via Tapatalk"*
- **unreadpostslink** — link sempre visibile a *Messaggi non letti* e *Segna tutti come letti*

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
