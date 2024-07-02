#!/usr/bin/env bash
## 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/newsletter.md

source $(dirname $(readlink -f $0))/script_begin.sh
wsuSymfony console newsletter "$@"
source "${SCRIPT_DIR}script_end.sh"
