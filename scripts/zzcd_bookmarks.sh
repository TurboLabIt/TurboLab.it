## 🚨 WARNING 🚨
#
# This file is under version control!
# DO NOT EDIT DIRECTLY - If you do, you'll loose your changes!
#
# The original file is in `/var/www/turbolab.it/scripts/`
#
# You MUST:
#
# 1. edit the original file on you PC
# 2. Git-commit+push the changes
# 3. run `sudo bash /var/www/turbolab.it/scripts/deploy.sh`
#
# ⚠️ This file is SHARED among staging|prod ⚠️
#
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/zzcd_bookmarks.sh
#
PROJECT_DIR=/var/www/turbolab.it/
ZZCD_BOOKMARKS=("${PROJECT_DIR}" "Go"
  "${PROJECT_DIR}shop" "Go"
  "${PROJECT_DIR}var/log" "Go"
  "/var/log/nginx" "Go"
  "/var/www" "Go"
  "/etc/nginx/conf.d" "Go")
