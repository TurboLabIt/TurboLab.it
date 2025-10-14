#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/build.sh

source $(dirname $(readlink -f $0))/script_begin.sh
source "${WEBSTACKUP_SCRIPT_DIR}node.js/webpack_build.sh"
#sudo -u ${EXPECTED_USER} -H PUPPETEER_EXECUTABLE_PATH=/usr/bin/google-chrome yarn node assets/extract-critical-css.mjs https://turbolab.it
source "${SCRIPT_DIR}script_end.sh"
