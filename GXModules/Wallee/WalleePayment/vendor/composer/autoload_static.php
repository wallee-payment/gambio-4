<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite056329d12f9c56c0cd32a50eac854ee
{
    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'Wallee\\Sdk\\' => 26,
            'WalleePayment\\' => 29,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Wallee\\Sdk\\' => 
        array (
            0 => __DIR__ . '/..' . '/wallee/sdk/lib',
        ),
        'WalleePayment\\' => 
        array (
            0 => __DIR__ . '/../..' . '/Wallee',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite056329d12f9c56c0cd32a50eac854ee::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite056329d12f9c56c0cd32a50eac854ee::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite056329d12f9c56c0cd32a50eac854ee::$classMap;

        }, null, ClassLoader::class);
    }
}
