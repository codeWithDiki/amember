<?php

defined('E_DEPRECATED')
    || define('E_DEPRECATED', 8192);

$_appPath = $_realAppPath = realpath(dirname(__FILE__) . '/application');

// Define path to application directory
defined('APPLICATION_PATH') || define('APPLICATION_PATH', $_appPath);
defined('AM_APPLICATION_PATH') || define('AM_APPLICATION_PATH', APPLICATION_PATH);

if (empty($_amAutoloader) || !defined('AM_SKIP_INIT_AUTOLOADER'))
{
    require_once __DIR__.'/library/vendor/autoload.php';
}

$_amApp = new Am_App(
    defined('APPLICATION_CONFIG') ?
        APPLICATION_CONFIG : $_realAppPath . '/configs/config.php');
$_amApp->bootstrap();

$_event = new Am_Event_GlobalIncludes();
Am_Di::getInstance()->hook->call(Am_Event::GLOBAL_INCLUDES, $_event);
foreach ($_event->get() as $_fn)
    include_once $_fn;
unset($_event);
Am_Di::getInstance()->hook->call(Am_Event::GLOBAL_INCLUDES_FINISHED);
