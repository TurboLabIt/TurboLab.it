<?php
// 📚 https://www.phpbb.com/support/docs/en/3.0/kb/article/phpbb3-cross-site-sessions-integration/
define('IN_PHPBB', true);
chdir(TLI_PROJECT_DIR . "public/forum/");
$phpbb_root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

require($phpbb_root_path . 'common.' . $phpEx);
require($phpbb_root_path . 'includes/functions_user.' . $phpEx);
require($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
require($phpbb_root_path . 'includes/message_parser.' . $phpEx);

$user->session_begin();
$auth->acl($user->data);
$user->setup();
