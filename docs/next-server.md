# [Infrastruttura per next.turbolab.it](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/next-server.md)

Questo file documenta l'infrastruttura tecnica che eroga l'istanza di test/staging di TurboLab.it (`next.turbolab.it`).

Per le credenziali di accesso e altre informazioni per utenti finali: 🔗 Vedi [docs/next-version-staging.md](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/next-version-staging.md)


## gateway-1 su turbolab.it

[Il record DNS next.turbolab.it](https://mxtoolbox.com/SuperTool.aspx?action=a%3anext.turbolab.it&run=toolpage) è un CNAME per `turbolab.it`. Ciò nonostante, il server che eroga il sito di produzione è fisicamente separato da quello che eroga `next.turbolab.it`.

Sul server di produzione è attiva la configurazione "[gateway-1](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/prod/nginx.conf)". La preparazione della relativa "webroot" avviene in [scripts/deploy_moment_030.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/deploy_moment_030.sh).

Questo primo gateway DEVE utilizzare certificati HTTPS validi per `next.turbolab.it` (comando di generazione: [https-generate-certs.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/https-generate-certs.sh)).

L'indirizzo IP di `proxy_pass` ivi impiegato DEVE essere quello del'interfaccia WAN del "tier IV data center" che ospita il server vero e proprio sul quale è in esecuzione `next.turbolab.it`.


## gateway-2 su stargate presso tier IV data center

Presso il "tier IV data center" è presente uno "stargate" che eroga "gateway-2", [nginx-gateway-2-stargate.conf](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/staging/nginx-gateway-2-stargate.conf), al quale si connette il "gateway-1". ⚠️ Tale file di configurazione va scaricato manualmente sullo stargate, così come documentato nel file di configurazione stesso.

La configurazione di "gateway-2" è identica a quella di "gateway-1", con le seguenti eccezioni:

1. i certificati HTTPS sono quelli standard, self-signed, generati da [webstackup](https://github.com/TurboLabIt/webstackup)
2. l'indirizzo IP di `proxy_pass` è quello di LAN del server che eroga next.turbolab.it


## server finale

Il gateway-2 si connette infine al server che eroga l'istanza di test del sito (`100 (next-tli)`).

Su tale server è attivo l'[autodeploy di Webstackup](https://github.com/TurboLabIt/webstackup/blob/master/script/php-pages/readme.md#how-to-autodeploy) che esegue automaticamente [deploy.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/deploy.sh) a ogni merge sul branch `master`.
