#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/test-runner.sh
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "🧪 ${APP_NAME} Test Runner"

#fxTitle "Pages to check"
#fxAskConfirmation "Update the values in tests/BaseT.php before proceeding"

wsuSymfony console cache:clear

# https://github.com/TurboLabIt/webstackup/tree/master/script/php/test-runner-package.sh
export XDEBUG_PORT=
#export WSU_TEST_RUNNER_PARALLEL=0
source "${WEBSTACKUP_SCRIPT_DIR}php/test-runner-package.sh"

fxTitle "🧹 Cleaning up..."
#rm -rf /tmp/any-temp-dir

source "${SCRIPT_DIR}/script_end.sh"
