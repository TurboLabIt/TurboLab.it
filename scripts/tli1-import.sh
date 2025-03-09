#!/usr/bin/env bash
## 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tli1-migration.md

source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "TLI1 importer"
fxEnvNotProd

wsuMysql -e "DROP TABLE IF EXISTS turbolab_it.doctrine_migration_versions"
bash "${SCRIPT_DIR}migrate.sh"

rm -rf ${PROJECT_DIR}backup/db-dumps/*.sql
bash "${SCRIPT_DIR}db-restore.sh"

cd ${PROJECT_DIR}
if [ "$EXPECTED_USER" = "$(whoami)" ]; then
  symfony console tli1
else
  sudo -u "$EXPECTED_USER" -H symfony console tli1
fi

bash "${SCRIPT_DIR}cache-clear.sh"
