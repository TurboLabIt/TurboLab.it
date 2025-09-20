fxTitle "Checking if IMAP for PHP ${PHP_VER} is installed..."
if dpkg -l | grep -q "php${PHP_VER}-imap"; then

  fxOK "Yes, it is."

else

  fxInfo "No, it isn't. Installing it now..."
  apt update && apt install php${PHP_VER}-imap -y
fi


fxTitle "Creating a symlink for public/immagini..."
rm -rf "${WEBROOT_DIR}immagini"
ln -s "${PROJECT_DIR}var/uploaded-assets/images/cache" "${WEBROOT_DIR}immagini"


fxTitle "Linking branding images..."
BRAND_LINK_PATH=${WEBROOT_DIR}images/logo/apple-touch-icon.png
rm -rf "${BRAND_LINK_PATH}"
fxLink "${WEBROOT_DIR}images/logo/2013/ttt-tiny.png" "${BRAND_LINK_PATH}"

BRAND_LINK_PATH=${WEBROOT_DIR}images/logo/favicon.ico
rm -rf "${BRAND_LINK_PATH}"
fxLink "${WEBROOT_DIR}images/logo/2013/favicon.ico" "${BRAND_LINK_PATH}"

BRAND_LINK_PATH=${WEBROOT_DIR}images/logo/turbolab.it.png
rm -rf "${BRAND_LINK_PATH}"
fxLink "${WEBROOT_DIR}images/logo/2013/turbolab.it-2013-finale-tiny.png" "${BRAND_LINK_PATH}"


wsuMysql -e "
  REVOKE ALL PRIVILEGES ON *.* FROM 'turbolab_it'@'localhost';
  GRANT ALL PRIVILEGES ON \`turbolab%\`.* TO 'turbolab_it'@'localhost';
  GRANT ALL PRIVILEGES ON \`tli1\`.* TO 'turbolab_it'@'localhost';
  GRANT RELOAD, PROCESS ON *.* TO 'turbolab_it'@'localhost';
  FLUSH PRIVILEGES;
"


fxTitle "Deploying next.turbolab.it (gateway-1)..."
if [ "$APP_ENV" = 'prod' ]; then
  bash "${WEBSTACKUP_SCRIPT_DIR}filesystem/proxyall-webroot-maker.sh"
else
  fxInfo "##$APP_ENV## is not prod. Skipping 🦘"
fi
