#!/usr/bin/env bash
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "Auto-deploy, download, import"
fxEnvNotProd

fxSshTestAccess root@turbolab.it

if [ "${APP_ENV}" == "staging" ]; then
  bash "${SCRIPT_DIR}deploy.sh"
fi

bash "${SCRIPT_DIR}tli-download.sh"
bash "${SCRIPT_DIR}tli-import.sh"

if [ "${APP_ENV}" == "dev" ]; then
  bash "${SCRIPT_DIR}test-runner.sh"
fi
