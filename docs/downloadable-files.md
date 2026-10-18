# [Download: file e URL scaricabili negli articoli](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/downloadable-files.md)

Questo documento tratta i **Download**, ovvero i file e gli URL esterni che gli autori mettono a disposizione dei lettori negli articoli. Il documento è una *specifica*: descrive il comportamento voluto e, in breve, l'implementazione attuale.

Nel codice e nel database un Download si chiama `File` (tabella `file`), anche quando è un URL esterno.


## Obiettivi

1. **Un Download è permanente.** Il suo indirizzo, `/scarica/{id}`, non cambia mai. Quando l'elemento cambia (l'URL esterno si sposta, l'autore carica una versione nuova o migliore del file, ...) e la versione precedente non serve più, l'autore modifica il Download: ogni link del sito (testo degli articoli, elenco dei Download dell'articolo, altri articoli che lo usano, forum, ...) eroga subito il nuovo elemento. La versione precedente non viene conservata.
2. **Un elemento, un Download.** Non devono esistere due Download per lo stesso elemento: prima o poi uno dei due resterebbe indietro, e da qualche articolo i lettori scaricherebbero la versione vecchia.


## Cos'è un Download

Ogni Download è composto da:

- **sorgente**, una delle due:
  - **file locale**, caricato su TurboLab.it: materiale originale prodotto dall'autore, o file rari/difficili da trovare altrove
  - **URL esterno**: file aggiornati spesso o facilmente reperibili in rete (sito del produttore, GitHub, app store, magnet BitTorrent, ...)
- **titolo**: univoco in tutto il sito (non possono esserci due Download con lo stesso nome). Per un file locale è anche il nome del file scaricato (titolo + `.formato`)
- **formato**: per un file locale è l'estensione; per un URL esterno descrive (orientativamente) cosa si scarica (`zip`, `iso`, `google play (app store)`, ...)
- autori, articoli che lo usano, numero di download

L'identità del Download è il suo ID, quindi il suo URL: la sorgente si può sostituire, l'ID resta.

**Versione o variante?** Una versione nuova dello stesso elemento *sostituisce* il Download (obiettivo 1). Una variante (ad esempio: 32/64 bit, ISO diretta/BitTorrent, variante per Windows o Linux, ...) è un elemento diverso, quindi un altro Download, e il titolo lo dice: "Windows 11 Live CD/USB 64 bit (x64)" e "... via BitTorrent" sono due Download legittimi.


## Comportamento atteso

Principi dell'interfaccia:

- **un solo nome**: "Download", sia per i file sia per gli URL
- **prima si cerca, poi si crea**: la verifica dei doppioni fa parte del flusso, non è un passaggio che l'autore deve ricordarsi
- **si sostituisce, non si duplica**: "Sostituisci" è un comando di primo piano, proposto anche quando il sistema riconosce una nuova versione
- **si mostra l'impatto**: chi modifica un Download vede in quali articoli è usato, perché la modifica vale per tutti
- **il sistema compila, l'autore corregge**: titolo e formato si deducono dalla sorgente


### Aggiungere un Download a un articolo

Un solo flusso, sia dal pulsante nella toolbar dell'editor sia dal riquadro sotto l'elenco dei Download dell'articolo. Accetta un **file** (scelto o trascinato, anche più d'uno), un **URL** (incollato) o del **testo** (da cercare): l'autore non deve scegliere prima tra "file" e "URL", lo capisce il sistema da cosa riceve.

1. il sistema cerca i Download che corrispondono:
   - file ➡ stesso contenuto (hash), poi nome del file e titolo simili
   - URL ➡ stesso URL, normalizzato (`http`/`https`, `www.`, `/` finale, ...)
   - testo ➡ titolo, nome del file, URL
2. **stesso elemento** ➡ lo dice ("Questo file è già su TurboLab.it come «...»") e propone **Usa questo**
3. **elemento simile** ➡ chiede se è una **nuova versione** di «...» (➡ **Sostituisci**) o una variante (➡ nuovo Download, con un titolo che lo distingua)
4. **nessuna corrispondenza** ➡ nuovo Download, con titolo e formato già compilati: dal nome del file o dall'URL, il formato anche dal sito (Google Play ➡ `google play (app store)`)

