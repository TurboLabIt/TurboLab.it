## Post-database restore queries
#
# The following queries are executed automatically by `scripts/db-restore.sh`
# after the database dump is restored.
#
# ⚠️ This file is for the DEV env only ⚠️
#
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/config/custom/dev/db-post-restore.sql

USE turbolab_it_forum;
UPDATE phpbb_config SET config_value = 'dev0.turbolab.it' WHERE config_name = 'server_name';
UPDATE phpbb_config SET config_value = 'https://dev0.turbolab.it' WHERE config_value = 'https://turbolab.it';
