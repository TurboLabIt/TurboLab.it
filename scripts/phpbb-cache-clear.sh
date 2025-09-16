#!/usr/bin/env bash

source $(dirname $(readlink -f $0))/script_begin.sh
fxHeader "🧹 phpBB cache-clear"


fxTitle "Setting base owner and permissions..."
sudo chown $EXPECTED_USER:www-data ${WEBROOT_DIR}forum -R
sudo chmod ug=rwX,o=rX ${WEBROOT_DIR}forum -R


fxTitle "ext/turbolabit link..."
# 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/forum-integration.md
rm -rf "${WEBROOT_DIR}forum/ext/turbolabit"
fxLink "${PROJECT_DIR}src/Forum/ext-turbolabit" "${WEBROOT_DIR}forum/ext/turbolabit"

fxTitle "Granting writing permission to the ForumIntegrationBuilder output folder..."
sudo chmod ugo= "${PROJECT_DIR}src/Forum/ext-turbolabit/forumintegration/styles/" -R
sudo chmod ugo=rwX "${PROJECT_DIR}src/Forum/ext-turbolabit/forumintegration/styles/" -R


# GENERATING THE EXT FILES FROM TWIG
wsuSymfony console ForumIntegrationBuilder


fxTitle "🧹 Deleting the forum cache folder..."
sudo rm -rf "${WEBROOT_DIR}forum/cache/production"

fxTitle "💬 Clearing phpBB cache via phpBB CLI..."
bash ${SCRIPT_DIR}phpbb-cli.sh cache:purge


fxTitle "Reloading PHP-FPM to wipe opcache..."
sudo service php${PHP_VER}-fpm reload

source "${SCRIPT_DIR}script_end.sh"
