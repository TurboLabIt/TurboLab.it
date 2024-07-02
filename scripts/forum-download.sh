#!/usr/bin/env bash

source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "⏬ Forum download"
devOnlyCheck


source "${SCRIPT_DIR}script_end.sh"
