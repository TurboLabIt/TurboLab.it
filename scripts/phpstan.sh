#!/usr/bin/env bash

clear
source $(dirname $(readlink -f $0))/script_begin.sh
vendor/phpstan/phpstan/phpstan analyse src
source "${SCRIPT_DIR}/script_end.sh"
