# [next.turbolab.it (istanza di staging)](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/next-version-staging.md)

All'indirizzo [next.turbolab.it](https://next.turbolab.it) è disponibile l'istanza di staging di TurboLab.it. Le credenziali di accesso sono:

- username: `tli_staging`
- password: `staging_tli`

Al contrario dell'istanza di produzione ([turbolab.it](https://turbolab.it)), questa istanza utilizza sempre il codice più aggiornato disponibile sul [repository GitHub](https://github.com/TurboLabIt/TurboLab.it).


## Isolamento

L'istanza di staging utilizza **una copia** dei seguenti dati di produzione:

- copia del database del sito (articoli, tag, ...)
- copia del database del forum (discussioni, commenti agli articoli, utenti registrati, ...)
- copia dei file scaricabili, allegati agli articoli
- copia delle immagine caricate all'interno degli articoli

È dunque possibile **creare, modificare, eliminare liberamente qualsiasi elemento**, senza che le modifiche si riflettano sul sito di produzione.

Di contro, è bene notare che le procedure automatiche **sovrascrivono** periodicamente i dati presenti su `next.turbolab.it` con quelli dell'istanza di produzione.


## Email

Le email inviate dall'istanza di staging non arrivano a destinazione. Non è dunque possibile effettuare alcuna prova che riguardi l'invio di email.


## Istanza "semi-pubblica"

`next.turbolab.it` è un sito "semi-pubblico", nel senso che:

1. ⚠ non dobbiamo mai linkare le pagine di next.turbolab.it quando le stesse pagine sono presenti sull'istanza di produzione
2. non dobbiamo mai scrivere direttamente sul sito o sul forum le credenziali di accesso

Piuttosto, vogliamo sempre linkare il presente documento ([permalink](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/next-version-staging.md)).

D'altro canto, è bene capire che il presente documento è disponibile pubblicamente, e contiene tutte le informazioni necessarie per accedere all'instanza di staging. È dunque necessario **trattare anche l'istanza di staging come un sito pubblico**, mantenendo il massimo decoro e professionalità anche durante le prove.