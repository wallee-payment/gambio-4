<?php declare(strict_types=1);

if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
	require_once dirname(__DIR__) . '/vendor/autoload.php';
}

use GXModules\WalleePayment\Library\Core\Service\PaymentService;

/**
 * Class WalleeModuleCenterModule
 */
class WalleeModuleCenterModule extends AbstractModuleCenterModule
{
	/**
	 * @var PaymentService $paymentService
	 */
	protected $paymentService;

	protected function _init(): void
	{
		$this->paymentService = new PaymentService();
		$this->name = 'Wallee';
		$this->title = 'Wallee ' . $this->languageTextManager->get_text('payment', 'wallee');
		$this->description = 'Wallee ' . $this->languageTextManager->get_text('description', 'wallee');
		$this->sortOrder = 10000;
	}

	/**
	 * Installs the module
	 */
	public function install(): void
	{
		try {
			xtc_db_query("
CREATE TABLE `wallee_transaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT, 
  `transaction_id` binary(16) NOT NULL,
  `confirmation_email_sent` tinyint(1) NOT NULL DEFAULT '0',
  `data` json NOT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` binary(16) NOT NULL,
  `space_id` int unsigned NOT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		");

			xtc_db_query("
CREATE TABLE `wallee_refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `refund_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `amount` decimal(10,0) NOT NULL,
  `created_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_refund_id_order_id` (`refund_id`,`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		} catch (\Exception $e) {

		}

		parent::install();
	}

	/**
	 * Uninstalls the module
	 */
	public function uninstall()
	{
		parent::uninstall();

		$this->paymentService->removeModuleFiles();

		try {
			xtc_db_query("DROP TABLE `wallee_transaction`");
			xtc_db_query("DROP TABLE `wallee_refunds`");
		} catch (\Exception $e) {

		}
	}
}
