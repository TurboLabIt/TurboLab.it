#!/usr/bin/env bash

source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "⏬ Forum download"
devOnlyCheck


fxTitle "📁 Checking required local directories..."
FORUM_DIR=${WEBROOT_DIR}forum/
if [ ! -d "${FORUM_DIR}" ]; then
    fxCatastrophicError "The required directory ##${FORUM_DIR}## is missing! Get it via ##git pull## first!"
fi

## this is temporary (TLI1)
TLI_EXT_DIR=${PROJECT_DIR}src/Forum/ext-turbolabit/
if [ ! -d "${TLI_EXT_DIR}" ]; then
    fxCatastrophicError "The required directory ##${TLI_EXT_DIR}## is missing! Get it via ##git pull## first!"
fi


fxTitle "📂 Switching to forum directory..."
cd "${FORUM_DIR}"
## let's double-check (this could have devastating effects)
if [ "$(pwd)/" != "${FORUM_DIR}" ]; then
    fxCatastrophicError "##${FORUM_DIR}## exists, but cd'ing failed"
fi

fxOK "${FORUM_DIR}"


if [ -f "config.php" ]; then

  PHPBB_CONFIG_BACKUP_PATH=${PROJECT_DIR}backup/phpbb_local_config.php
  fxTitle "🦺 Preserving your local phpBB config.php..."
  mv config.php "${PHPBB_CONFIG_BACKUP_PATH}"
  fxOK "Saved to #${PHPBB_CONFIG_BACKUP_PATH}#"
fi


fxTitle "⏬ Mirroring!"
REMOTE_PATH=root@turbolab.it:/var/www/turbolab_it/website/www/public/forum/

if [[ "${REMOTE_PATH}" != */ ]]; then
   REMOTE_PATH=${REMOTE_PATH}/
fi

echo "From: ${REMOTE_PATH}"
echo "To:   $(pwd)"
fxCountdown

rsync --archive --compress --delete --partial --progress --verbose \
  --exclude='cache/production' --exclude='.gitignore' ${REMOTE_PATH} .


fxTitle "📂 Listing..."
fxOK "$(pwd)"
echo ""
ls -lah --color=auto


fxTitle "🧹 Deleting the cache folder..."
rm -rf "cache/production"

fxTitle "🧹 Deleting the ext/turbolabit symlink..."
rm -rf "ext/turbolabit"


if [ -f "${PHPBB_CONFIG_BACKUP_PATH}" ]; then

  fxTitle "🦺 Restoring your local phpBB config.php..."
  mv "${PHPBB_CONFIG_BACKUP_PATH}" config.php
  fxOK "Restored to #$(pwd)/config.php#"
fi


## this is temporary (TLI1)
fxTitle "📂 Switching to ext-turbolab.it real directory..."
cd "${TLI_EXT_DIR}"
## let's double-check (this could have devastating effects)
if [ "$(pwd)/" != "${TLI_EXT_DIR}" ]; then
    fxCatastrophicError "##${TLI_EXT_DIR}## exists, but cd'ing failed"
fi

fxOK "${TLI_EXT_DIR}"
fxTitle "⏬ Mirroring!"
REMOTE_PATH=root@turbolab.it:/var/www/turbolab_it/website/www/public/forum-integration/forum-ext-turbolabit/

if [[ "${REMOTE_PATH}" != */ ]]; then
   REMOTE_PATH=${REMOTE_PATH}/
fi

echo "From: ${REMOTE_PATH}"
echo "To:   $(pwd)"
fxCountdown

rsync --archive --compress --delete --partial --progress --verbose ${REMOTE_PATH} .

#bash "${SCRIPT_DIR}db-download.sh"

source "${SCRIPT_DIR}script_end.sh"
