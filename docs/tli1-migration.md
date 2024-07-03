# [Migrazione dati e file al nuovo sito](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tli1-migration.md)

Per lanciare il nuovo sito è necessario importare dalla versione precedente di TLI svariati dati.


## Sito

- database `turbolab_it` - contiene articoli, autori, tag e indicizza immagini e file
- immagini caricate negli articoli
- file allegati agli articoli

Questi dati **NON possono** essere utilizzati "così come sono" a causa della diversa architettura dell'applicazione.
Piuttosto, vanno scaricati e importati tramite [src/Command/TLI1ImporterCommand](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/TLI1ImporterCommand.php).


## Forum

Per quanto riguarda il forum:

- database `turbolab_it_forum` - è il database di phpBB
- cartella completa `forum` - contiene i file che costituiscono phpBB, avatar caricati dagli utenti ecc.

Questi dati possono essere utilizzati "così come sono".


## Fase 1: Download dati

🎗️ Al termine di questa fase, l'**ambiente di sviluppo diventa inutilizzabile**, ed è obbligatorio eseguire la Fase 2.

La prima fase consiste nel download dei dati dal vecchio server all'istanza corrente.

🛑 Poiché la procedura richiede l'accesso SSH al server di produzione, questo comando deve essere lanciato sul PC locale
dello sviluppatore, **NON** sul server di sviluppo.

🛑 Prima di eseguire lo script, assicurarsi che l'**auto-upload di phpStorm** sia attivo e funzionante.

````bash
bash [scripts/tli1-download.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/tli1-download.sh)

````


## Fase 2: Importazione

Una volta che phpStorm ha finito di caricare tutti i file dal PC locale al server di sviluppo, importare i dati.

🛑 Il comando seguente va regolarmente eseguito sul server di sviluppo, **NON** sul PC locale.

````bash
bash [scripts/tli1-import.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/tli1-import.sh)

````
