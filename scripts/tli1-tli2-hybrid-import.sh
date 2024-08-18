#!/usr/bin/env bash

clear
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "Copy data from TLI1 to TLI2 hybrid server"

source "/etc/turbolab.it/mysql.conf"

fxTitle "Clearing database..."
mysql -u${MYSQL_USER} -p ${MYSQL_PASSWORD} -H ${MYSQL_HOST} -e "TRUNCATE turbolab_it_next.doctrine_migration_versions"

bash {$SCRIPT_DIR}migrate.sh

#sudo -u "$EXPECTED_USER" -H symfony console tli1

#bash "${SCRIPT_DIR}cache-clear.sh"
