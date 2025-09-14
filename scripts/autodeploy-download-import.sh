#!/usr/bin/env bash
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "Auto-deploy, download, import"

if [ "${APP_ENV}" == "prod" ]; then

  fxCatastrophicError "TLI 2.0 is live - this script cannot run anymore"
  bash "${SCRIPT_DIR}deploy.sh"
  bash "${SCRIPT_DIR}tli1-tli2-hybrid-import.sh"

elif [ "${APP_ENV}" == "staging" ]; then

  fxSshTestAccess root@turbolab.it
  bash "${SCRIPT_DIR}deploy.sh"

elif [ "${APP_ENV}" == "dev" ]; then

  fxSshTestAccess root@turbolab.it

else

  fxCatastrophicError "Unhandled branch ##${APP_ENV}##"
fi


if [ "${APP_ENV}" != "prod" ]; then

  bash "${SCRIPT_DIR}tli1-download.sh"
  rm -rf ${PROJECT_DIR}backup/db-dumps/*.sql
  bash "${SCRIPT_DIR}db-restore.sh"
  bash "${SCRIPT_DIR}tli1-import.sh"
fi


if [ "${APP_ENV}" == "dev" ]; then
  bash "${SCRIPT_DIR}test-runner.sh"
fi
