# [Migrazione dati e file al nuovo sito](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tli1-migration.md)

Per attivare questo sito è necessario importare svariati dati dalla versione precedente di TLI.

⏩ Comando rapido per dev (richiede `ForwardAgent yes`, vedi seguito)

````bash
cd /var/www/turbolab.it && clear && bash scripts/tli1-download.sh && bash scripts/tli1-import.sh

````


## Sito

I dati del sito sono:

- database `turbolab_it`: contiene articoli, autori, tag e indicizza immagini e file
- immagini caricate negli articoli
- file allegati agli articoli

Questi dati **NON possono** essere utilizzati "così come sono" a causa della diversa architettura dell'applicazione. Vanno invece importati tramite [src/Command/TLI1ImporterCommand](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Command/TLI1ImporterCommand.php).


## Forum

Per quanto riguarda il forum:

- database `turbolab_it_forum`: è il database di phpBB
- cartella completa `forum`: contiene i file che costituiscono phpBB, gli avatar caricati dagli utenti ecc.

Questi dati possono essere utilizzati "così come sono".


## Fase 1: Download dati

🎗️ Al termine di questa fase, l'**ambiente di sviluppo diventa inutilizzabile**, ed è obbligatorio eseguire la Fase 2.

La prima fase consiste nel download dei dati dal server di produzione all'istanza corrente.

🛑 Questo comando richiede l'accesso SSH al server di produzione per scaricare i dati. **Deve essere eseguito sul server di sviluppo**, ma, per consentire al server di sviluppo di accedere al server di produzione, è necessario usare `ForwardAgent yes` nella connessione SSH verso il server di sviluppo:

📚 [Come usare Git e la chiave SSH del PC locale con Visual Studio Code Remote Development su Windows 11 e Windows 10](https://turbolab.it/3788)

````bash
bash scripts/tli1-download.sh

````

([vedi script](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/tli1-download.sh))


## Fase 2: Importazione

Per importare i dati scaricati:

````bash
bash scripts/tli1-import.sh

````

([vedi script](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/tli1-import.sh))
