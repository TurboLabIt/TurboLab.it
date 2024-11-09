#!/usr/bin/env bash

source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "Copy data from TLI1 to TLI2 hybrid server"
fxEnvNotDev

TLI1_SOURCE_DIR=/var/www/turbolab_it/website/www/


bash "${TLI1_SOURCE_DIR}scripts/db-dump.sh"


if [ "$APP_ENV" == "staging" ]; then

  TLI2_DATABASE_NAME=turbolab_it_next
  TLI2_FORUM_DIR=${PROJECT_DIR}public/forum/

  fxTitle "Copying the whole forum directory..."
  sudo mv "${TLI2_FORUM_DIR}config.php" "${PROJECT_DIR}backup/phpbb-next-config.php"
  sudo rm -rf "${TLI2_FORUM_DIR}"
  sudo cp -rL --preserve "${TLI1_SOURCE_DIR}public/forum" "${TLI2_FORUM_DIR%/}"
  sudo rm -f "${TLI2_FORUM_DIR}config.php"
  sudo mv "${PROJECT_DIR}backup/phpbb-next-config.php" "${TLI2_FORUM_DIR}config.php"
  sudo chown webstackup:www-data "${TLI2_FORUM_DIR}" -R

  if ! grep -q turbolab_it_next_forum "${TLI2_FORUM_DIR}config.php"; then
    fxCatastrophicError "config.php doesn't contain ##turbolab_it_next_forum##"
  fi

  cd "${TLI1_SOURCE_DIR}backup/db-dumps"
  zzmysqlimp "$(hostname)_turbolab_it_forum_$(date +'%u').sql.7z" turbolab_it_next_forum

  fxTitle "Changing some forum settings..."
  bash "${SCRIPT_DIR}phpbb-cli.sh" config:set email_enable 0
  bash "${SCRIPT_DIR}phpbb-cli.sh" config:set smtp_host "null.turbolab.it"
  bash "${SCRIPT_DIR}phpbb-cli.sh" config:set sitename "next.turbolab.it"
  bash "${SCRIPT_DIR}phpbb-cli.sh" config:set site_home_url "https://next.turbolab.it"
  bash "${SCRIPT_DIR}phpbb-cli.sh" config:set server_name "next.turbolab.it"
  bash "${SCRIPT_DIR}phpbb-cli.sh" cache:purge

elif [ "$APP_ENV" == "prod" ]; then

  TLI2_DATABASE_NAME=turbolab_it
fi


cd "${TLI1_SOURCE_DIR}backup/db-dumps"
zzmysqlimp "$(hostname)_turbolab_it_v1_$(date +'%u').sql.7z" turbolab_it_next_tli1_to_import


cd "${PROJECT_DIR}"


fxTitle "Removing TLI2 assets store var/uploaded-assets/..."
sudo rm -rf ${PROJECT_DIR}var/uploaded-assets


fxTitle "Setting up symlinks to TLI1 resources..."
TLI2_ASSETS_TO_IMPORT_DIR=${PROJECT_DIR}var/uploaded-assets-downloaded-from-remote

sudo rm -rf "${TLI2_ASSETS_TO_IMPORT_DIR}"
sudo mkdir -p "${TLI2_ASSETS_TO_IMPORT_DIR}"
cd "${TLI2_ASSETS_TO_IMPORT_DIR}"

ln -s "${TLI1_SOURCE_DIR}immagini/originali-contenuti" images
ln -s "${TLI1_SOURCE_DIR}files" .

ls -l --color "${TLI2_ASSETS_TO_IMPORT_DIR}"
cd "${PROJECT_DIR}"


fxTitle "Dropping doctrine_migration_versions..."
wsuMysql -e "DROP TABLE IF EXISTS ${TLI2_DATABASE_NAME}.doctrine_migration_versions"

bash ${SCRIPT_DIR}migrate.sh

sudo -u "$EXPECTED_USER" -H symfony console tli1

bash "${SCRIPT_DIR}cache-clear.sh"
