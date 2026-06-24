# [Gestione utenti](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/users.md)

Gli utenti registrati a TurboLab.it sono conservati nella tabella `phpbb_users`, all'interno del database dedicato al forum (`turbolab_it_forum`). Quando un utente "si registra al sito", in realtà, si "registra al forum", tramite il flusso di registrazione proprio di phpBB.

Il sito deve quindi integrare la tabella `phpbb_users` di phpBB e il relativo sistema di login.

Il requisito è che gli utenti possano eseguire login/logout sia dal sito, sia dal forum.


## Integrazione della tabella `phpbb_users` con il sito

Per accedere alla tabella degli utenti dal sito:

1. l'applicazione mappa la tabella `phpbb_users` tramite [Entity/User](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Entity/PhpBB/User.php) e relativo [UserRepository](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Repository/PhpBB/UserRepository.php)
2. per evitare che le migration agiscano sulla tabella di phpBB (come avverrebbe con qualsiasi altra Entity), la tabella è esclusa tramite [config/packages/doctrine.yaml](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/packages/doctrine.yaml) (parametro `schema_filter` + `schema_ignore_classes`)

In una prima implementazione si era tentato di [usare una *view*](https://github.com/TurboLabIt/TurboLab.it/commit/15d60324d2027e404dcbb102a876295f4b5bb74a#diff-9e8d1f28092b733b6d0067fdf5c74d12980ec1ba992f9cd74d3259980aba02d7) al posto di accedere alla tabella `phpbb_users` direttamente. Ma non è possibile creare *foreign key* verso una view, e quindi l'elemento *Author* delle relazioni non era referenziabile con garanzia di *integrità referenziale*.


## Analisi del sistema di login di phpBB

Quando l'utente apre una pagina del forum per la prima volta, senza essere loggato, il client riceve tre cookie (il cui prefisso è personalizzabile da `ACP -> General -> Cookie settings`):

- `_k`: è la `session_key` (un *secret*). Valorizzata a `null` fino a quando l'utente non si logga abilitando l'opzione "remember me"
- `_sid`: è la `session_id`, salvata nella tabella `phpbb_sessions`. Viene generato immediatamente e assegnato al client al primo caricamento di pagina del forum, anche se il client non fa login
- `_u`: è lo `user_id` dell'utente. Valorizzato a `1` (utente `Anonymous`) fino a quando il client non esegue login

L'utente può [eseguire login](https://turbolab.it/forum/ucp.php?mode=login) tramite username e password, attivando o disattivando le opzioni:

- `Ricordami` (default: disattivato)
- `Nascondi il mio stato per questa sessione` (default: disattivato)

Dopo il login, i cookie vengono modificati in questo modo:

- `_k`: viene valorizzato solo se l'utente ha attivato "Ricordami", altrimenti resta vuoto
- `_sid`: viene generato un nuovo valore, e quello vecchio viene rimosso dal db
- `_u`: diviene lo `user_id` dell'utente loggato

Relativamente al cookie `_k`, è importante notare che:

- il cookie contiene il *valore* della chiave
- il corrispondente record nella tabella `phpbb_sessions_keys` conserva il suo MD5

Per verificare da linea di comando: `echo -n 'aaabbbccc' | md5sum`.


## Login da sito tramite username e password

Per consentire il login da sito tramite username e password dobbiamo:

1. mostrare al client un form da compilare per effettuare il submit delle credenziali verso il backend
2. utilizzare le credenziali fornite per effettuare login su phpBB
3. istanziare la sessione utente di Symfony

Il login via phpBB è implementabile seguendo [📚 phpBB3 Cross-site Sessions Integration](https://www.phpbb.com/support/docs/en/3.0/kb/article/phpbb3-cross-site-sessions-integration/). Non è però possibile integrare questo codice in un controller Symfony, perché genera l'errore

> Fatal error: Declaration of Symfony\Component\DependencyInjection\ContainerBuilder::compile() must be compatible with Symfony\Component\DependencyInjection\Container::compile(): void in /var/www/turbolab.it/public/forum/vendor/symfony/dependency-injection/ContainerBuilder.php on line 763

Bisogna quindi impiegare un file PHP indipendente: [public/special-pages/login.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/public/special-pages/login.php), che possiamo poi esporre come `https://turbolab.it/ajax/login` tramite [config/custom/nginx.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/nginx.conf).

Tale file:

- esegue login con le funzioni proprie di phpBB
- se l'utente NON ha spuntato "Remember me" ➡ genera il cookie custom `tli-login-no-remember-me-workaround`, con validità di sessione, da utilizzare come "passport"

Tale soluzione, da sola, consente di istanziare la sessione utente di phpBB ed eseguire login al forum dal sito, ma non è sufficiente ad istanziare la sessione utente di Symfony necessaria al sito stesso.


## Login al sito

L'ideale per integrare la sessione utente di phpBB sul sito sarebbe [📚 phpBB3 Sessions Integration](https://www.phpbb.com/support/docs/en/3.0/kb/article/phpbb3-sessions-integration/). Ma c'è lo stesso problema di compatibilità che genera il "fatal error" citato sopra.

Facciamo allora affidamento sui cookie di phpBB (`_sid`, `_u` (e `_k`, se c'è)) e un [📚 custom authenticator Symfony](https://symfony.com/doc/current/security/custom_authenticator.html):

1. [config/packages/security.yaml](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/packages/security.yaml) definisce come `custom_authenticator` del *firewall* `tli_phpbb_cookies` l'autenticatore [Security/phpBBCookiesAuthenticator](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Security/phpBBCookiesAuthenticator.php)
2. tale autenticatore si attiva solo per le route previste dal metodo `supports()`, come `app_home` oppure `app_article`. È dunque importante esplicitare lì tutte le route del sito nelle quali deve essere caricata la sessione utente (nelle route non specificate, l'utente risulterà loggato o non-loggato a seconda che sia stato rilevato come loggato on non-loggato in precedenza da una delle pagine gestite da `supports()`)
3. quando l'autenticatore si attiva, prova a leggere i valori dai cookie di login

Se è presente il cookie `_k` che phpBB scrive solo in caso venga spuntata l'opzione "Remember me" ➡ viene eseguita una query direttamente sulle tabelle di phpBB utilizzando come parametri i tre valori dei cookie `_sid`, `_u` e `_k`.

Se NON è presente il cookie `_k` ➡ viene letto il cookie `tli-login-no-remember-me-workaround` e, se i valori contenuti corrispondono a quelli presenti in `_sid` e `_u`, viene eseguita una query direttamente sulle tabelle di phpBB utilizzandoli come parametri di ricerca.

Le query sono in [UserRepository](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Repository/PhpBB/UserRepository.php). Se viene trovato un match, la funzione istanzia e ritorna un oggetto [Entity/User](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Entity/PhpBB/User.php) e l'utente viene autenticato.

⚠️⚠️⚠️️ La limitazione è che, se l'utente esegue login da phpBB senza spuntare "Ricordami", non viene impostato né il cookie `_k`, né il cookie `tli-login-no-remember-me-workaround` ➡ Il sito non ha informazioni sufficienti per verificare le credenziali e istanziare la sessione utente ➡ **L'utente risulta loggato su phpBB, ma non sul sito** ([🪲 #90](https://github.com/TurboLabIt/TurboLab.it/issues/90), [🪲 #88](https://github.com/TurboLabIt/TurboLab.it/issues/88)).

Per gestire questa limitazione, si potrebbe fare affidamento sui soli cookie `_sid` e `_u`, senza bisogno di `_k`? No, perché il primo è *leaky* (l'URL di logout, ad esempio, è `ucp.php?mode=logout&sid=39...83`, e i link interni, a volte, vengono mostrati come `viewforum.php?f=6&sid=49...50`) e il secondo [è pubblico](https://turbolab.it/forum/memberlist.php?mode=viewprofile&u=2). phpBB lo fa, mitigando il problema con check aggiuntivi su IP di provenienza del client e user-agent, ma è una soluzione poco robusta.

Manteniamo dunque questa limitazione, alla luce del fatto che:

- la issue non è bloccante (basta eseguire login di nuovo al sito una sola volta per essere loggati)
- **consigliavamo già prima di attivare sempre "Ricordami" per evitare disconnessioni durante la navigazione**

Per incoraggiare gli utenti a spuntare l'opzione "Remember me", [la casella è ora spuntata di default](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/forum.js) (`$("#autologin").prop("checked", true)` nell'entry point `forum`).


## Logout

Se l'utente esegue [logout dal forum](https://turbolab.it/forum/ucp.php?mode=logout), phpBB invalida i cookie di login => quando il sito prova a leggerli, l'autenticazione fallisce.

Se l'utente esegue [logout dal sito](https://turbolab.it/logout):

1. poiché [Security/phpBBCookiesAuthenticator](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Security/phpBBCookiesAuthenticator.php) ha un listener per `LogoutEvent` (metodo `getSubscribedEvents()`), viene invocato il relativo metodo `removeAllCookies`
2. `removeAllCookies` si occupa di cancellare tutti i cookie, compresi quelli di phpBB
3. senza cookie, il sistema di login non funziona e l'utente si ritrova non-loggato
