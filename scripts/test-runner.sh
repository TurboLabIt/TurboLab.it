#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/test-runner.sh
source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "🧪 ${APP_NAME} Test Runner"
devOnlyCheck

#fxTitle "Pages to check"
#fxAskConfirmation "Update the values in tests/BaseT.php before proceeding"

sudo nginx -t
if [ "$?" -ne 0 ]; then
  fxCatastrophicError "NGINX config is failing, cannot proceed"
fi

sudo service nginx restart
wsuSymfony console cache:clear

# https://github.com/TurboLabIt/webstackup/tree/master/script/php/test-runner-package.sh
export XDEBUG_PORT=
#export WSU_TEST_RUNNER_PARALLEL=0
source "${WEBSTACKUP_SCRIPT_DIR}php/test-runner-package.sh"


if [ "$APP_ENV" = "dev" ]; then

  fxTitle "chown dev..."
  sudo chown $(logname):www-data "${PROJECT_DIR}" -R
  sudo chmod ugo= "${PROJECT_DIR}" -R
  sudo chmod ugo=rwX "${PROJECT_DIR}" -R
fi

source "${SCRIPT_DIR}/script_end.sh"
