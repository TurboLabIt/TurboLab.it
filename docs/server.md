# [Configurazione server](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/server.md)

1. installare Ubuntu Server (ultima LTS). Installazione `minimized`. Sistema operativo in inglese, layout tastiera Italiano, attivare SSH
2. eseguire [ssh-keys](https://github.com/ZaneCEO/ssh-keys)
3. deploy con [Webstackup](https://github.com/TurboLabIt/webstackup) | la configurazione (`/etc/turbolab.it/webstackup.conf`) da usare [è disponibile qui](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/webstackup.conf) (copia-incollare)
4. **mantenere `INSTALL_GIT_CLONE_WEBAPP=1`** e seguire la procedura
5. [aggiungere la chiave alle Deploy keys](https://github.com/TurboLabIt/TurboLab.it/settings/keys) quando richiesto
6. prendere [link SSH da qui](https://github.com/TurboLabIt/TurboLab.it)) quando richiesto per la clonazione
7. clonare in `/var/www/turbolab.it`


## Primo deploy istanza di staging (next.turbolab.it)

- [ ] ⚠️ [Attivare il catch-all delle email verso catchall-<env>@TLI](https://github.com/TurboLabIt/webstackup/blob/master/config/postfix/redirect-all-template.md)

- [ ] Forzare l'ambiente su "staging":

````shell
echo "staging" > /var/www/turbolab.it/env && cat /var/www/turbolab.it/env
````

- [ ] Caricare il file privato `.env.staging.local`

- [ ] Caricare il file privato `backup/next-phpbb-config.php` nel percorso `public/forum/config.php`

- [ ] Eseguire il primo deploy:

````shell
clear && bash scripts/deploy.sh
````

- [ ] [Eseguire l'importazione da TLI1 a TLI2](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tli1-migration.md)
