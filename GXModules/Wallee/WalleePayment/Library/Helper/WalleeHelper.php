<?php declare(strict_types=1);

namespace GXModules\WalleePayment\Library\Helper;

class WalleeHelper
{
	/**
	 * @param string $text
	 * @param string $divider
	 * @return string
	 */
	public static function slugify(string $text, string $divider = '_'): string
	{
		// replace non letter or digits by divider
		$text = preg_replace('~[^\pL\d]+~u', $divider, $text);

		// transliterate
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

		// remove unwanted characters
		$text = preg_replace('~[^-\w]+~', '', $text);

		// trim
		$text = trim($text, $divider);

		// remove duplicate divider
		$text = preg_replace('~-+~', $divider, $text);

		// lowercase
		$text = strtolower($text);

		return $text;
	}
}