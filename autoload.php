<?php
spl_autoload_register(function ($class) {
    $path = str_replace("\\", '/', str_replace("ILIAS\\Plugin\\ElectronicCourseReserve\\", '', $class)) . '.php';

    if (file_exists(ilElectronicCourseReservePlugin::getInstance()->getDirectory() . '/classes/' . $path)) {
        ilElectronicCourseReservePlugin::getInstance()->includeClass($path);
    }
}, true, true);

require_once dirname(__FILE__) . '/libs/composer/vendor/autoload.php';