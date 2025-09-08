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


fxTitle "Forum own extensions link..."
rm -rf "${WEBROOT_DIR}forum/ext/turbolabit"
fxLink "${PROJECT_DIR}src/Forum/ext-turbolabit" "${WEBROOT_DIR}forum/ext/turbolabit"
chmod ugo= "${PROJECT_DIR}src/Forum/ext-turbolabit/forumintegration/styles/" -R
chmod ugo=rwX "${PROJECT_DIR}src/Forum/ext-turbolabit/forumintegration/styles/" -R


wsuMysql -e "
  REVOKE ALL PRIVILEGES ON *.* FROM 'turbolab_it'@'localhost';
  GRANT ALL PRIVILEGES ON \`turbolab%\`.* TO 'turbolab_it'@'localhost';
  GRANT ALL PRIVILEGES ON \`tli1\`.* TO 'turbolab_it'@'localhost';
  GRANT RELOAD, PROCESS ON *.* TO 'turbolab_it'@'localhost';
  FLUSH PRIVILEGES;
"

fxTitle "Patching $(logname) .bashrc..."
LOGGED_USER_BASHRC=$(fxGetUserHomePath $(logname)).bashrc
fxInfo "###${LOGGED_USER_BASHRC}###"

if [ ! -f "${LOGGED_USER_BASHRC}" ]; then
  touch "${LOGGED_USER_BASHRC}"
fi

if [ "$APP_ENV" = 'dev' ] && ! grep -q "scripts/bashrc-dev.sh" "${LOGGED_USER_BASHRC}"; then

  echo "" >> "${LOGGED_USER_BASHRC}"
  echo "## TurboLab.it dev" >> "${LOGGED_USER_BASHRC}"
  echo "source ${SCRIPT_DIR}bashrc-dev.sh" >> "${LOGGED_USER_BASHRC}"
  fxOK "${LOGGED_USER_BASHRC} has been patched (dev)"
fi

if ! grep -q "scripts/bashrc.sh" "${LOGGED_USER_BASHRC}"; then

  echo "" >> "${LOGGED_USER_BASHRC}"
  echo "## TurboLab.it" >> "${LOGGED_USER_BASHRC}"
  echo "source ${SCRIPT_DIR}bashrc.sh" >> "${LOGGED_USER_BASHRC}"
  fxOK "${LOGGED_USER_BASHRC} has been patched"
fi


fxTitle "Deploying next.turbolab.it (gateway)..."
if [ "$APP_ENV" = 'prod' ]; then

  bash "${WEBSTACKUP_SCRIPT_DIR}filesystem/proxyall-webroot-maker.sh"

  rm -f /etc/nginx/conf.d/turbolab.it-next-gateway.conf
  ln -s ${PROJECT_DIR}config/custom/staging/nginx-gateway-1.conf /etc/nginx/conf.d/turbolab.it-next-gateway.conf

else

  fxInfo "##$APP_ENV## is not prod. Skipping 🦘"
fi
