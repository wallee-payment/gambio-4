<?php

namespace Wallee\WalleePayment\Components\Utility;

class UrlHelper
{
	/**
	 * @param $filename
	 * @return string
	 */
    public static function getModuleJsFile($filename): string
    {
        return static::getModulePath() . "Javascripts/$filename";
    }

	/**
	 * @return string
	 */
    public static function getModulePath(): string
    {
		return '/../../../../GXModules/Wallee/WalleePayment/';
    }
}
