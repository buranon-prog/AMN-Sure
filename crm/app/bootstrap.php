<?php
// โหลดทุกอย่างที่แอปต้องใช้ (ใช้ร่วมกันทั้ง index.php, install.php และ tests)

define('APP_DIR', __DIR__);
define('ROOT_DIR', dirname(__DIR__));
if (!defined('STORAGE_DIR')) define('STORAGE_DIR', ROOT_DIR . '/storage');
define('APP_VERSION', '1.0.0');
define('MIN_PHP', '7.4.0');

mb_internal_encoding('UTF-8');

$cfgFile = APP_DIR . '/config.php';
$GLOBALS['APP_CONFIG'] = is_file($cfgFile) ? (require $cfgFile) : [];
date_default_timezone_set($GLOBALS['APP_CONFIG']['timezone'] ?? 'Asia/Bangkok');

require APP_DIR . '/lib/support.php';
require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/perm.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/audit.php';
require APP_DIR . '/lib/labels.php';
require APP_DIR . '/lib/view.php';
require APP_DIR . '/lib/files.php';
require APP_DIR . '/lib/seed.php';

foreach (glob(APP_DIR . '/services/*.php') as $f) require $f;

function app_installed(): bool
{
    return (bool) config('db');
}
