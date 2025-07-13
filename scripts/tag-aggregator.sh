#!/usr/bin/env bash
source $(dirname $(readlink -f $0))/script_begin.sh

wsuSymfony console TagAggregator "$@"
wsuSymfony console cache:clear
