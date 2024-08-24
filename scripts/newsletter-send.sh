#!/usr/bin/env bash
## 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/newsletter.md

source $(dirname $(readlink -f $0))/script_begin.sh
## test mode+recipients by default. Add the option --unlock to send for real
wsuSymfony console NewsletterSend "$@"
source "${SCRIPT_DIR}script_end.sh"
