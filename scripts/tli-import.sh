#!/usr/bin/env bash

source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "TLI importer"
fxEnvNotProd

rm -rf ${PROJECT_DIR}backup/db-dumps/*.sql
bash "${SCRIPT_DIR}db-restore.sh"
bash "${SCRIPT_DIR}cache-clear.sh"
