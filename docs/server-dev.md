## Server di sviluppo

Il "server di sviluppo" è una macchina virtuale Hyper-V, fisicamente separata dal server di produzione. Ogni sviluppatore utilizza un proprio dominio dedicato:

- https://ben:venuto@dev0.turbolab.it
- https://ben:venuto@dev1.turbolab.it
- ...

Questi domini non sono inseriti a DNS. Devono quindi essere inseriti nel [file hosts](https://turbolab.it/1131) in questo modo:

````
78.xxx.yyy.zzz    dev0.turbolab.it dev1.turbolab.it
````

Per accedere in SSH al server di sviluppo è necessario utilizzare [una chiave SSH](https://turbolab.it/3144). Dopo aver scambiato la chiave [creare un nuovo elemento in .ssh/config](https://turbolab.it/3145):

````
Host zzdev
Hostname dev0.turbolab.it
User dev0
Port 30986
RemoteForward 9003 localhost:9003
````

nota: sostituire a `dev0` il nome utente comunicato. Ad esempio: `dev1` oppure `dev2`.

A questo punto, sarà possibile collegarsi in SSH impartendo:

`ssh zzdev`


## Configurazione server dev

1. seguire [server.md](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/server.md)
2. modificare la porta SSH in `30986`
3. attivare la config nginx: `ln -s /var/www/turbolab.it/config/custom/dev/nginx-dev0.conf /etc/nginx/conf.d/turbolab.it-dev0.conf && zzws`
