<?php declare(strict_types=1);

namespace GXModules\WalleePayment\Library\Helper;

use MainFactory;
use GXModules\WalleePayment\Library\Core\Service\PaymentService;

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
	
	/**
	 * @return int|null
	 * @throws \Wallee\Sdk\ApiException
	 * @throws \Wallee\Sdk\Http\ConnectionException
	 * @throws \Wallee\Sdk\VersioningException
	 */
	public static function getPaymentMethodConfigurationId()
	{
		$paymentMethodConfigurationId = null;
		$paymentService = new PaymentService(MainFactory::create('WalleeStorage'));
		$paymentMethodConfigurations = $paymentService->getPaymentMethodConfigurations();
		foreach ($paymentMethodConfigurations as $paymentMethodConfiguration) {
			$slug = 'wallee_' . trim(strtolower(self::slugify($paymentMethodConfiguration->getName())));
			if ($_SESSION['choosen_payment_method'] === $slug) {
				$paymentMethodConfigurationId = $paymentMethodConfiguration->getId();
				break;
			}
		}
		
		return $paymentMethodConfigurationId;
	}
}
