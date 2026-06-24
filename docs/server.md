# [Configurazione server](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/server.md)

- [ ] installare Ubuntu Server (ultima LTS). Installazione `minimized`. Sistema operativo in inglese, layout tastiera Italiano, attivare SSH
- [ ] eseguire [ssh-keys](https://github.com/ZaneCEO/ssh-keys)
- [ ] provisioning del server con [Webstackup](https://github.com/TurboLabIt/webstackup) | la configurazione (`/etc/turbolab.it/webstackup.conf`) da usare [è disponibile qui](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/webstackup.conf) (copia-incollare)
- [ ] **mantenere `INSTALL_GIT_CLONE_WEBAPP=1`** e seguire la procedura
- [ ] [aggiungere la chiave alle Deploy keys](https://github.com/TurboLabIt/TurboLab.it/settings/keys) quando richiesto
- [ ] prendere [link SSH da qui](https://github.com/TurboLabIt/TurboLab.it) quando richiesto per la clonazione
- [ ] clonare in `/var/www/turbolab.it`
- [ ] eseguire il primo deploy: `clear && bash scripts/deploy.sh`
