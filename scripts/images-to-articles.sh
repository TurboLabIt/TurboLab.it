#!/usr/bin/env bash
## 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-articles.md

source $(dirname $(readlink -f $0))/script_begin.sh
wsuSymfony console ImagesToArticles "$@"
