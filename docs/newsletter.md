# [Gestione newsletter](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/newsletter.md)

La newsletter di TurboLab.it, chiamata *Questa settimana su TLI*, è una email inviata ogni settimana che raccoglie i link a tutti i contenuti pubblicati sul sito durante la settimana. Nello specifico, contiene:

- articoli e notizie pubblicati sul sito
- discussioni dal forum

Gli obbiettivi sono:

- offrire a quanti più utenti possibile l'occasione di ricevere almeno una volta la newsletter
- assicurarci che chi non vuole ricevere la newsletter non la riceva più, immediatamente

La newsletter viene generata sul server di TurboLab.it e inviata direttamente alle mailbox degli iscritti tramite il servizio SMTP. Abbiamo dunque scelto di **non utilizzare servizi esterni** per i seguenti motivi:

1. non dipendere dalla disponibilità di un servizio sul quale non abbiamo alcun controllo
2. evitare i costi dei servizi esterni di invio mail
3. evitare di condividere gli indirizzi email degli iscritti con altri soggetti


## Informazioni per utenti finali

La guida per utenti finali è disponibile qui: [Ricevere "TurboLab.it" via email: Come dis/iscriversi dalla newsletter](https://turbolab.it/402).

L'archivio completo di tutti gli invii è il [tag "newsletter turbolab.it"](https://turbolab.it/newsletter-turbolab.it-1349).


## Iscrizione alla newsletter

L'iscrizione alla newsletter dei singoli utenti è gestita tramite l'attributo `Gli amministratori possono inviarti email` nel profilo utente del forum

![image](https://turbolab.it/immagini/max/ricevere-turbolab.it-via-email-come-dis-iscriversi-newsletter-iscrizione-newsletter-2480.img)

Non è dunque possibile "registrarsi alla newsletter" senza "registrarsi al forum". È però possibile che un utente registrato al forum abbia scelto di non ricevere la newsletter.

L'iscrizione alla newsletter viene attivata per la prima volta al momento della registrazione, poiché l'attributo in questione è valorizzato di default a `true` per impostazione predefinita di phpBB.


## Visualizzazione in anteprima

È possibile visualizzare in anteprima la prossima uscita della newsletter tramite la pagina [/newsletter/anteprima](https://turbolab.it/newsletter/anteprima). Questa pagina è disponibile pubblicamente, ma è pensata principalmente per ottenere una *preview* durante lo sviluppo.

- se l'utente che visualizza la pagina è loggato al sito ➡ la pagina di anteprima utilizza il suo nome utente e la sua email
- se l'utente NON è loggato ➡ vengono mostrati i dati dell'utente `Zane`. In tal caso, il link di dis-iscrizione non funziona realmente

La pagina di anteprima non invia alcuna email, ma mostra soltanto il contenuto nel browser web.


## Comando di invio

La newsletter viene spedita via email agli scritti settimanalmente da [config/custom/cron](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/cron).

Il comando utilizzato è [scripts/newsletter-send.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/newsletter-send.sh), che a sua volta esegue [Command/NewsletterCommand.php](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/NewsletterCommand.php).

Se invocato senza parametri, il comando invia una sola copia della newsletter all'utente `Zane <info@turbolab.it>`. A seconda di come è configurato l'ambiente, la mail potrebbe arrivare su [mailtrap.io](https://mailtrap.io/inboxes/974437/messages).

Se è specificata l'opzione `--dry-run`, il comando non invia nessuna mail, ma simula solo l'esecuzione della procedura.




## Rilevazione dei click sui link

Vogliamo fare il possibile per **disattivare immediatamente l'invio della newsletter a coloro che non siano interessati a riceverla**. Oltre ai link di dis-iscrizione nel corpo di ogni email, abbiamo implementato un meccanismo che auto-annulla l'iscrizione se l'utente non clicca alcun link presente nella newsletter per un determinato numero di mesi.

Allo scopo, ogni link presente nella newsletter è inserito come parametro della pagina `/newsletter/open`. Tale pagina riceve due parametri:

1. `opener=...`: è la versione crittografata dell'ID utente al quale è stata inviata la specifica mail
2. `url=...`: è l'URL "finale" che deve essere mostrato quando l'utente clicca su quel link

Un esempio (non-funzionante) del link che conduce alla homepage è il seguente:

`https://turbolab.it/newsletter/open?opener=Mg...pw%3D&url=https%3A%2F%2Fturbolab.it`

La pagina `/newsletter/open` si occupa di:

1. de-crittografare il parametro `opener=`, ricavando l'ID dell'utente
2. aggiornare il campo `newsletter_last_link_clicked_at` del profilo phpBB dell'utente in questione, valorizzandolo con la data corrente
3. impostare a `<null>` il campo `newsletter_inactive_warning_sent_at` che indica la data nella quale sono stati avvisati che la loro iscrizione sarebbe stata disattivata a breve
4. effettuare un redirect verso l'URL "reale", presente nel parametro `url=`


## Auto-annullamento dell'iscrizione

La procedura [NewsletterUnsubscribeInactive](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/NewsletterUnsubscribeInactiveCommand.php) si occupa periodicamente di:

1. estrarre tutti gli utenti non-iscritti alla newsletter
2. impostare a `<null>` il loro campo `newsletter_last_link_clicked_at`
3. impostare a `<null>` il loro campo `newsletter_inactive_warning_sent_at`

Fatto ciò, siamo in una situazione "pulita per":

1. estrarre tutti gli utenti iscritti alla newsletter che abbiano `newsletter_last_link_clicked_at` antecedente ad un determinato numero di mesi e `newsletter_inactive_warning_sent_at` valorizzato a `<null>`
2. inviare a costoro una email di avviso: se non confermeranno l'iscrizione alla newsletter, cliccando su un link presente nella mail stessa, la loro iscrizione verrà annullata. Il link proposta usa `/newsletter/open` per condurre a una pagina di conferma
3. aggiornare il campo `newsletter_inactive_warning_sent_at` del profilo phpBB dell'utente in questione, valorizzandolo con la data corrente

Una routine successiva della stessa procedura si occupa poi di:

1. estrarre tutti gli utenti iscritti alla newsletter che abbiano `newsletter_inactive_warning_sent_at` antecedente ad un determinato numero di giorni
2. disattivare la loro iscrizione alla newsletter
3. impostare a `<null>` il campo `newsletter_last_link_clicked_at`
4. impostare a `<null>` il campo `newsletter_inactive_warning_sent_at`
5. inviare una email di notifica disattivazione iscrizione newsletter
