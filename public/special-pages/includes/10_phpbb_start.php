<?php
// 📚 https://www.phpbb.com/support/docs/en/3.0/kb/article/phpbb3-cross-site-sessions-integration/
define('IN_PHPBB', true);
$phpbb_root_path = '../../public/forum/';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

require($phpbb_root_path . 'common.' . $phpEx);
require($phpbb_root_path . 'includes/functions_user.' . $phpEx);

$user->session_begin();
$auth->acl($user->data);
$user->setup();
