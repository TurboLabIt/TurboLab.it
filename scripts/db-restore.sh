#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/db-load.sh

source $(dirname $(readlink -f $0))/script_begin.sh

DAY_NUM=$(date +'%u')
## temporary, for TLI2 dev while TLI1 is still live
DAY_NUM=4

DB_DUMP_FILE_PATH=${DB_DUMP_DIR}thundercracker_turbolab_it_${DAY_NUM}.sql.7z
## local database name to import into
MYSQL_DB_NAME=tli1
## options
SKIP_POST_RESTORE_QUERY=1
wsuSourceFrameworkScript db-restore


DB_DUMP_FILE_PATH=${DB_DUMP_DIR}thundercracker_turbolab_it_forum_${DAY_NUM}.sql.7z
## local database name to import into
MYSQL_DB_NAME=turbolab_it_forum
## options
SKIP_POST_RESTORE_QUERY=0
wsuSourceFrameworkScript db-restore

source "${SCRIPT_DIR}script_end.sh"
