#!/usr/bin/env bash
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "HTTPS certs generator"

if [ "$APP_ENV" = "prod" ]; then

  sudo certbot certonly --email info@turbolab.it --no-eff-email --agree-tos --cert-name turbolab.it --webroot -w ${WEBROOT_DIR} -d turbolab.it -d www.turbolab.it
  sudo certbot certonly --email info@turbolab.it --no-eff-email --agree-tos --cert-name turbolab.it-next-gateway --webroot -w /var/www/proxyall-webroot -d next.turbolab.it
  sudo certbot certonly --email info@turbolab.it --no-eff-email --agree-tos --cert-name turbolab.it-bug --webroot -w /var/www/proxyall-webroot -d bug.turbolab.it

elif [ "$APP_ENV" = "staging" ]; then

  sudo certbot --email info@turbolab.it --agree-tos certonly --webroot -w ${WEBROOT_DIR} -d next.turbolab.it
fi

source "${SCRIPT_DIR}script_end.sh"
