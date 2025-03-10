# [next.turbolab.it (istanza di staging)](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/next-version-staging.md)

All'indirizzo [next.turbolab.it](https://next.turbolab.it) è disponibile l'istanza di staging di TurboLab.it. Le credenziali di accesso sono:

- username: `tli_staging`
- password: `staging_tli`


## ⚠️ Istanza "semi-pubblica"

`next.turbolab.it` è un sito "semi-pubblico", nel senso che:

1. **⚠️ non dobbiamo mai linkare le pagine di next.turbolab.it** quando le stesse pagine sono presenti sull'istanza di produzione
2. non dobbiamo mai scrivere direttamente sul sito o sul forum le credenziali di accesso

Piuttosto, vogliamo sempre linkare il presente documento ([permalink](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/next-version-staging.md)).

D'altro canto, è bene capire che il presente documento è disponibile pubblicamente, e contiene tutte le informazioni necessarie per accedere all'instanza di staging. È dunque necessario **trattare l'istanza di staging come un sito pubblico**, mantenendo il massimo decoro e professionalità anche durante le prove.


## Codice sorgente

Al contrario dell'istanza di produzione, next.turbolab.it utilizza sempre il codice più aggiornato disponibile sul [repository GitHub](https://github.com/TurboLabIt/TurboLab.it).


## Dati di test

L'istanza di staging utilizza **una copia** dei seguenti dati di produzione:

- database del sito (articoli, tag, ...)
- database del forum (discussioni, commenti agli articoli, utenti registrati, ...)
- file scaricabili, allegati agli articoli
- immagini caricate all'interno degli articoli

È dunque possibile **creare, modificare, eliminare liberamente qualsiasi elemento**, senza che le modifiche si riflettano sul sito di produzione.

Di contro, è bene notare che le procedure automatiche **sovrascrivono** periodicamente i dati presenti su next.turbolab.it con quelli dell'istanza di produzione.


## Invio email

Le email inviate dall'istanza di staging non arrivano a destinazione. Non è dunque possibile effettuare alcuna prova che riguardi l'invio di email.


## Infrastruttura

**Il server che eroga next.turbolab.it è fisicamente separato da quello che eroga il sito di produzione**.

Ciò premesso, [il record DNS `next.turbolab.it` è un CNAME per `turbolab.it`](https://mxtoolbox.com/SuperTool.aspx?action=a%3anext.turbolab.it&run=toolpage). Sul server di produzione è dunque presente una [configurazione "gateway-1"](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/staging/nginx-gateway.conf) (non-versionata):

````shell
sudo mkdir -p /var/www/turbolab.it-next-gateway/public
sudo chown www-data:www-data /var/www/turbolab.it-next-gateway -R
sudo chmod ugo= /var/www/turbolab.it-next-gateway -R
sudo chmod ug=rwX,o=rX /var/www/turbolab.it-next-gateway -R

sudo curl -o /etc/nginx/conf.d/turbolab.it-next-gateway.conf https://raw.githubusercontent.com/TurboLabIt/TurboLab.it/refs/heads/main/config/custom/staging/nginx-gateway.conf

sudo nano /etc/nginx/conf.d/turbolab.it-next-gateway.conf && sudo nginx -t && sudo service nginx reload

````

Questo primo gateway DEVE utilizzare certificati HTTPS validi per next.turbolab.it ([comando di generazione qui](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/staging/nginx-gateway.conf)).

L'indirizzo IP di `proxy_pass` DEVE essere quello WAN del "tier IV data center" che ospita next.turbolab.it.

------

Presso il "tier IV data center" è presente un "gateway-2" (*stargate*) al quale si connette il "gateway-1". La configurazione di "gateway-2" è identica a quella di "gateway-1", con le seguenti eccezioni:

1. i certificati HTTPS sono quelli standard, self-signed, generati da [webstackup](https://github.com/TurboLabIt/webstackup)
2. l'indirizzo IP di `proxy_pass` è quello di LAN del server che eroga next.turbolab.it


## Setup del server

Vedi [docs/server.md](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/server.md)
