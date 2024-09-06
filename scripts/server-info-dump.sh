#!/usr/bin/env bash

source $(dirname $(readlink -f $0))/script_begin.sh
fxHeader 'Dump server info'


fxTitle "Output directory..."
DUMP_DIR=${PROJECT_DIR}var/server-info-dump/
fxMessage "##${DUMP_DIR}##"
sudo rm -rf "${DUMP_DIR}"
sudo -u ${EXPECTED_USER} mkdir -p "${DUMP_DIR}"


fxTitle "lsb_release..."
sudo -u ${EXPECTED_USER} lsb_release -a > "${DUMP_DIR}lsb_release.txt" 2>&1


fxTitle "git commit..."
sudo -u ${EXPECTED_USER} git rev-parse --short HEAD > "${DUMP_DIR}git-commit.txt" 2>&1


source "${SCRIPT_DIR}/script_end.sh"
