#!/usr/bin/env bash

source $(dirname $(readlink -f $0))/script_begin.sh
fxHeader "cache-clear-forum-only"

fxTitle "Forum own extensions link..."
rm -rf "${WEBROOT_DIR}forum/ext/turbolabit"
fxLink "${PROJECT_DIR}src/Forum/ext-turbolabit" "${WEBROOT_DIR}forum/ext/turbolabit"

fxTitle "Allowing access..."
sudo chmod ugo= "${PROJECT_DIR}src/Forum/ext-turbolabit/forumintegration/styles/" -R
sudo chmod ugo=rwX "${PROJECT_DIR}src/Forum/ext-turbolabit/forumintegration/styles/" -R


# GENERATING THE EXT FILES FROM TWIG
wsuSymfony console ForumIntegrationBuilder


fxTitle "🧹 Deleting the forum cache folder..."
sudo rm -rf "${WEBROOT_DIR}forum/cache/production"

fxTitle "💬 Clearing phpBB cache via phpBB CLI..."
bash ${SCRIPT_DIR}phpbb-cli.sh cache:purge

source "${SCRIPT_DIR}script_end.sh"
