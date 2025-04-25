# [Infrastruttura per next.turbolab.it](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/next-server.md)

Questo file documenta l'infrastruttura tecnica che eroga l'istanza di staging di TurboLab.it (`next.turbolab.it`). Per le informazioni generali e le credenziali di accesso, vedi [docs/next-version-staging.md](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/next-version-staging.md).


## gateway-1 su turbolab.it

[Il record DNS next.turbolab.it](https://mxtoolbox.com/SuperTool.aspx?action=a%3anext.turbolab.it&run=toolpage) è un CNAME per `turbolab.it`. Ciò nonostante, il server che eroga `next.turbolab.it` è fisicamente separato da quello che eroga il sito di produzione.

Sul server di produzione è attiva la configurazione "gateway-1", [nginx-gateway-1.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/staging/nginx-gateway-1.conf). Il deploy di questo file avviene contestualmente al deploy dell'istanza di produzione ([scripts/deploy_moment_030.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/deploy_moment_030.sh)).

Questo primo gateway DEVE utilizzare certificati HTTPS validi per `next.turbolab.it` ([comando di generazione qui](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/staging/nginx-gateway-1.conf)).

L'indirizzo IP di `proxy_pass` ivi impiegato DEVE essere quello del'interfaccia WAN del "tier IV data center" che ospita il server vero e proprio sul quale è in esecuzione `next.turbolab.it`.


## gateway-2

Presso il "tier IV data center" è presente un "gateway-2", [nginx-gateway-2-stargate.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/staging/nginx-gateway-2-stargate.conf), al quale si connette il "gateway-1". La configurazione di "gateway-2" è identica a quella di "gateway-1", con le seguenti eccezioni:

1. i certificati HTTPS sono quelli standard, self-signed, generati da [webstackup](https://github.com/TurboLabIt/webstackup)
2. l'indirizzo IP di `proxy_pass` è quello di LAN del server che eroga next.turbolab.it

Il deploy avviene manualmente, tramite curl (v. file).


## Setup del server

Vedi [docs/server.md](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/server.md)
