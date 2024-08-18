## Post-database restore queries
#
# The following queries are executed automatically by `scripts/db-restore.sh`
# after the database dump is restored.
#
# ⚠️ This file runs both on STAGING and on DEV env ⚠️
#
# 🪄 Based on https://github.com/TurboLabIt/webstackup/blob/master/my-app-template/config/custom/staging/db-post-restore.sql

TRUNCATE turbolab_it_next-forum.phpbb_sessions_keys;
TRUNCATE turbolab_it_next-forum.phpbb_sessions;
