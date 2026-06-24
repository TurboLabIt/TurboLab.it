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

ShareOnSocialCommand ha una logica interna per rispettare un "orario del silenzio" (*quiet hours*) e prevenire l'invio dei messaggi di notte. L'orario del silenzio **inizia a mezzanotte** (00:00:00) e finisce alle ore [ShareOnSocialCommand::QUIET_HOURS_END](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/ShareOnSocialCommand.php).

- se si tratta della prima esecuzione della mattina, al termine dell'orario del silenzio ➡ condivide tutti gli articoli pubblicati dall'inizio dell'orario del silenzio sino a ora
- se si tratta di una regolare esecuzione periodica ➡ invia gli articoli pubblicati su TLI negli ultimi [ShareOnSocialCommand::EXEC_INTERVAL](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/ShareOnSocialCommand.php) minuti.


## Valutazione casi-limite

L'idoneità di un articolo è valutata su un intervallo **semi-aperto**, con i secondi azzerati: `publishedAt >= lowLimit AND publishedAt < highLimit` (vedi [`ArticleRepository::findLatestForSocialSharing`](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Repository/Cms/ArticleRepository.php)), dove:

- `highLimit` = orario del run;
- `lowLimit` = `highLimit` − `EXEC_INTERVAL` (15 min). **Eccezione**: nel primo run del giorno (`08:00`) `lowLimit` = mezzanotte, per recuperare tutto ciò che è stato pubblicato durante le quiet hours.

Escludere `highLimit` (`<`) evita che due run consecutivi condividano lo stesso articolo. Esiti nei casi-limite, con gli attuali `EXEC_INTERVAL` = 15 min e quiet hours `00:00`–`08:00` (`QUIET_HOURS_END` = 8):

| Articolo pubblicato alle | Condiviso dal run | Note |
|---|---|---|
| `12:07:30` | `12:15` | caso normale: rientra in `[12:00:00, 12:15:00)` |
| `12:00:00` (sul quarto d'ora) | `12:15` | `highLimit` è escluso → l'orario esatto va sempre al run *successivo* |
| `12:14:59` | `12:15` | ultimo istante della finestra |
| `12:15:00` (sul quarto d'ora) | `12:30` | come sopra: lo scatto esatto slitta al run successivo |
| `00:00:00` (mezzanotte) | `08:00` (primo run) | incluso: il primo run copre `[00:00:00, 08:00:00)` |
| `07:59:59` (quiet hours) | `08:00` (primo run) | tutto il pubblicato durante le quiet hours è recuperato dal primo run |
| `08:00:00` (fine quiet hours) | `08:15` | escluso dal primo run (`< 08:00:00`), preso dal run delle `08:15` |
| `23:44:59` | `23:45` (ultimo run) | ultimo istante coperto dall'ultimo run del giorno |
| da `23:45:00` a `23:59:59` | 🛑 **nessuno** | l'ultimo run (`23:45`) arriva solo a `23:44:59`; il primo run del giorno dopo riparte da `00:00:00` |

🛑 **Buco di copertura noto**: un articolo pubblicato negli ultimi 15 minuti prima di mezzanotte (`23:45:00`–`23:59:59`) non viene mai condiviso. Da rivedere se si modifica l'orario dei run o delle quiet hours.
