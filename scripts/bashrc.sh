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
# ⚠️ This file is SHARED among dev|staging|prod ⚠️
#
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/bashrc.sh

source "/usr/local/turbolab.it/bash-fx/scripts/colors.sh"
fxSetBackgroundColorByHostAndEnv "thundercracker" "prod"
fxSetBackgroundColorByHostAndEnv "next-tli" "next"
trap fxResetBackgroundColor EXIT

cd /var/www/turbolab.it
