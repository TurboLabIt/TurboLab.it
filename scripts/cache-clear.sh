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

  fxTitle "Replacing vendor/turbolabit with a symlink..."
  sudo rm -rf ${PROJECT_DIR}vendor/turbolabit/*
  fxLink ${PROJECT_DIR}../php-packages/php-encryptor ${PROJECT_DIR}vendor/turbolabit/php-encryptor
  fxLink ${PROJECT_DIR}../php-packages/php-foreachable ${PROJECT_DIR}vendor/turbolabit/php-foreachable
  fxLink ${PROJECT_DIR}../php-packages/php-symfony-basecommand ${PROJECT_DIR}vendor/turbolabit/php-symfony-basecommand
  fxLink ${PROJECT_DIR}../php-packages/php-symfony-messenger ${PROJECT_DIR}vendor/turbolabit/php-symfony-messenger
  fxLink ${PROJECT_DIR}../php-packages/php-traits ${PROJECT_DIR}vendor/turbolabit/php-traits
  fxLink ${PROJECT_DIR}../php-packages/php-symfony-service-entity-plus-bundle ${PROJECT_DIR}vendor/turbolabit/service-entity-plus-bundle
  fxLink ${PROJECT_DIR}../php-packages/php-symfony-paginator ${PROJECT_DIR}vendor/turbolabit/paginatorbundle
  echo ""
  ls -l --color=always ${PROJECT_DIR}vendor/turbolabit
fi


fxTitle "Locking down encryptor key files (www-data, 0600)..."
# var/ above is chowned to *:www-data and chmod'd o=rX (world-readable) — re-lock the secret keys.
# php-fpm runs as www-data, so we make it the owner and keep 0600 (no group/other access).
if [ -d "${PROJECT_DIR}var/encryptor" ]; then
  sudo chown www-data:www-data "${PROJECT_DIR}var/encryptor" -R
  sudo chmod 0700 "${PROJECT_DIR}var/encryptor"
  sudo find "${PROJECT_DIR}var/encryptor" -type f -name '*.key' -exec chmod 0600 {} +
fi


bash "${SCRIPT_DIR}phpbb-cache-clear.sh"

bash "${SCRIPT_DIR}reindex.sh"
