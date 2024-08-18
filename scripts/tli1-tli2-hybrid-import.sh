#!/usr/bin/env bash

clear
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "Copy data from TLI1 to TLI2 hybrid server"

fxTitle "Clearing database..."
wsuMysql -e 'DROP TABLE IF EXISTS turbolab_it_next.doctrine_migration_versions'

bash ${SCRIPT_DIR}migrate.sh

#sudo -u "$EXPECTED_USER" -H symfony console tli1

#bash "${SCRIPT_DIR}cache-clear.sh"
