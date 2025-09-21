#!/usr/bin/env bash
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/scripts/reindex.sh

source $(dirname $(readlink -f $0))/script_begin.sh
fxHeader "🔍 Reindex"

## 📚 https://github.com/meilisearch/meilisearch-symfony/wiki/index-data-into-meilisearch#indexing-manually

fxTitle "Creating the index..."
wsuSymfony console meili:create

fxTitle "Clearing the index..."
wsuSymfony console meili:clear

fxTitle "Importing..."
wsuSymfony console meili:import

#curl -X POST 'http://127.0.0.1:7700/indexes/app_tli_articles/search' -H 'Content-Type: application/json' -H 'Authorization: Bearer aSampleMasterKey' --data-binary '{ "q": "windows" }'

source ${SCRIPT_DIR}/script_end.sh
