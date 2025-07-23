#!/usr/bin/env bash
## 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tli1-migration.md

source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "TLI1 importer"
fxEnvNotProd

wsuMysql -e "DROP TABLE IF EXISTS turbolab_it.doctrine_migration_versions"
bash "${SCRIPT_DIR}migrate.sh"

cd ${PROJECT_DIR}
rm -rf ${PROJECT_DIR}var/uploaded-assets/images/cache
wsuSymfony console tli1 "$@"
wsuSymfony console TagAggregator
wsuSymfony console cache:clear
