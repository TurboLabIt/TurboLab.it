# [Gestione newsletter](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/newsletter.md)

La newsletter di TurboLab.it, chiamata *Questa settimana su TLI*, è una email inviata settimanalmente agli iscritti.

Il mittente della mail è impostato nel servizio [Newsletter.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Newsletter.php), ed è un *alias* della mailbox `info@tli`.

La newsletter raccoglie i link a tutti i contenuti pubblicati sul sito nel corso della settimana:

- articoli e notizie usciti (o ri-usciti) in home page
- discussioni del forum: iniziate, oppure che hanno ricevuto nuove risposte
- ultimi video da YouTube (in ordine temporale, quindi non solo quelli pubblicati in settimana)

Ogni volta che viene spedita la newsletter, viene generato e pubblicato automaticamente un articolo sul sito con i medesimi contenuti.


## Informazioni/guida per utenti finali

La guida per utenti finali è disponibile qui: [Ricevere "TurboLab.it" via email: Come dis/iscriversi dalla newsletter](https://turbolab.it/402).

L'archivio completo di tutti gli invii è il [tag "newsletter turbolab.it"](https://turbolab.it/newsletter-turbolab.it-1349).


## Priorità

Le priorità principali nella gestione della newsletter sono:

- **automazione totale**: generazione e invio devono avvenire automaticamente, senza bisogno di alcuna conferma o interventi esterni
- offrire a quanti più utenti possibile l'occasione di ricevere la newsletter almeno una volta, di modo che possano valutare se interessa
- **NO SPAM**! Chi non vuole la newsletter non deve riceverla, senza ostacoli o ritardi

La newsletter viene generata sul server di TurboLab.it, ma **inviata** agli iscritti tramite il servizio esterno [SMTP2Go](https://www.smtp2go.com/) (transport `newsletter` in [mailer.yaml](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/packages/mailer.yaml), configurato tramite `MAILER_DSN_NEWSLETTER`).

In origine si inviava direttamente dall'SMTP del server (per non dipendere da terzi, risparmiare sui costi e non condividere gli indirizzi degli iscritti), ma si è rivelato impossibile ottenere un buon tasso di *delivery*. Dopo un tentativo con [Postmark](https://postmarkapp.com/), la scelta attuale è SMTP2Go.


## Iscrizione alla newsletter

L'iscrizione alla newsletter dei singoli utenti è gestita tramite l'attributo `Gli amministratori possono inviarti email`, nativo di phpBB e [accessibile da ogni utente tramite il proprio pannello di controllo sul forum](https://turbolab.it/forum/ucp.php?i=174) (scheda `Preferenze`, gruppo `Preferenze globali`).

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

La newsletter viene spedita periodicamente agli iscritti tramite [prod/cron](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/prod/cron).

Il comando utilizzato è [scripts/newsletter-send.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/newsletter-send.sh), che a sua volta esegue [Command/NewsletterSendCommand.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/NewsletterSendCommand.php).

**Per inviare manualmente la newsletter** tramite lo script, impartire:

- `bash scripts/newsletter-send.sh`: invia la newsletter solo a `System <info.system@tli>`. A seconda di come è configurato l'ambiente, la mail potrebbe arrivare nella mailbox indicata oppure su [mailtrap.io](https://mailtrap.io/inboxes/974437/messages)
- **⚠⚠** `bash scripts/newsletter-send.sh --real-recipients --send-messages`: invia la newsletter a tutti gli iscritti

Se viene specificata l'opzione `--dry-run`, il comando non invia nessuna mail, ma simula solo l'esecuzione della procedura.


## Gestione email con errori (bounce)

Quando la mailbox collegata all'invio della newsletter (`newsletter@tli`) riceve una *Delivery status notification* in risposta all'invio di una email:

1. un filtro impostato nella webmail inoltra il messaggio verso una casella dedicata su [gmx.com](https://www.gmx.com/)
2. il comando [scripts/email-bounce-manager.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/email-bounce-manager.sh) (ovvero [Command/EmailBounceManagerCommand.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/EmailBounceManagerCommand.php)) si collega alla casella GMX via IMAP, estrae gli indirizzi problematici e li dis-iscrive

Tale comando viene eseguito quotidianamente via [prod/cron](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/prod/cron), ma può anche essere lanciato manualmente.


## Tecnologie utilizzate per il template HTML

- il template della newsletter ([newsletter/email.html.twig](https://github.com/TurboLabIt/TurboLab.it/blob/main/templates/newsletter/email.html.twig)) utilizza il CSS fornito da [📚 Foundation for Emails](https://get.foundation/emails.html)
- il markup del template HTML è scritto in [📚 Inky](https://get.foundation/emails/docs/inky.html)
- il CSS viene spostato *in-line* tramite il filtro `inline_css`, fornito da `twig/cssinliner-extra`
- il post-processing ("building") di Inky in HTML viene svolto dal filtro Twig `inky_to_html`, fornito da `twig/inky-extra`


## Spotlight per la versione web

L'articolo pubblicato sul sito corrispondente a ogni newsletter richiede uno spotlight. La procedura di generazione sceglie automaticamente un'immagine fra quelle definitine in [Image::IDS_NEWSLETTER_SPOTLIGHT](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Cms/Image.php).

Tali immagini sono state generate manualmente tramite AI:

Caricare questa immagine:  https://turbolab.it/images/logo/turbolab.it.png e aggiungere il seguente prompt:

> Genera l'immagine di un'ipotetica newsletter cartacea spedita dal sito "TurboLab.it". Come logo/titolo, usa l'immagine che ti allego.
> Il tema della newsletter deve essere "Guide PC, Windows, Linux, Android e Bitcoin". Assicurati che siano presenti immagini di computer, laptop e tablet dall'aspetto moderno. Presta la MASSIMA ATTENZIONE a non distorcere i caratteri.
> La newsletter è appoggiata sul tavolo di una scrivania da ufficio, con una luce calda e accogliente.
> Le dimensioni complessive dell'immagine generata devono essere esattamente 1920x1080 pixel. L'immagine generata deve essere fotorealistica e ad alta definizione.


## Rilevazione dei click sui link

Vogliamo fare il possibile per disattivare immediatamente l'invio della newsletter a coloro che non la vogliano ricevere.

Oltre ai link di dis-iscrizione presenti nel corpo di ogni email, l'obiettivo è auto-annullare l'iscrizione di chi non clicca alcun link per molti mesi consecutivi.

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


## Avviso di imminente dis-iscrizione automatica 🔧 *(pianificato, non ancora attivo)*

La procedura `NewsletterWarnInactiveCommand` (da creare) si occuperà periodicamente di avvisare gli utenti iscritti alla newsletter, ma che non clicchino su alcun link da molto tempo, che la loro iscrizione sarà annullata, se non la confermano.

**Vogliamo minimizzare l'invio di queste email**, che creano comunque "rumore" nella casella dell'utente e sono per lo più inviate come cortesia nei confronti di utenti che, per altro, o hanno abbandonato la mailbox in questione oppure non sono interessati alla newsletter.

La procedura inizia con un'attività di manutenzione che consiste nell'estrarre tutti gli utenti NON iscritti alla newsletter. Per ognuno di loro:

- viene eliminata ogni riga dalla tabella `newsletter_opener`
- viene eliminata ogni riga dalla tabella `newsletter_expiring_warn`

Da questa situazione "pulita", la procedura seleziona gli utenti che soddisfino tutti i seguenti criteri:

- sono iscritti al sito da almeno [APP_NEWSLETTER_SUBSCRIPTION_EXPIRE_WARN_MONTHS](https://github.com/TurboLabIt/TurboLab.it/blob/main/.env) mesi
- sono iscritti alla newsletter
- non hanno una entry nella tabella `newsletter_opener` aggiornata prima di [APP_NEWSLETTER_SUBSCRIPTION_EXPIRE_WARN_MONTHS](https://github.com/TurboLabIt/TurboLab.it/blob/main/.env) mesi fa
- non hanno una entry nella tabella `newsletter_expiring_warn`

A ognuno degli utenti selezionati, la procedura invia una email di avviso: "se non confermi l'iscrizione alla newsletter cliccando sul link presente nella mail stessa, verrai dis-iscritto". Il link proposto usa `/newsletter/open` per condurre a una pagina di conferma.

La procedura salva poi una entry relativa all'invio nella tabella `newsletter_expiring_warn`, di modo che l'utente non venga avvisato nuovamente alla prossima esecuzione.


## Auto-annullamento dell'iscrizione 🔧 *(pianificato, non ancora attivo)*

La procedura `NewsletterUnsubscribeInactiveCommand` (da creare) si occuperà periodicamente di estrarre tutti gli utenti che abbiano una entry nella tabella `newsletter_expiring_warn` più vecchia di [APP_NEWSLETTER_SUBSCRIPTION_EXPIRE_AFTER_WARN_MONTHS](https://github.com/TurboLabIt/TurboLab.it/blob/main/.env). Ognuno di questi utenti:

- viene dis-iscritto dalla newsletter
- riceve una email che lo informa della dis-iscrizione e offre un link per ri-iscriversi

Come parte della procedura di dis-iscrizione vengono inoltre eliminate tutte le righe relative dalle tabelle `newsletter_opener` e `newsletter_expiring_warn`.
