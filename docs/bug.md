# [Integrazione issue GitHub](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/bug.md)

Gli utenti registrati possono trasformare un post del forum in una *issue* sul [repository GitHub di TurboLab.it](https://github.com/TurboLabIt/TurboLab.it/issues) con un solo click. La issue viene creata tramite le API di GitHub e, contestualmente, il post originale sul forum viene modificato per aggiungervi un link alla issue appena creata.


## Informazioni/guida per utenti finali

La guida per utenti finali è disponibile qui: [Ho un'idea / suggerimento / problema con TurboLab.it: cosa faccio?](https://turbolab.it/49).


## Architettura

Il meccanismo coinvolge tre attori:

1. il *frontend* (modale + JavaScript sul forum)
2. il *backend* Symfony (che parla con GitHub e orchestra il flusso)
3. una *special page* PHP che opera nel contesto di phpBB (che riscrive il post)


## Dove e quando appare il pulsante

Il pulsante che consente di aprire una issue GitHub a partire da un post del forum è un'icona a forma di insetto verde (`fa-bug`, simile a 🪲), mostrata in alcuni post

![bug](https://turbolab.it/immagini/reg/6/forum-issue-report-01-26612.avif)

L'HTML dell'icona è [templates/forum/post-buttons.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/forum/post-buttons.html.twig).

L'icona viene mostrata solo se:

- l'utente è loggato (`S_USER_LOGGED_IN`)
- il post appartiene a uno dei forum abilitati: `Forum::ID_TLI` o `Forum::ID_COMMENTS`
- **non** si tratta del primo post di una discussione nel forum commenti: quel post è generato automaticamente e rappresenta l'articolo, non una segnalazione

Cliccando tale icona, si apre una modale di conferma `#tli-issue-modal`. È definita in [templates/forum/09-overall-header.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/forum/09-overall-header.html.twig) e contiene il promemoria di leggere la guida e il pulsante `OK`, che porta nell'attributo `data-url` l'URL dell'endpoint Symfony (route `app_forum_new_issue`).


## Il flusso lato-client

To-client, la funzionalità viene guidata da [assets/js/forum/issue.js](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/js/forum/issue.js), incluso tramite l'*entry point* [assets/forum.js](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/forum.js):

1. click sull'icona 🪲 (`.tli-open-issue-modal`) ➡ apre la modale e vi copia il `post-id` del post selezionato. Un *lock* (classe `tli-issue-action-running`) impedisce di avviare due creazioni in parallelo
2. click su `OK` (`.tli-create-issue`) ➡ mostra uno spinner, disabilita i pulsanti ed esegue `POST {data-url}` con il solo parametro `postId`
3. in caso di successo, la risposta del backend è l'URL del post. Prima di usarla per il redirect, viene validata da [Validator.isSameOriginHttpsUrl](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/js/validator.js): deve essere un URL `https` *same-origin*. Questo previene che una risposta inattesa o manomessa venga passata a `window.location` (es. uno schema `javascript:`). Se l'URL è valido ➡ redirect al post (con *reload* forzato se è la stessa pagina, così da mostrare il post aggiornato); altrimenti ➡ messaggio di errore
4. in caso di errore, il `responseText` restituito dal backend viene mostrato così com'è all'utente, dentro la modale

⚠️ La risposta del backend in caso di errore è pensata per essere leggibile dall'utente finale (vedi [textErrorResponse](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/ForumController.php)): i messaggi delle eccezioni sollevate dai service sono in italiano e già formattati (anche con HTML).


## L'endpoint Symfony

[ForumController::newIssue](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/ForumController.php) (route `app_forum_new_issue`, `POST forum-integration/ajax/new-issue`) è un *thin controller*: tutta la logica vive in [src/Service/Issue.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Issue.php). Esegue in sequenza:

1. `ajaxOnly()` + `loginRequired()` ➡ l'endpoint risponde solo a chiamate AJAX di utenti loggati
2. `readGuideRequired()` ➡ verifica che l'utente abbia letto la guida (vedi sotto)
3. `rateLimiting()` ➡ limita il numero di segnalazioni (vedi sotto)
4. `createFromForumPostId()` ➡ crea la issue su GitHub e registra il `Bug`
5. `updatePost()` ➡ aggiorna il post sul forum

Se in un qualsiasi punto viene sollevata un'eccezione, la risposta è il relativo messaggio in formato testo, che il frontend mostra all'utente. In caso di successo, il *body* della risposta è l'URL del post (quello che il client validerà e userà per il redirect).


## Controlli preliminari: guida e rate limiting

Prima di creare la issue su GitHub, ci sono due check:

1. **Lettura della guida** (`readGuideRequired`): l'utente deve aver visitato l'articolo-guida ([turbolab.it/49](https://turbolab.it/49), `Article::ID_ISSUE_REPORT`) negli ultimi [`READ_GUIDE_AGAIN_AFTER_DAYS` giorni](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Issue.php). La verifica cerca una `Visit` recente di quell'utente su quell'articolo; in sua assenza viene sollevata un'eccezione con un messaggio che invita (di nuovo) a leggere la guida prima di riprovare. L'obiettivo è ridurre le segnalazioni di scarsa qualità
2. **Rate limiting** (`rateLimiting`): **solo in `prod`**, se l'utente ha già creato [`TIME_LIMIT_BUGS_NUM` issue](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Repository/BugRepository.php) negli ultimi [`TIME_LIMIT_MINUTES` minuti](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Repository/BugRepository.php), la richiesta viene rifiutata con `429 Too Many Requests`, indicando l'orario a partire dal quale riprovare. La query è in [BugRepository::getRecentByAuthor](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Repository/BugRepository.php) e conteggia le segnalazioni per `user_id` **oppure** per indirizzo IP.


## Creazione della issue su GitHub

`createFromForumPostId` ([Issue.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Issue.php)):

1. carica l'Entity del post dal forum; `404` se non esiste
2. **ri-verifica** che il forum sia tra quelli ammessi (`ID_TLI`, `ID_COMMENTS`): è la stessa condizione del template, ma qui applicata lato server, perché il `postId` arriva dal client
3. costruisce il **titolo** della issue dal *subject* del post (decodificato da HTML, con l'eventuale "Re: " iniziale rimosso)
4. costruisce il **corpo** con un link markdown al post originale e i nomi dell'autore del post e dell'autore della segnalazione
5. invoca [GitHub::createIssue](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/GitHub.php)

[src/Service/GitHub.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/GitHub.php) esegue `POST https://api.github.com/repos/{path}/issues` con header `Authorization: Bearer {token}`. Il repository (`path`) e il token sono configurati in [config/services.yaml](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/services.yaml) a partire dalle variabili d'ambiente `GITHUB_PATH` e `GITHUB_ACCESS_TOKEN_ISSUES`.

Due dettagli importanti:

- **fuori da `prod` la chiamata è cortocircuitata**: `GitHub::createIssue` ritorna una issue fittizia (numero `00-test`) *senza* contattare GitHub. Così gli ambienti di sviluppo e i test non creano issue reali sul repository
- in ambienti diversi da `prod` il titolo viene prefissato con un *tag* d'ambiente (es. `[DEV] `) da `getEnvTag()`


## Registrazione locale: l'Entity Bug

Ogni issue creata viene salvata come [Entity/Bug](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Entity/Bug.php) nel database del CMS. La riga conserva: l'utente, l'indirizzo IP, l'id remoto (`remoteId`, il *number* della issue su GitHub), l'URL remoto (`remoteUrl`, l'`html_url`), il post di origine e i timestamp (`TimestampableEntity`).

Questa tabella ha un duplice scopo: è il *log* delle segnalazioni create ed è la fonte dati per il rate limiting descritto sopra.


## Aggiornamento del post sul forum

Una volta creata la issue su GitHub, `updatePost` ([Issue.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Issue.php)) deve aggiungere al post originale un link alla issue

![issue-created](https://turbolab.it/immagini/reg/6/forum-issue-report-02-26613.avif)

Ma **le Entity di phpBB sono in sola lettura dal lato Symfony** (vedi [docs/users.md](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/users.md) e la nota sullo `schema_filter` di Doctrine): modificare un post rispettando *tutte* le regole di phpBB (parsing del BBCode, `message_md5`, *bitfield*, stato di approvazione, ecc.) richiede il runtime di phpBB.

Per questo `updatePost` non scrive direttamente nel database, ma esegue una `POST` verso `https://<dominio>/issue-add-to-post/`, che [config/custom/nginx.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/nginx.conf) riscrive sulla *special page* [public/special-pages/issues.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/special-pages/issues.php). La chiamata usa `verify_peer`/`verify_host` = `false`, perché il backend contatta sé stesso via HTTPS (in dev il certificato può essere *self-signed*).

[public/special-pages/issues.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/special-pages/issues.php):

- **risponde solo a richieste provenienti da `127.0.0.1`** (`403` altrimenti). È un punto delicato — modifica un post per conto di un utente senza autenticarlo a sua volta — perciò l'unico chiamante legittimo è il backend Symfony sullo stesso host, e la pagina non è raggiungibile dall'esterno. Il commento in cima al file documenta il *contratto* interno e un esempio di chiamata `curl` da localhost
- esegue il *bootstrap* di phpBB e carica post/topic/forum/autore
- prende il BBCode grezzo del post, vi appende in fondo `🪲 [url=...]Issue #N su GitHub[/url]`, lo ri-processa con `parse_message` e salva la modifica con `submit_post('edit', ...)`, indicando come motivo della modifica "Link to GitHub issue #N" e come autore della modifica l'utente che ha aperto la segnalazione
- risponde `200` con l'URL del post aggiornato

Se questo secondo passaggio fallisce **dopo** che la issue è già stata creata su GitHub, `updatePost` solleva un'eccezione con un messaggio dedicato: avvisa l'utente che la issue è stata creata correttamente (ringraziandolo) ma che l'aggiornamento del post è fallito, invitandolo ad aprire una nuova discussione per segnalare quest'ultimo problema.


## Perché due "salti" lato server

Il flusso passa dal client al backend Symfony, e da questo a una special page PHP, prima di completarsi. I motivi:

- phpBB è il sistema utenti ed è il proprietario dei post: il sito non può riscriverne i contenuti mantenendone l'integrità, quindi serve una special page che operi **nel contesto di phpBB**. È lo stesso motivo per cui anche il login passa da una special page dedicata (vedi [docs/users.md](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/users.md))
- quella special page compie un'azione privilegiata **senza autenticazione propria**, perciò la si blinda accettando solo connessioni da `127.0.0.1`: l'unico chiamante è il backend del sito, sullo stesso host
- la creazione della issue su GitHub è cortocircuitata fuori produzione, così sviluppo e test non sporcano il repository reale con segnalazioni fittizie
