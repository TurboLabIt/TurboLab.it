#!/usr/bin/env bash
## 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tli1-migration.md

source $(dirname $(readlink -f $0))/script_begin.sh
wsuSymfony console TLI1Importer "$@"
bash "${SCRIPT_DIR}cache-clear.sh"
