<?php
spl_autoload_register(static function ($class) {
    $path = str_replace("\\", '/', str_replace("ILIAS\\Plugin\\ElectronicCourseReserve\\", '', $class)) . '.php';

    if (file_exists(ilElectronicCourseReservePlugin::getInstance()->getDirectory() . '/classes/' . $path)) {
        ilElectronicCourseReservePlugin::getInstance()->includeClass($path);
    }
});

require_once __DIR__ . '/libs/composer/vendor/autoload.php';
