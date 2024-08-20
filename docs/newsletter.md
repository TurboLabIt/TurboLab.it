# [Gestione newsletter](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/newsletter.md)

La newsletter di TurboLab.it, chiamata *Questa settimana su TLI*, è una email inviata settimanalmente agli iscritti.

Il mittente della mail è `TurboLab.it <newsletter@turbolab.it>`, *alias* della mailbox `info@turbolab.it`.

La newsletter raccoglie i link a tutti i contenuti pubblicati sul sito nel corso della settimana. Nello specifico, contiene i link a:

- articoli e notizie pubblicati o ri-pubblicati sul sito
- discussioni del forum: iniziate, oppure che hanno ricevuto nuove risposte, durante la settimana

Ogni volta che viene spedita la newsletter, viene generato e pubblicato automaticamente un articolo sul sito con i medesimi contenuti.


## Informazioni/guida per utenti finali

La guida per utenti finali è disponibile qui: [Ricevere "TurboLab.it" via email: Come dis/iscriversi dalla newsletter](https://turbolab.it/402).

L'archivio completo di tutti gli invii è il [tag "newsletter turbolab.it"](https://turbolab.it/newsletter-turbolab.it-1349).


## Priorità

Le priorità principali nella gestione della newsletter sono:

- **ottenere un servizio completamente automatico**: generazione e invio devono avvenire automaticamente, senza bisogno di alcuna conferma o intervento esterni
- offrire a quanti più utenti possibile l'occasione di ricevere almeno una volta la newsletter per valutare se possa loro interessare
- assicurarci che chi non vuole ricevere la newsletter non la riceva più, senza ostacoli o ritardi

La newsletter viene generata sul server di TurboLab.it e inviata direttamente alle mailbox degli iscritti tramite il servizio SMTP in esecuzione sul server stesso. Abbiamo dunque scelto di **non utilizzare servizi esterni**, per i seguenti motivi:

1. non dipendere dalla disponibilità di un servizio sul quale non abbiamo alcun controllo
2. evitare i costi dei servizi esterni di invio mail, che oltretutto crescono con il numero di invii
3. evitare di condividere gli indirizzi email degli iscritti con aziende terze


## Iscrizione alla newsletter

L'iscrizione alla newsletter dei singoli utenti è gestita tramite l'attributo `Gli amministratori possono inviarti email`, nativo di phpBB e accessibile all'utente tramite il proprio profilo del forum

![image](https://turbolab.it/immagini/max/ricevere-turbolab.it-via-email-come-dis-iscriversi-newsletter-iscrizione-newsletter-2480.img)

Non è dunque possibile "registrarsi alla newsletter" senza "registrarsi al forum" ([issue #18](https://github.com/TurboLabIt/TurboLab.it/issues/18)).

È però possibile che un utente registrato al forum scelga di non ricevere la newsletter.

L'iscrizione alla newsletter viene attivata per la prima volta al momento della registrazione al forum, poiché l'attributo in questione è valorizzato di default a `true` per impostazione predefinita di phpBB.


## Visualizzazione in anteprima

È possibile visualizzare in anteprima la prossima uscita della newsletter tramite la pagina [/newsletter/anteprima](https://turbolab.it/newsletter/anteprima):

- se l'utente che visualizza la pagina è loggato al sito ➡ la pagina di anteprima utilizza il suo nome utente e la sua email
- se l'utente NON è loggato ➡ vengono mostrati i dati dell'[utente System](https://turbolab.it/forum/memberlist.php?mode=viewprofile&u=5103). In tal caso, il link di dis-iscrizione non funziona realmente

La pagina di anteprima non invia nessuna email, ma mostra soltanto il contenuto nel browser web.


## Comando di invio

La newsletter viene spedita periodicamente agli iscritti tramite *cron* ([staging](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/staging/cron) | [prod](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/prod/cron)).

Il comando utilizzato è [scripts/newsletter-send.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/newsletter-send.sh), che a sua volta esegue [Command/NewsletterSendCommand.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/NewsletterSendCommand.php).

Se invocato senza parametri, il comando invia una sola copia della newsletter all'utente `System <info+system@turbolab.it>`. A seconda di come è configurato l'ambiente, la mail potrebbe arrivare nella mailbox indicata oppure su [mailtrap.io](https://mailtrap.io/inboxes/974437/messages).

Se viene specificata l'opzione `--dry-run`, il comando non invia nessuna mail, ma simula solo l'esecuzione della procedura.


## Rilevazione dei click sui link

Vogliamo fare il possibile per **disattivare immediatamente l'invio della newsletter a coloro che non vogliano riceverla**. Oltre ai link di dis-iscrizione presenti nel corpo di ogni email, è attivo un meccanismo che auto-annulla l'iscrizione se l'utente non clicca mai alcun link presente nella newsletter per un determinato numero di mesi consecutivi.

Allo scopo, ogni link presente nella newsletter è inserito come parametro della pagina `/newsletter/open`. Tale pagina riceve due parametri:

1. `opener=...`: è la versione crittografata dell'ID utente al quale è stata inviata la specifica mail
2. `url=...`: è l'URL "finale" che deve essere mostrato quando l'utente clicca su quel link

Un esempio (non-funzionante) del link che conduce alla homepage è il seguente:

`https://turbolab.it/newsletter/open?opener=Mg...pw%3D&url=https%3A%2F%2Fturbolab.it`

La pagina `/newsletter/open` si occupa di:

1. de-crittografare il parametro `opener=`, ricavando l'ID dell'utente
2. inserire/aggiornare il record relativo all'utente nella tabella `newsletter_opener`
3. eliminare l'eventuale record presente nella tabella `newsletter_expiring_warn` che indica la data nella quale l'utente è stato avvisato che la sua iscrizione sarebbe stata disattivata a breve per inattività
4. effettuare un redirect verso l'URL "reale", presente nel parametro `url=`


## Avviso di imminente dis-iscrizione automatica

La procedura [NewsletterWarnInactive](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/NewsletterWarnInactiveCommand.php) si occupa periodicamente di avvisare gli utenti iscritti alla newsletter, ma che non abbiano mai cliccato alcun link, che la loro iscrizione sarà annullata presto se non la confermano.

**Vogliamo minimizzare l'invio di queste email**, che creano comunque "rumore" nella casella dell'utente e sono per lo più inviate come cortesia nei confronti dell'utente che per altro.

La procedura inizia con un'attività di manutenzione che consiste nell'estrarre tutti gli utenti NON iscritti alla newsletter. Per ognuno di loro:

- viene eliminata ogni riga dalla tabella `newsletter_opener`
- viene eliminata ogni riga dalla tabella `newsletter_expiring_warn`

Da questa situazione "pulita", la procedura seleziona gli utenti che soddisfino tutti i seguenti criteri:

- sono iscritti al sito da almeno [APP_NEWSLETTER_SUBSCRIPTION_EXPIRE_WARN_MONTHS](https://github.com/TurboLabIt/TurboLab.it/blob/main/.env) mesi
- sono iscritti alla newsletter
- non hanno una entry nella tabella `newsletter_opener` aggiornata prima di [APP_NEWSLETTER_SUBSCRIPTION_EXPIRE_WARN_MONTHS](https://github.com/TurboLabIt/TurboLab.it/blob/main/.env) mesi fa
- non hanno una entry nella tabella `newsletter_expiring_warn`

A ognuno degli utenti selezionati, la procedura invia una email di avviso: se non confermeranno l'iscrizione alla newsletter cliccando su un link presente nella mail stessa, la loro iscrizione verrà annullata. Il link proposto usa `/newsletter/open` per condurre a una pagina di conferma.

La procedura salva poi una entry relativa all'invio nella tabella `newsletter_expiring_warn`, di modo che l'utente non venga avvisato nuovamente alla prossima esecuzione.


## Auto-annullamento dell'iscrizione

La procedura [NewsletterUnsubscribeInactive](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/NewsletterUnsubscribeInactiveCommand.php) si occupa periodicamente di estrarre tutti gli utenti che abbiano una entry nella tabella `newsletter_expiring_warn` più vecchia di [APP_NEWSLETTER_SUBSCRIPTION_EXPIRE_AFTER_WARN_MONTHS](https://github.com/TurboLabIt/TurboLab.it/blob/main/.env). Ognuno di questi utenti:

- viene dis-iscritto dalla newsletter
- riceve una email che lo informa della dis-iscrizione e offre un link per ri-iscriversi

Come parte della procedura di dis-iscrizione vengono inoltre eliminate tutte le righe relative dalle tabelle `newsletter_opener` e `newsletter_expiring_warn`.


## Tecnologie utilizzate per il template HTML

- il template della newsletter ([email/newsletter.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/email/newsletter.html.twig)) utilizza il CSS fornito da [📚 Foundation for Emails](https://get.foundation/emails.html)
- il markup del template HTML è scritto in [📚 Inky](https://get.foundation/emails/docs/inky.html)
- il CSS viene spostato *in-line* tramite il filtro `inline_css`, fornito da `twig/cssinliner-extra`
- il post-processing ("building") di Inky in HTML viene svolto dal filtro Twig `inky_to_html`, fornito da `twig/inky-extra`
