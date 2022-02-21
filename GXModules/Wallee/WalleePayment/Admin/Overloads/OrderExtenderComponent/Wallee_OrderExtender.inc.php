<?php declare(strict_types=1);

use Wallee\Sdk\Model\TransactionState;

class Wallee_OrderExtender extends Wallee_OrderExtender_parent
{
	public function proceed()
	{
		require(DIR_FS_CATALOG . DIR_WS_CLASSES . 'xtcPrice.php');
		$xtPrice = new xtcPrice(DEFAULT_CURRENCY, $_SESSION['customers_status']['customers_status_id']);

		$contentView = MainFactory::create('ContentView');
		$contentView->set_template_dir(DIR_FS_DOCUMENT_ROOT);
		$contentView->set_content_template('GXModules/Wallee/WalleePayment/Admin/Templates/wallee_transaction_panel.html');
		$contentView->set_flat_assigns(true);
		$contentView->set_caching_enabled(false);

		$contentView->set_content_data('translateSection', 'wallee');
		$contentView->set_content_data('moduleName', 'Wallee');

		$orderId = (int)$_GET['oID'];

		$query = xtc_db_query("SELECT * FROM `wallee_transaction` WHERE order_id = " . xtc_db_input($orderId));
		$transactionData = xtc_db_fetch_array($query);
		$transactionInfo = \json_decode($transactionData['data'], true);

		$contentView->set_content_data('orderId', $orderId);
		$refunds = $this->getRefunds($orderId);
		$contentView->set_content_data('refunds', $refunds);
		$contentView->set_content_data('xtPrice', $xtPrice);
		$contentView->set_content_data('totalSumOfRefunds', $this->getTotalRefundsAmount($refunds));
		$contentView->set_content_data('totalOrderAmount', round($transactionInfo['info']['total'], 2));
		$amountToBeRefunded = round(floatval($transactionInfo['info']['total']) - $this->getTotalRefundsAmount($refunds), 2);
		$contentView->set_content_data('amountToBeRefunded', number_format($amountToBeRefunded, 2));

		$query = xtc_db_query("SELECT * FROM `wallee_transaction` WHERE order_id = " . xtc_db_input($orderId));
		$transactionData = xtc_db_fetch_array($query);
		$contentView->set_content_data('transactionState', $transactionData['state']);
		$contentView->set_content_data('authorizedState', TransactionState::AUTHORIZED);
		$contentView->set_content_data('fulfillState', TransactionState::FULFILL);

		$languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
		$this->v_output_buffer['below_product_data_heading'] = 'Wallee ' . $languageTextManager->get_text('transaction_panel', 'wallee');
		$this->v_output_buffer['below_product_data'] = $contentView->get_html();

		$this->addContent();
		parent::proceed();
	}

	/**
	 * @param int $orderId
	 * @return array
	 */
	private function getRefunds(int $orderId): array
	{
		$query = xtc_db_query("SELECT * FROM `wallee_refunds` WHERE order_id='" . xtc_db_input($orderId) . "'");
		$refunds = [];

		while ($row = mysqli_fetch_assoc($query)) {
			$refunds[] = $row;
		}
		return $refunds;
	}

	/**
	 * @param array $refunds
	 * @return float
	 */
	private function getTotalRefundsAmount(array $refunds): float
	{
		$total = 0;
		foreach ($refunds as $refund) {
			$total += $refund['amount'];
		}

		return round($total, 2);
	}
}