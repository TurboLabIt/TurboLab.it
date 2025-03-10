# [Infrastruttura per next.turbolab.it](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/next-server.md)

Questo file documenta l'infrastruttura tecnica che eroga l'istanza di staging di TurboLab.it. Per le informazioni generali e le credenziali di accesso, vedi [docs/next-version-staging.md](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/next-version-staging.md).


## gateway-1 su turbolab.it

Il server che eroga next.turbolab.it è fisicamente separato da quello che eroga il sito di produzione. Ciò premesso, [il record DNS `next.turbolab.it` è un CNAME per `turbolab.it`](https://mxtoolbox.com/SuperTool.aspx?action=a%3anext.turbolab.it&run=toolpage).

Sul server di produzione è presente una [configurazione "gateway-1"](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/staging/nginx-gateway.conf) (non-versionata):

````bash
sudo mkdir -p /var/www/turbolab.it-next-gateway/public
sudo chown www-data:www-data /var/www/turbolab.it-next-gateway -R
sudo chmod ugo= /var/www/turbolab.it-next-gateway -R
sudo chmod ug=rwX,o=rX /var/www/turbolab.it-next-gateway -R

sudo curl -o /etc/nginx/conf.d/turbolab.it-next-gateway.conf https://raw.githubusercontent.com/TurboLabIt/TurboLab.it/refs/heads/main/config/custom/staging/nginx-gateway.conf

sudo nano /etc/nginx/conf.d/turbolab.it-next-gateway.conf && sudo nginx -t && sudo service nginx reload

````

Questo primo gateway DEVE utilizzare certificati HTTPS validi per next.turbolab.it ([comando di generazione qui](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/staging/nginx-gateway.conf)).

L'indirizzo IP di `proxy_pass` DEVE essere quello WAN del "tier IV data center" che ospita next.turbolab.it.


## gateway-2

Presso il "tier IV data center" è presente un "gateway-2" (*stargate*) al quale si connette il "gateway-1". La configurazione di "gateway-2" è identica a quella di "gateway-1", con le seguenti eccezioni:

1. i certificati HTTPS sono quelli standard, self-signed, generati da [webstackup](https://github.com/TurboLabIt/webstackup)
2. l'indirizzo IP di `proxy_pass` è quello di LAN del server che eroga next.turbolab.it


## Setup del server

Vedi [docs/server.md](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/server.md)
