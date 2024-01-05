#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/db-download.sh

source $(dirname $(readlink -f $0))/script_begin.sh

REMOTE_SERVER=turbolab.it
REMOTE_SSH_USERNAME=root
## 👇 this is still related to TLI1
REMOTE_PROJECT_DIR=/var/www/turbolab_it/website/www/
REMOTE_APP_ENV=prod
DISABLE_SSH_TEST=0

wsuSourceFrameworkScript db-download

source "${SCRIPT_DIR}script_end.sh"
