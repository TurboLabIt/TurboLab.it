#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/clear-cache.sh

source $(dirname $(readlink -f $0))/script_begin.sh

if [ "$APP_ENV" = "dev" ]; then

  fxHeader "Special DEV handling..."

  fxLink ${PROJECT_DIR}config/custom/dev/nginx-dev0.conf /etc/nginx/conf.d/turbolab.it-dev0.conf
  fxLink ${PROJECT_DIR}config/custom/phpbb-upgrader.conf /etc/turbolab.it/phpbb-upgrader-turbolab.it.conf
  fxLink ${PROJECT_DIR}config/custom/zzmysqldump.conf /etc/turbolab.it/zzmysqldump.profile.turbolab.it.conf
  sudo cp ${PROJECT_DIR}config/custom/mysql-custom.conf /etc/mysql/mysql.conf.d/95-turbolab.it.cnf

  fxLink ${PROJECT_DIR}config/custom/php-custom.ini /etc/php/${PHP_VER}/fpm/conf.d/90-turbolab.it.ini
  fxLink ${PROJECT_DIR}config/custom/php-custom.ini /etc/php/${PHP_VER}/cli/conf.d/90-turbolab.it.ini
  fxLink ${PROJECT_DIR}config/custom/php-custom-cli.ini /etc/php/${PHP_VER}/cli/conf.d/95-turbolab.it-cli.ini
  sudo rm -f /etc/php/${PHP_VER}/fpm/conf.d/30-webstackup-opcache.ini

  fxTitle "Removing composer stuff..."
  sudo rm -rf ${PROJECT_DIR}.env.local.php ${PROJECT_DIR}vendor ${PROJECT_DIR}composer.lock

  fxTitle "npm-check-updates..."
  yarn npm-check-updates -u

  fxTitle "Removing yarn stuff..."
  rm -f ${PROJECT_DIR}yarn.lock

  fxTitle "Clearing the built images cache..."
  rm -rf ${PROJECT_DIR}var/uploaded-assets/images/cache
  mkdir -p ${PROJECT_DIR}var/uploaded-assets/images/cache

  fxTitle "chown dev..."
  sudo chown $(logname):www-data "${PROJECT_DIR}" -R
  sudo chmod ugo= "${PROJECT_DIR}" -R
  sudo chmod ugo=rwX "${PROJECT_DIR}" -R

  source ${SCRIPT_DIR}deploy_moment_030.sh
fi


wsuSourceFrameworkScript cache-clear "$@"


sudo chmod 775 "${PROJECT_DIR}vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer" -R


wsuSymfony console ForumIntegrationBuilder


fxTitle "🧹 Deleting the forum cache folder..."
rm -rf "${WEBROOT_DIR}forum/cache/production"

fxTitle "💬 Clearing phpBB cache via phpBB CLI..."
bash ${SCRIPT_DIR}phpbb-cli.sh cache:purge


source "${SCRIPT_DIR}script_end.sh"
