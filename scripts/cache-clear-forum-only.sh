#!/usr/bin/env bash

source $(dirname $(readlink -f $0))/script_begin.sh
fxHeader "cache-clear-forum-only"
## this would destroy the integration if ran with TLI1 still live
fxEnvNotProd

fxTitle "Allow access to forumintegration/styles..."
sudo chmod ugo= "${PROJECT_DIR}src/Forum/ext-turbolabit/forumintegration/styles/" -R
sudo chmod ugo=rwX "${PROJECT_DIR}src/Forum/ext-turbolabit/forumintegration/styles/" -R

wsuSymfony console ForumIntegrationBuilder

fxTitle "🧹 Deleting the forum cache folder..."
sudo rm -rf "${WEBROOT_DIR}forum/cache/production"

source "${SCRIPT_DIR}script_end.sh"
