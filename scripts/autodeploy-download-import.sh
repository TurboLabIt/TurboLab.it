#!/usr/bin/env bash
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "Auto-deploy, download, import"
fxEnvNotDev

if [ "${APP_ENV}" = "prod" ]; then

  bash "${SCRIPT_DIR}deploy.sh"
  bash "${SCRIPT_DIR}tli1-tli2-hybrid-import.sh"

elif [ "${APP_ENV}" = "staging" ]; then

  bash "${SCRIPT_DIR}deploy.sh"
  bash "${SCRIPT_DIR}tli1-download.sh"
  bash "${SCRIPT_DIR}tli1-import.sh"

else

  fxCatastrophicError "Unhandled branch ##${APP_ENV}##"

endif
