#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/db-load.sh

source $(dirname $(readlink -f $0))/script_begin.sh

DAY_NUM=$(date +'%u')

SKIP_POST_RESTORE_CACHE_CLEAR=1

## WEBSITE
DB_DUMP_FILE_PATH=${DB_DUMP_DIR}thundercracker_turbolab_it_v1_${DAY_NUM}.sql.7z
## local database name to import into
MYSQL_DB_NAME=tli1
SKIP_POST_RESTORE_QUERY=1
wsuSourceFrameworkScript db-restore

## FORUM
DB_DUMP_FILE_PATH=${DB_DUMP_DIR}thundercracker_turbolab_it_forum_${DAY_NUM}.sql.7z
## local database name to import into
MYSQL_DB_NAME=turbolab_it_forum
SKIP_POST_RESTORE_QUERY=0
wsuSourceFrameworkScript db-restore


source "${SCRIPT_DIR}script_end.sh"
