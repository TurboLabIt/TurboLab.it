# [Server di sviluppo](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/server-dev.md)

Il "server di sviluppo" è una macchina virtuale, fisicamente separata dal server di produzione. Ogni sviluppatore utilizza un proprio dominio dedicato:

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
#RemoteForward 9003 localhost:9003
````

nota: sostituire a `dev0` il nome utente comunicato. Ad esempio: `dev1` oppure `dev2`.

A questo punto, sarà possibile collegarsi in SSH impartendo:

`ssh zzdev`


## Configurazione server dev

1. seguire [server.md](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/server.md)
2. modificare la porta SSH in `30986`
3. [postfix: external-relay](https://github.com/TurboLabIt/webstackup/blob/master/config/postfix/external-relay-template.md) con account `turbolab.prove@xxx` e relativa "app password"
4. [postfix: redirect-all](https://github.com/TurboLabIt/webstackup/blob/master/config/postfix/redirect-all-template.md) verso `turbolab.prove@xxx`
5. file hosts: `127.0.0.1 dev0.turbolab.it dev1.turbolab.it dev2.turbolab.it`
5. `bash /var/www/turbolab.it/scripts/cache-clear.sh`


## Xdebug con Visual Studio Code

[📚 Guida Remote Dev con Visual Studio Code](https://turbolab.it/1540)

[📚 Guida Xdebug con Visual Studio Code](https://turbolab.it/3270) | [📚 Troubleshooting](https://turbolab.it/3274)

Il file `.vscode/launch.json` di base è il seguente:

````json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            //"stopOnEntry": true,
            "pathMappings": {
                "/var/www/turbolab.it/": "${workspaceRoot}"
            }
        }
    ]
}
````

Utilizzando Visual Studio Code è necessario mantenere commentata la riga `#RemoteForward 9003 localhost:9003` presente nella config SSH indicata sopra.


## Xdebug con phpStorm

[📚 Guida Xdebug con phpStorm](https://turbolab.it/3275) | [📚 Troubleshooting](https://turbolab.it/3274)

Utilizzando phpStorm è necessario de-commentare la riga `RemoteForward 9003 localhost:9003` presente nella config SSH indicata sopra.
