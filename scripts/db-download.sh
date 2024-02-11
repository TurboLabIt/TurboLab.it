#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/db-download.sh

source $(dirname $(readlink -f $0))/script_begin.sh

REMOTE_SERVER=turbolab.it
REMOTE_SSH_USERNAME=root
## 👇 this is still related to TLI1
REMOTE_PROJECT_DIR=/var/www/turbolab_it/website/www/
REMOTE_APP_ENV=prod
DISABLE_SSH_TEST=0

wsuSourceFrameworkScript db-download

if [ "${APP_ENV}" != "prod" ]; then

  fxTitle "📁 Setting up local directories..."
  RES_LOCAL_DIR=${PROJECT_DIR}var/uploaded-assets-downloaded-from-remote/

  IMAGES_LOCAL_DIR=${RES_LOCAL_DIR}images/
  mkdir -p "${IMAGES_LOCAL_DIR}"

  FILES_LOCAL_DIR=${RES_LOCAL_DIR}files/
  mkdir -p "${FILES_LOCAL_DIR}"


  fxTitle "📂 Switching to images directory..."
  cd "${IMAGES_LOCAL_DIR}"
  ## let's double-check (this could have devastating effects)
  if [ "$(pwd)/" != "${IMAGES_LOCAL_DIR}" ]; then
    fxCatastrophicError "cd'ing to ##${IMAGES_LOCAL_DIR}## failed"
  fi

  fxOK "${IMAGES_LOCAL_DIR}"

  fxTitle "⏬ Mirroring images"
  REMOTE_PATH=root@turbolab.it:/var/www/turbolab_it/website/www/immagini/originali-contenuti/

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
  REMOTE_PATH=root@turbolab.it:/var/www/turbolab_it/website/www/files/

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
fi

source "${SCRIPT_DIR}script_end.sh"
