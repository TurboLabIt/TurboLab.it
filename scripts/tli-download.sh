#!/usr/bin/env bash

source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "TLI2 downloader"
fxEnvNotProd

## 👇 this is the TLI2 remote path
REMOTE_PROJECT_DIR=/var/www/turbolab.it/
REMOTE_SERVER=turbolab.it
fxSshTestAccess root@${REMOTE_SERVER}


fxTitle "📁 Setting up local directories..."
RES_LOCAL_DIR=${PROJECT_DIR}var/uploaded-assets/
mkdir -p "${RES_LOCAL_DIR}"

FORUM_DIR=${WEBROOT_DIR}forum/
if [ ! -d "${FORUM_DIR}" ]; then
    fxCatastrophicError "The required directory ##${FORUM_DIR}## is missing! Get it via ##git pull## first!"
fi

TLI_EXT_DIR=${PROJECT_DIR}src/Forum/ext-turbolabit/
if [ ! -d "${TLI_EXT_DIR}" ]; then
    fxCatastrophicError "The required directory ##${TLI_EXT_DIR}## is missing! Get it via ##git pull## first!"
fi


bash "${PROJECT_DIR}scripts/db-download.sh"


fxTitle "📂 Switching to uploaded-assets directory..."
cd "${RES_LOCAL_DIR}"
## let's double-check (this could have devastating effects)
if [ "$(pwd)/" != "${RES_LOCAL_DIR}" ]; then
  fxCatastrophicError "cd'ing to ##${RES_LOCAL_DIR}## failed"
fi

fxOK "${RES_LOCAL_DIR}"


fxTitle "⏬ Mirroring assets..."

REMOTE_PATH=root@${REMOTE_SERVER}:${REMOTE_PROJECT_DIR}var/uploaded-assets/

if [[ "${REMOTE_PATH}" != */ ]]; then
  REMOTE_PATH=${REMOTE_PATH}/
fi

echo "From: ${REMOTE_PATH}"
echo "To:   $(pwd)"
#fxCountdown

rsync --archive --compress --delete --partial --progress --verbose \
  --exclude 'images/cache' ${REMOTE_PATH} .

fxTitle "📂 Listing..."
fxOK "$(pwd)"
echo ""
ls -la
du -hs * | sort -rh | head -5


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
REMOTE_PATH=root@${REMOTE_SERVER}:${REMOTE_PROJECT_DIR}public/forum/

if [[ "${REMOTE_PATH}" != */ ]]; then
   REMOTE_PATH=${REMOTE_PATH}/
fi

echo "From: ${REMOTE_PATH}"
echo "To:   $(pwd)"
#fxCountdown

rsync --archive --compress --delete --partial --progress --verbose \
  --exclude='cache/production' --exclude='.gitignore' ${REMOTE_PATH} .


fxTitle "📂 Listing..."
fxOK "$(pwd)"
echo ""
ls -lah --color=auto


fxTitle "🧹 Deleting the cache folder..."
sudo rm -rf "cache/production"


if [ -f "${PHPBB_CONFIG_BACKUP_PATH}" ]; then

  fxTitle "🦺 Restoring your local phpBB config.php..."
  chmod ugo=rwx config.php
  mv "${PHPBB_CONFIG_BACKUP_PATH}" config.php
  chmod ug=rw,o= config.php
  fxOK "Restored to #$(pwd)/config.php#"
fi


source "${SCRIPT_DIR}script_end.sh"
