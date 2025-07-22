#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/cleaner.sh

SCRIPT_NAME=cleaner
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "🧹 ${SCRIPT_NAME}"
rootCheck

wsuSourceFrameworkScript cleaner

if [ "${APP_ENV}" == "dev" ]; then

  source /usr/local/turbolab.it/webstackup/script/mysql/maintenance.sh

  service mysql stop
  service nginx stop

  apt clean
  vmware-toolbox-cmd disk shrink /

  service mysql start
  service nginx start
fi

source ${SCRIPT_DIR}/script_end.sh
