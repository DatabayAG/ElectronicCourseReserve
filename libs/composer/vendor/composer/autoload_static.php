<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit7b1b3acfccdfcf8b965b1fe0e30c6526
{
    public static $files = array (
        '5255c38a0faeba867671b61dfda6d864' => __DIR__ . '/..' . '/paragonie/random_compat/lib/random.php',
    );

    public static $prefixLengthsPsr4 = array (
        'Z' => 
        array (
            'Zend\\Stdlib\\' => 12,
            'Zend\\Math\\' => 10,
            'Zend\\Crypt\\' => 11,
        ),
        'P' => 
        array (
            'Psr\\Container\\' => 14,
        ),
        'I' => 
        array (
            'Interop\\Container\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Zend\\Stdlib\\' => 
        array (
            0 => __DIR__ . '/..' . '/zendframework/zend-stdlib/src',
        ),
        'Zend\\Math\\' => 
        array (
            0 => __DIR__ . '/..' . '/zendframework/zend-math/src',
        ),
        'Zend\\Crypt\\' => 
        array (
            0 => __DIR__ . '/..' . '/zendframework/zend-crypt/src',
        ),
        'Psr\\Container\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/container/src',
        ),
        'Interop\\Container\\' => 
        array (
            0 => __DIR__ . '/..' . '/container-interop/container-interop/src/Interop/Container',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit7b1b3acfccdfcf8b965b1fe0e30c6526::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit7b1b3acfccdfcf8b965b1fe0e30c6526::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
