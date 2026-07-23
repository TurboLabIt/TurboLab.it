## Post-database restore queries
#
# The following queries are executed automatically by `scripts/db-restore.sh`
# after the database dump is restored.
#
# ⚠️ This file runs both on STAGING and on DEV env ⚠️
#
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/config/custom/staging/db-post-restore.sql

USE turbolab_it_forum;
UPDATE phpbb_config SET config_value = 'next.turbolab.it' WHERE config_name = 'server_name';
UPDATE phpbb_config SET config_value = 'https://next.turbolab.it' WHERE config_value = 'https://turbolab.it';

UPDATE phpbb_config SET config_value = 'localhost' WHERE config_name = 'smtp_host';
UPDATE phpbb_config SET config_value = 25 WHERE config_name = 'smtp_port';
UPDATE phpbb_config SET config_value = '' WHERE config_name = 'smtp_username';
UPDATE phpbb_config SET config_value = '' WHERE config_name = 'smtp_password';

TRUNCATE phpbb_sessions_keys;
TRUNCATE phpbb_sessions;
