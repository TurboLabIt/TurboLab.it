# [Migrazione dati e file al nuovo sito](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tli1-migration.md)

🛑 **Procedura una tantum, già conclusa: NON deve essere ri-eseguita.** Questo è il documento *storico* della migrazione dei dati dal vecchio sito (TLI1) a quello attuale (TLI2), effettuata una sola volta. I relativi script sono stati rimossi dal repository e restano consultabili solo nella git history: i link più sotto puntano all'ultima versione disponibile. Il file è conservato come riferimento.

---

Per attivare TLI2 è necessario importare svariati dati dalla versione precedente (TLI1).


## Sito

I dati del sito sono:

- database `turbolab_it`: contiene articoli, autori, tag e indicizza immagini e file
- immagini caricate negli articoli
- file allegati agli articoli

Questi dati **non possono** essere utilizzati "così come sono" a causa della diversa architettura dell'applicazione: vanno importati tramite la procedura descritta sotto.


## Forum

Per quanto riguarda il forum:

- database `turbolab_it_forum`: il database di phpBB
- cartella completa `forum`: i file che costituiscono phpBB, gli avatar caricati dagli utenti, ecc.

Questi dati possono essere utilizzati "così come sono".


## Fase 1: Download dati

La prima fase consiste nel download dei dati dal server di produzione all'istanza corrente. 🛑 Richiede l'accesso SSH al server di produzione e va eseguita **sul server di sviluppo**, usando `ForwardAgent yes` nella connessione SSH verso produzione (così da consentire al server di sviluppo di accedere a sua volta al server di produzione):

📚 [Come usare Git e la chiave SSH del PC locale con Visual Studio Code Remote Development su Windows 11 e Windows 10](https://turbolab.it/3788)

````bash
bash scripts/tli1-download.sh
````

Script rimosso — ultima versione in git: [scripts/tli1-download.sh](https://github.com/TurboLabIt/TurboLab.it/blob/eb1b82e3e96fa0bf0fbc61672d5d6241e76caa43/scripts/tli1-download.sh)


## Fase 2: Importazione

La seconda fase importa i dati scaricati nell'architettura di TLI2

````bash
bash scripts/tli1-import.sh
````

Script rimossi — ultima versione in git: [scripts/tli1-import.sh](https://github.com/TurboLabIt/TurboLab.it/blob/eb1b82e3e96fa0bf0fbc61672d5d6241e76caa43/scripts/tli1-import.sh), esegue comando Symfony [TLI1Importer](https://github.com/TurboLabIt/TurboLab.it/blob/eb1b82e3e96fa0bf0fbc61672d5d6241e76caa43/src/Command/TLI1ImporterCommand.php)


## Tutti i file della procedura (solo git history)

I file specifici della migrazione sono stati rimossi dal repository; qui sono linkati all'ultima versione disponibile:

- [scripts/tli1-download.sh](https://github.com/TurboLabIt/TurboLab.it/blob/eb1b82e3e96fa0bf0fbc61672d5d6241e76caa43/scripts/tli1-download.sh) — Fase 1: download dei dati da produzione
- [scripts/tli1-import.sh](https://github.com/TurboLabIt/TurboLab.it/blob/eb1b82e3e96fa0bf0fbc61672d5d6241e76caa43/scripts/tli1-import.sh) — Fase 2: import dei dati in TLI2
- [src/Command/TLI1ImporterCommand.php](https://github.com/TurboLabIt/TurboLab.it/blob/eb1b82e3e96fa0bf0fbc61672d5d6241e76caa43/src/Command/TLI1ImporterCommand.php) — comando Symfony `tli1` (*TLI1 Importer*), eseguito dalla Fase 2
- [scripts/tli1-tli2-hybrid-import.sh](https://github.com/TurboLabIt/TurboLab.it/blob/eb1b82e3e96fa0bf0fbc61672d5d6241e76caa43/scripts/tli1-tli2-hybrid-import.sh) — variante "ibrida" (fase di transizione): copiava i dati da TLI1 a un server TLI2 ibrido
