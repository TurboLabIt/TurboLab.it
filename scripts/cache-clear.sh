#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/clear-cache.sh

source $(dirname $(readlink -f $0))/script_begin.sh

if [ "$APP_ENV" = "dev" ]; then

  fxHeader "Special DEV handling..."

  fxLink ${PROJECT_DIR}config/custom/phpbb-upgrader.conf /etc/turbolab.it/phpbb-upgrader-turbolab.it.conf
  fxLink ${PROJECT_DIR}config/custom/zzmysqldump.conf /etc/turbolab.it/zzmysqldump.profile.turbolab.it.conf

  fxLink ${PROJECT_DIR}config/custom/php-custom.ini /etc/php/8.3/fpm/conf.d/90-turbolab.it.ini
  fxLink ${PROJECT_DIR}config/custom/php-custom.ini /etc/php/8.3/cli/conf.d/90-turbolab.it.ini
  fxLink ${PROJECT_DIR}config/custom/php-custom-cli.ini /etc/php/8.3/cli/conf.d/95-turbolab.it-cli.ini

  fxLink ${PROJECT_DIR}config/custom/dev/nginx-dev0.conf /etc/nginx/conf.d/turbolab.it-dev0.conf

  fxTitle "Removing composer stuff..."
  rm -f ${PROJECT_DIR}.env.local.php
  rm -rf ${PROJECT_DIR}vendor composer.lock

  fxTitle "chown dev..."
  sudo chown $(logname):www-data "${PROJECT_DIR}" -R
  sudo chmod ugo= "${PROJECT_DIR}" -R
  sudo chmod ugo=rwX "${PROJECT_DIR}" -R

fi

wsuSourceFrameworkScript cache-clear "$@"

fxTitle "👾 symlinking forum/ext/turbolabit..."
fxLink ${PROJECT_DIR}src/Forum/ext-turbolabit ${WEBROOT_DIR}forum/ext/turbolabit

fxTitle "🧹 Deleting the forum cache folder..."
rm -rf "${WEBROOT_DIR}forum/cache/production"

source "${SCRIPT_DIR}script_end.sh"
