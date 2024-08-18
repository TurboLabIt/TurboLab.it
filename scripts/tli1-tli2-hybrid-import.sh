#!/usr/bin/env bash

clear
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "Copy data from TLI1 to TLI2 hybrid server"

fxTitle "Dropping doctrine_migration_versions..."
wsuMysql -e 'DROP TABLE IF EXISTS turbolab_it_next.doctrine_migration_versions'

bash ${SCRIPT_DIR}migrate.sh

fxTitle "Setting up symlinks to TLI1 resources..."
TLI2_ASSETS_TO_IMPORT_DIR=${PROJECT_DIR}var/uploaded-assets-downloaded-from-remote
echo "${TLI2_ASSETS_TO_IMPORT_DIR}"
exit

sudo rm -rf ${TLI2_ASSETS_TO_IMPORT_DIR}
sudo mkdir -p ${TLI2_ASSETS_TO_IMPORT_DIR}
cd ${TLI2_ASSETS_TO_IMPORT_DIR}

TLI1_SOURCE_DIR=/var/www/turbolab_it/website/www/

ln -s ${TLI1_SOURCE_DIR}immagini/originali-contenuti images
ln -s ${TLI1_SOURCE_DIR}files .


#sudo -u "$EXPECTED_USER" -H symfony console tli1

#bash "${SCRIPT_DIR}cache-clear.sh"
