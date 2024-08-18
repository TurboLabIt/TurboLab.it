# [Configurazione server](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/server.md)

1. installare Ubuntu Server (ultima LTS). Installazione `standard`, non `minimal`. Sistema operativo in inglese, layout tastiera Italiano, attivare SSH
2. eseguire [ssh-keys](https://github.com/ZaneCEO/ssh-keys)
3. deploy con [Webstackup](https://github.com/TurboLabIt/webstackup) | la configurazione (`/etc/turbolab.it/webstackup.conf`) da usare [è disponibile qui](https://github.com/TurboLabIt/TurboLab.it/blob/main/config/custom/webstackup.conf) (copia-incollare)
4. visualizzare la chiave SSH dell'utente `webstackup` (impartire `webstackup` e scegliere la voce di menu)
5. [associare tale chiave](https://github.com/settings/keys) a quelle dell'utente GitHub `zz-tli-server`
6. **clonazione** (prendere [link SSH da qui](https://github.com/TurboLabIt/TurboLab.it)) in `/var/www/turbolab.it`
7.


## Primo deploy istanza di staging (next.turbolab.it)

[] Clonare il repository:

````shell
sudo git clone https://github.com/TurboLabIt/TurboLab.it.git /var/www/turbolab.it-next && sudo chown webstackup:www-data /var/www/turbolab.it-next -R && cd /var/www/turbolab.it-next
````

[] Forzare l'ambiente su "staging":

````shell
echo "staging" > /var/www/turbolab.it-next/env && cat /var/www/turbolab.it-next/env
````

[] Caricare il file privato `.env.staging.local`

[] Impartire `webstackup` e scegliere l'opzione per creare l'utente MySQL (`app_name: turbolab_it_next`) indicato nel file `.env.staging.local`

[] Caricare il file privato `backup/next-phpbb-config.php` nel percorso `public/forum/config.php`

[] Eseguire il primo deploy:

````shell
clear && bash scripts/deploy.sh
````

[] Eseguire l'importazione da TLI1 a TLI2:

````shell
clear && bash scripts/tli1-tli2-hybrid-import.sh
````
