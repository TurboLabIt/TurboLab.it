#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/phpbb-upgrade.sh

source $(dirname $(readlink -f $0))/script_begin.sh

fxLink ${PROJECT_DIR}config/custom/phpbb-upgrader.conf /etc/turbolab.it/phpbb-upgrader-turbolab.it.conf

bash /usr/local/turbolab.it/phpbb-upgrader/phpbb-upgrader.sh turbolab.it
bash ${SCRIPT_DIR}phpbb-cache-clear.sh

source "${SCRIPT_DIR}/script_end.sh"
