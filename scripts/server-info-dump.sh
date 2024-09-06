#!/usr/bin/env bash

source $(dirname $(readlink -f $0))/script_begin.sh

fxHeader 'Dump server info'

fxTitle "Output directory..."
DUMP_DIR=${PROJECT_DIR}var/server-info-dump/
fxMessage "##${DUMP_DIR}##"

if [ ! -d "${DUMP_DIR}" ]; then

    mkdir -p "${DUMP_DIR}"
    fxOK "Created!"

else

    fxOK "Directory exists"
fi

fxTitle "lsb_release..."
lsb_release -a > "${DUMP_DIR}lsb_release.txt" 2>&1

fxTitle "git commit..."
git rev-parse --short HEAD > "${DUMP_DIR}git-commit.txt" 2>&1

source "${SCRIPT_DIR}/script_end.sh"
