# [Integrazione fra sito e forum](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/forum-integration.md)

Per generare i file necessari all'integrazione fra il sito e phpBB è sufficiente eseguire [scripts/cache-clear.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/cache-clear.sh).


## Implementazione

L'integrazione fra sito e forum è gestita tramite i seguenti elementi:

1. modulo per phpBB: [src/Forum/ext-turbolabit/forumintegration](https://github.com/TurboLabIt/TurboLab.it/tree/main/src/Forum/ext-turbolabit/forumintegration)
2. symlink in `public/forum/ext/turbolabit` che fa credere a phpBB che il modulo si trovi nel percorso dedicato ai moduli
2. template HTML: [templates/forum](https://github.com/TurboLabIt/TurboLab.it/tree/main/templates/forum)
3. comando Symfony: [ForumIntegrationBuilder](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/ForumIntegrationBuilderCommand.php)

Il modulo per phpBB ha una cartella non versionata: `Forum/ext-turbolabit/forumintegration/styles/prosilver/template/event`.
I file che si trovano in questa cartella vengono iniettati nel tema da phpBB.

Il comando `ForumIntegrationBuilder` provvede dunque a:

1. renderizzare i template Twig dedicati
2. salvare l'output renderizzato nella cartella non-versionata del modulo

Versione comando su TLI1 (closed source): [forum-integration/forum_integration.php](https://github.com/TurboLabIt/tli1-sasha-grey/blob/master/website/www/public/forum-integration/forum_integration.php)
