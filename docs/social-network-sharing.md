# [Procedura di condivisione sui social network](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/social-network-sharing.md)

Gli articoli pubblicati su TurboLab.it devono essere condivisi su piattaforme esterne come:

- social network (Facebook, X (Twitter), ...)
- servizi di messaggistica (WhatsApp, Telegram, ...)

🛑 In realtà, noi dobbiamo sempre **incoraggiare il pubblico** affinché:

- **ci segua tramite servizi indipendenti da terze parti**, come [newsletter](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/newsletter.md) oppure [feed RSS](https://turbolab.it/feed)
- **interagisca con noi tramite [il forum](https://turbolab.it/forum/)**

Ciò detto: le presenze social devono comunque essere alimentate.


## Informazioni/guida per utenti finali

La lista completa dei nostri canali social è esposta [in cima alla colonna della homepage](https://turbolab.it/) (sono le icone nella sezione `Seguici`).


## Lista dei canali di test

Gli ambienti *non-prod* pubblicano gli articoli su canali social di test, diversi da quelli reali. La lista di tali canali è nel file [.env.dev](https://github.com/TurboLabIt/TurboLab.it/blob/main/.env.dev).


## Priorità

Le priorità nella condivisione dei contenuti di TLI sulle pagine social sono:

- **automazione totale**: la condivisione deve avvenire automaticamente, senza bisogno di alcuna conferma o interventi esterni
- **non utilizzare servizi esterni dedicati**

L'indipendenza da servizi esterni, come *Hootsuite* o *dlvrit*, è necessaria per:

1. non dipendere dalla disponibilità di un servizio sul quale non abbiamo alcun controllo
2. evitare di sviluppare integrazioni specifiche con piattaforme che, storicamente, falliscono o chiudono i piani "free" molto rapidamente
3. evitare i costi dei piani a pagamento


## Configurazione

Il sistema sfrutta il bundle Symfony [turbolabit/php-symfony-messenger](https://github.com/TurboLabIt/php-symfony-messenger) per l'effettivo invio dei contenuti alle piattaforme. I vari file `.env` devono quindi essere configurati come documentato nel bundle. Per il resto non c'è nulla da fare.

Il token per l'accesso a Facebook è particolamente delicato, in quanto tende a scadere dopo pochi mesi. TLI utilizza un token "never expiring", generato seguendo la tediosa procedura [documentata nel bundle](https://github.com/TurboLabIt/php-symfony-messenger/blob/main/docs/facebook.md).


## Comando di invio

La condivisione sulle piattaforme esterne avviene tramite il comando [social-share.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/social-share.sh), che invoca a sua volta [ShareOnSocialCommand](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/ShareOnSocialCommand.php).

Non sono previsti parametri: credenziali ed endpoint vengono letti dal `.env` corrente.

Il comando viene eseguito automaticamente tramite [prod/cron](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/prod/cron). 🛑 È fondamentale assicurarsi che l'intervallo di esecuzione configurato nel cron sia lo stesso valorizzato nella costante [ShareOnSocialCommand::EXEC_INTERVAL](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/ShareOnSocialCommand.php). In caso contrario, l'applicazione non può funzionare correttamente.

ShareOnSocialCommand ha una logica interna per rispettare un "orario del silenzio" (*quiet hours*) e prevenire l'invio dei messaggi di notte. L'orario del silenzio **inizia a mezzanotte** (00:00:00) e finsice alle ore [ShareOnSocialCommand::QUIET_HOURS_END](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/ShareOnSocialCommand.php).

- se si tratta della prima esecuzione della mattina, al termine dell'orario del silenzio ➡ condivide tutti gli articoli pubblicati dall'inizio dell'orario del silenzio sino a ora
- se si tratta di una regolare esecuzione periodica ➡ invia gli articoli pubblicati su TLI negli ultimi [ShareOnSocialCommand::EXEC_INTERVAL](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/ShareOnSocialCommand.php) minuti.


## Valutazione casi-limite esecuzioni regolari

| Pubblicato alle | Orario exec1 | Range exec1         | Risultato exec1 | Orario exec2 | Range exec2         | Risultato exec2 |
|-----------------|--------------|---------------------|:---------------:|--------------|---------------------|:---------------:|
| 11:59:59        | 12:00        | 11:50:00 - 11:59:59 |      HIT ✅      | 12:10        | 12:00:00 - 12:09:59 |      MISS ✅     |
| 12:00:00        | 12:00        | 11:50:00 - 11:59:59 |      MISS ✅     | 12:10        | 12:00:00 - 12:09:59 |      HIT ✅      |
| 12:00:01        | 12:10        | 12:00:00 - 12:09:59 |      HIT ✅      | 12:20        | 12:10:00 - 12:19:59 |      MISS ✅     |
| 12:00:58        | 12:10        | 12:00:00 - 12:09:59 |      HIT ✅      | 12:20        | 12:10:00 - 12:19:59 |      MISS ✅     |


## Valutazione casi-limite prima esecuzione dopo quiet hours

| Pubblicato alle | Orario exec1 | Range exec1         | Risultato exec1 | Orario exec2 | Range exec2         | Risultato exec2 |
|-----------------|--------------|---------------------|:---------------:|--------------|---------------------|:---------------:|
| 11:59:59        | 12:00        | 11:50:00 - 11:59:59 |      HIT ✅      | 12:10        | 12:00:00 - 12:09:59 |      MISS ✅     |
| 12:00:00        | 12:00        | 11:50:00 - 11:59:59 |      MISS ✅     | 12:10        | 12:00:00 - 12:09:59 |      HIT ✅      |
| 12:00:01        | 12:10        | 12:00:00 - 12:09:59 |      HIT ✅      | 12:20        | 12:10:00 - 12:19:59 |      MISS ✅     |
| 12:00:58        | 12:10        | 12:00:00 - 12:09:59 |      HIT ✅      | 12:20        | 12:10:00 - 12:19:59 |      MISS ✅     |
