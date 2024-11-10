#!/usr/bin/env bash
## 📚

source $(dirname $(readlink -f $0))/script_begin.sh

fxTitle "Checking if IMAP for PHP ${PHP_VER} is installed..."

if dpkg -l | grep -q "php${PHP_VER}-imap"; then

  fxOK "Yes, it is."

else

    fxInfo "No, it isn't. Installing it now..."
    sudo apt update
    sudo apt install php${PHP_VER}-imap -y
fi

wsuSymfony console EmailBounceManager "$@"
