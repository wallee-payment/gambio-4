<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit42a0c2ce1bc39489d6e8fa138c585f70
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
            $loader->prefixLengthsPsr4 = ComposerStaticInit42a0c2ce1bc39489d6e8fa138c585f70::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit42a0c2ce1bc39489d6e8fa138c585f70::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit42a0c2ce1bc39489d6e8fa138c585f70::$classMap;

        }, null, ClassLoader::class);
    }
}