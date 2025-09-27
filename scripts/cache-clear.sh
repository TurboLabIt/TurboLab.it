#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/clear-cache.sh

source $(dirname $(readlink -f $0))/script_begin.sh

if [ "$APP_ENV" == "dev" ]; then

  fxHeader "Special DEV handling..."

  fxLink ${PROJECT_DIR}config/custom/dev/nginx-dev0.conf /etc/nginx/conf.d/turbolab.it-dev0.conf
  fxLink ${PROJECT_DIR}config/custom/zzmysqldump.conf /etc/turbolab.it/zzmysqldump.profile.turbolab.it.conf
  sudo cp ${PROJECT_DIR}config/custom/mysql-custom.conf /etc/mysql/mysql.conf.d/95-turbolab.it.cnf

  sudo rm -rf /etc/php/${PHP_VER}/fpm/conf.d/90-turbolab.it.ini
  sudo cp ${PROJECT_DIR}config/custom/php-custom.ini /etc/php/${PHP_VER}/fpm/conf.d/90-turbolab.it.ini
  sudo sed -i 's/display_errors = off/display_errors = on/; s/display_startup_errors = off/display_startup_errors = on/' /etc/php/${PHP_VER}/fpm/conf.d/90-turbolab.it.ini

  fxLink /etc/php/${PHP_VER}/fpm/conf.d/90-turbolab.it.ini /etc/php/${PHP_VER}/cli/conf.d/90-turbolab.it.ini
  fxLink ${PROJECT_DIR}config/custom/php-custom-cli.ini /etc/php/${PHP_VER}/cli/conf.d/95-turbolab.it-cli.ini
  sudo rm -f /etc/php/${PHP_VER}/fpm/conf.d/30-webstackup-opcache.ini

  fxTitle "Removing composer stuff..."
  sudo rm -rf ${PROJECT_DIR}.env.local.php ${PROJECT_DIR}vendor ${PROJECT_DIR}composer.lock

  fxTitle "npm-check-updates..."
  yarn npm-check-updates -u

  fxTitle "Removing yarn stuff..."
  rm -f ${PROJECT_DIR}yarn.lock

  source "${WEBSTACKUP_SCRIPT_DIR}account/bashrc-dev-patch.sh"

  source ${SCRIPT_DIR}deploy_moment_030.sh
fi

### SYMFONY console cache:clear ###
wsuSourceFrameworkScript cache-clear "$@"


fxTitle "Setting up the images cache folder..."
sudo rm -rf ${PROJECT_DIR}var/uploaded-assets/images/cache
sudo mkdir -p ${PROJECT_DIR}var/uploaded-assets/images/cache

fxTitle "Setting up the var folder..."
sudo chown webstackup:www-data ${PROJECT_DIR}var -R
sudo chmod ug=rwX,o=rX ${PROJECT_DIR}var -R

fxTitle "Setting up HTMLpurifier cache folder..."
sudo chmod 775 "${PROJECT_DIR}vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer" -R


if [ "$APP_ENV" == "dev" ]; then

  fxTitle "chown dev..."
  sudo chown $(logname):www-data "${PROJECT_DIR}" -R
  sudo chmod ugo= "${PROJECT_DIR}" -R
  sudo chmod ugo=rwX "${PROJECT_DIR}" -R
fi

bash "${SCRIPT_DIR}phpbb-cache-clear.sh"
