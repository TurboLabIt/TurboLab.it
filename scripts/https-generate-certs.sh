#!/usr/bin/env bash
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "HTTPS certs generator"

if [ "$APP_ENV" = "prod" ]; then

  fxCatastrophicError "Not ready for prod"

elif [ "$APP_ENV" = "staging" ]; then

  sudo certbot --email info@turbolab.it --agree-tos certonly --cert-name turbolab.it-next --webroot -w ${WEBROOT_DIR} -d next.turbolab.it

fi

source "${SCRIPT_DIR}script_end.sh"