Ogni strada termina con il Download collegato all'articolo e il suo link inserito nel testo: sul testo selezionato, oppure come riga a sé `» Download: Titolo`.


### Aggiornare un Download

**Modifica**, nell'elenco dei Download dell'articolo, permette di:

- cambiare titolo e formato
- **sostituire la sorgente**: caricare la nuova versione del file, o indicare il nuovo URL, anche passando da file locale a URL esterno e viceversa

ID e URL `/scarica/{id}` non cambiano, quindi ogni link segue; il vecchio file locale viene eliminato. Prima di confermare, l'autore vede gli altri articoli che usano il Download. Se la nuova sorgente è già un altro Download (stesso contenuto o stesso URL), il sistema lo segnala: due Download con la stessa sorgente sarebbero un doppione.


### Rimuovere un Download da un articolo

L'interfaccia mostra due possibilità alternative, a seconda della situazione:

- **Scollega** se altri articoli lo usano (un Download in uso altrove non si elimina: lascerebbe link morti nel loro testo)
- **Elimina** se l'articolo è l'ultimo a usarlo. La conferma avvisa se il testo contiene ancora link al Download


### Unire due Download doppi (da valutare)

Per i doppioni già esistenti e per quelli che le verifiche non intercettano: "unisci B in A" sposta su A i link e i collegamenti di B in tutti gli articoli, elimina B e fa rispondere `/scarica/B` con un redirect 301 a `/scarica/A`, così funzionano anche i link a B da fuori (come per i tag sostituiti).


## Implementazione (descrizione breve)

Database:

- `file`: `title` (UNIQUE), `format`, `url` (vuoto = file locale), `hash` (UNIQUE: md5 del contenuto per i file locali, dell'URL per quelli esterni), `views`
- `file_author`: chi ha creato il Download
- `article_file`: gli articoli che usano il Download (+ `user_id`: chi l'ha collegato)

Backend:

- `/scarica/{id}` ([FileController](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/FileController.php)): un file locale è servito via X-Sendfile da `var/uploaded-assets/files/{id}.{formato}` e scaricato come `titolo.formato`; un URL esterno risponde con un redirect 302. Il Download eredita la visibilità dagli articoli che lo usano; lo modificano i suoi autori e gli editor ([FileSentinel](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Service/Sentinel/FileSentinel.php))
- nel testo degli articoli il link è salvato come segnaposto `==###file::id::{id}###==` e mostrato come `/scarica/{id}`: la permanenza dei link (obiettivo 1) c'è già
- [FileEditController](https://github.com/TurboLabIt/TurboLab.it/blob/main/src/Controller/Editor/FileEditController.php): upload, creazione da URL, modifica (titolo, formato, URL esterno), scollega/elimina. Ogni modifica svuota la cache delle pagine degli articoli interessati
- salvare un articolo collega ogni Download linkato nel testo (solo aggiunte); [uploaded-assets-maintenance.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/uploaded-assets-maintenance.sh) fa lo stesso ogni 3 ore con `FilesToArticles` (la rimozione dei collegamenti inutilizzati è disattivata: [#101](https://github.com/TurboLabIt/TurboLab.it/issues/101)), più `FilesHasher` (ricalcola gli hash) e `FilesNotFound` (file mancanti su disco o nel database). I risultati sono in [/scarica/da-controllare](https://turbolab.it/scarica/da-controllare)

Frontend: [link-file.js](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/js/ckeditor-plugins/link-file.js) gestisce la finestra "Cerca e inserisci link a file", con "Crea via URL" e "Crea via Upload", e il riquadro "Carica file scaricabile o URL", che carica direttamente i file trascinati; [files-edit.js](https://github.com/TurboLabIt/TurboLab.it/blob/main/assets/js/files-edit.js) la modifica e Scollega/Elimina.


## 🔗 Vedi anche

- [Gestione delle immagini caricate negli articoli](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-articles.md) — l'altro contenuto caricato dagli autori: X-Sendfile, immagini orfane, manutenzione periodica
