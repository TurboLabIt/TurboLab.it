#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/script_begin.sh

APP_NAME="turbolab.it"
PROJECT_FRAMEWORK=symfony
LETS_ENCRYPT_SKIP_RENEW=1

## https://github.com/TurboLabIt/webstackup/blob/master/script/filesystem/script_begin_start.sh
source "/usr/local/turbolab.it/webstackup/script/filesystem/script_begin_start.sh"

## Enviroment variables and checks

PHPBB_DB_NAME=turbolab_it_forum

if [ "$APP_ENV" = "prod" ]; then

  EMOJI=rocket
  ZZ_CMD_SUFFIX=1
  SITE_URL=https://turbolab.it

elif [ "$APP_ENV" = "staging" ]; then

  EMOJI=cat
  ZZ_CMD_SUFFIX=0
  SITE_URL=https://next.turbolab.it
fi
