## Configurazione server dev

1. seguire [server.md](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/server.md)
2. modificare la porta SSH in `30986`
3. attivare la config nginx: `ln -s /var/www/turbolab.it/config/custom/dev/nginx-dev0.conf /etc/nginx/conf.d/turbolab.it-dev0.conf && zzws`
