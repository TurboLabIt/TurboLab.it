#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/test-runner.sh

source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "🧪 ${APP_NAME} Test Runner"

# https://github.com/TurboLabIt/webstackup/tree/master/script/php/test-runner-package.sh
source "${WEBSTACKUP_SCRIPT_DIR}php/test-runner-package.sh"

fxTitle "🧹 Cleaning up..."
#rm -rf /tmp/any-temp-dir

source "${SCRIPT_DIR}/script_end.sh"
