#!/usr/bin/env bash

clear
source $(dirname $(readlink -f $0))/script_begin.sh
XDEBUG_MODE=off vendor/phpstan/phpstan/phpstan analyse src
source "${SCRIPT_DIR}/script_end.sh"
