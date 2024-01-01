#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/db-load.sh

source $(dirname $(readlink -f $0))/script_begin.sh

## zzmysqldump
DB_DUMP_FILE_PATH=${DB_DUMP_DIR}db_${APP_NAME}_$(date +'%u').sql.7z
## local database name to import into (zzmysqldump only)
MYSQL_DB_NAME=${APP_NAME}
## options
SKIP_POST_RESTORE_QUERY=0

wsuSourceFrameworkScript db-restore

source "${SCRIPT_DIR}script_end.sh"
