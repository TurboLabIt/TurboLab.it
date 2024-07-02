#!/usr/bin/env bash
## 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/tli1-migration.md

source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader "TLI1 downloader"
devOnlyCheck

### DB DOWLOAD
REMOTE_SERVER=turbolab.it
REMOTE_SSH_USERNAME=root
## 👇 this is the TLI1 remote path
REMOTE_PROJECT_DIR=/var/www/turbolab_it/website/www/
REMOTE_APP_ENV=prod
DISABLE_SSH_TEST=0

wsuSourceFrameworkScript db-download


fxTitle "📁 Setting up local directories..."
RES_LOCAL_DIR=${PROJECT_DIR}var/uploaded-assets-downloaded-from-remote/

IMAGES_LOCAL_DIR=${RES_LOCAL_DIR}images/
mkdir -p "${IMAGES_LOCAL_DIR}"

FILES_LOCAL_DIR=${RES_LOCAL_DIR}files/
mkdir -p "${FILES_LOCAL_DIR}"

FORUM_DIR=${WEBROOT_DIR}forum/
if [ ! -d "${FORUM_DIR}" ]; then
    fxCatastrophicError "The required directory ##${FORUM_DIR}## is missing! Get it via ##git pull## first!"
fi

TLI_EXT_DIR=${PROJECT_DIR}src/Forum/ext-turbolabit/
if [ ! -d "${TLI_EXT_DIR}" ]; then
    fxCatastrophicError "The required directory ##${TLI_EXT_DIR}## is missing! Get it via ##git pull## first!"
fi


fxTitle "📂 Switching to images directory..."
cd "${IMAGES_LOCAL_DIR}"
## let's double-check (this could have devastating effects)
if [ "$(pwd)/" != "${IMAGES_LOCAL_DIR}" ]; then
  fxCatastrophicError "cd'ing to ##${IMAGES_LOCAL_DIR}## failed"
fi

fxOK "${IMAGES_LOCAL_DIR}"

fxTitle "⏬ Mirroring images..."

REMOTE_PATH=root@turbolab.it:${REMOTE_PROJECT_DIR}immagini/originali-contenuti/

if [[ "${REMOTE_PATH}" != */ ]]; then
  REMOTE_PATH=${REMOTE_PATH}/
fi

echo "From: ${REMOTE_PATH}"
echo "To:   $(pwd)"
fxCountdown

rsync --archive --compress --delete --partial --progress --verbose ${REMOTE_PATH} .


fxTitle "📂 Switching to files directory..."
cd "${FILES_LOCAL_DIR}"
## let's double-check (this could have devastating effects)
if [ "$(pwd)/" != "${FILES_LOCAL_DIR}" ]; then
  fxCatastrophicError "cd'ing to ##${FILES_LOCAL_DIR}## failed"
fi

fxOK "${FILES_LOCAL_DIR}"

fxTitle "⏬ Mirroring files"
REMOTE_PATH=root@turbolab.it:${REMOTE_PROJECT_DIR}files/

if [[ "${REMOTE_PATH}" != */ ]]; then
 REMOTE_PATH=${REMOTE_PATH}/
fi

echo "From: ${REMOTE_PATH}"
echo "To:   $(pwd)"
fxCountdown

rsync --archive --compress --delete --partial --progress --verbose ${REMOTE_PATH} .


fxTitle "📂 Listing..."
cd "${RES_LOCAL_DIR}"
fxOK "$(pwd)"
echo ""
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
REMOTE_PATH=root@turbolab.it:${REMOTE_PROJECT_DIR}public/forum/

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


fxTitle "📂 Switching to ext-turbolab.it real directory..."
cd "${TLI_EXT_DIR}"
## let's double-check (this could have devastating effects)
if [ "$(pwd)/" != "${TLI_EXT_DIR}" ]; then
    fxCatastrophicError "##${TLI_EXT_DIR}## exists, but cd'ing failed"
fi

fxOK "${TLI_EXT_DIR}"
fxTitle "⏬ Mirroring!"
REMOTE_PATH=root@turbolab.it:${REMOTE_PROJECT_DIR}public/forum-integration/forum-ext-turbolabit/

if [[ "${REMOTE_PATH}" != */ ]]; then
   REMOTE_PATH=${REMOTE_PATH}/
fi

echo "From: ${REMOTE_PATH}"
echo "To:   $(pwd)"
fxCountdown

rsync --archive --compress --delete --partial --progress --verbose ${REMOTE_PATH} .


fxTitle "📂 Listing..."
fxOK "$(pwd)"
echo ""
ls -lah --color=auto


source "${SCRIPT_DIR}script_end.sh"
