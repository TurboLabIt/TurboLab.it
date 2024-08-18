#!/usr/bin/env bash

clear
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "Copy data from TLI1 to TLI2 hybrid server"
TLI1_SOURCE_DIR=/var/www/turbolab_it/website/www/
TLI2_FORUM_DIR=${PROJECT_DIR}public/forum/


fxTitle "Copying the whole forum directory..."
sudo mv "${TLI2_FORUM_DIR}config.php" "${PROJECT_DIR}backup/phpbb-next-config.php"
sudo rm -rf "${TLI2_FORUM_DIR}"
sudo cp -a "${TLI1_SOURCE_DIR}public/forum" "${TLI2_FORUM_DIR%/}"
sudo rm -f "${TLI2_FORUM_DIR}config.php"
sudo mv "${PROJECT_DIR}backup/phpbb-next-config.php" "${TLI2_FORUM_DIR}config.php"

if ! grep -q turbolab_it_next_forum "${TLI2_FORUM_DIR}config.php"; then
  fxCatastrophicError "config.php doesn't contain ##turbolab_it_next_forum##"
fi

fxTitle "Copying the forum database..."
bash "${TLI1_SOURCE_DIR}scripts/db-dump.sh"
cd "${TLI1_SOURCE_DIR}backup/db-dumps"
zzmysqlimp thundercracker_turbolab_it_forum_$(date +'%u').sql.7z turbolab_it_next_forum
cd "${PROJECT_DIR}"


fxTitle "Changing some forum settings..."
bash "${SCRIPT_DIR}phpbb-cli.sh" config:set email_enable 0
bash "${SCRIPT_DIR}phpbb-cli.sh" config:set sitename "next.turbolab.it"
bash "${SCRIPT_DIR}phpbb-cli.sh" config:set site_home_url "https://next.turbolab.it"
bash "${SCRIPT_DIR}phpbb-cli.sh" config:set server_name "next.turbolab.it"
bash "${SCRIPT_DIR}phpbb-cli.sh" cache:purge

