# [Configurazione server](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/server.md)

1. installare Ubuntu Server (ultima LTS). Installazione `standard`, non `minimal`. Sistema operativo in inglese, layout tastiera Italiano, attivare SSH
2. eseguire [ssh-keys](https://github.com/ZaneCEO/ssh-keys)
3. deploy con [Webstackup](https://github.com/TurboLabIt/webstackup) | la configurazione (`/etc/turbolab.it/webstackup.conf`) da usare [è disponibile qui](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/webstackup.conf) (copia-incollare)
4. visualizzare la chiave SSH dell'utente `webstackup` (impartire `webstackup` e scegliere la voce di menu)
5. [associare tale chiave](https://github.com/settings/keys) a quelle dell'utente GitHub `zz-tli-server`
6. **clonazione** (prendere [link SSH da qui](https://github.com/TurboLabIt/TurboLab.it)) in `/var/www/turbolab.it`
7.
