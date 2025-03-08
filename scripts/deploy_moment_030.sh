fxTitle "Checking if IMAP for PHP ${PHP_VER} is installed..."
if dpkg -l | grep -q "php${PHP_VER}-imap"; then

  fxOK "Yes, it is."

else

  fxInfo "No, it isn't. Installing it now..."
  sudo apt update && sudo apt install php${PHP_VER}-imap -y
fi


fxTitle "Creating a symlink for public/immagini..."
rm -rf "${WEBROOT_DIR}immagini"
ln -s "${PROJECT_DIR}var/uploaded-assets/images/cache"

fxTitle "Forum own extensions link..."
rm -rf "${WEBROOT_DIR}forum/ext/turbolabit"
fxLink "${PROJECT_DIR}src/Forum/ext-turbolabit" "${WEBROOT_DIR}forum/ext/turbolabit"
