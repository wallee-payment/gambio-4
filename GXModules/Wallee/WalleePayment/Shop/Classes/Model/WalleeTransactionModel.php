<?php declare(strict_types=1);

namespace GXModules\Wallee\WalleePayment\Shop\Classes\Model;

use GXModules\WalleePayment\Library\Core\Settings\Struct\Settings;
use Wallee\Sdk\Model\TransactionState;
use GXModules\Wallee\WalleePayment\Shop\Classes\Entity\WalleeTransactionEntity;

class WalleeTransactionModel
{
	public const TRANSACTION_STATE_FULFILL = 'FULFILL';
	public const TRANSACTION_STATE_REFUNDED = 'REFUNDED';
	public const TRANSACTION_STATE_PARTIALLY_REFUNDED = 'PARTIALY_REFUNDED';
	public const TRANSACTION_STATE_PAID = 'PAID';

	public function getByOrderId(int $orderId): ?WalleeTransactionEntity
	{
		$orderData = $this->getFromDbByOrderId($orderId);

		if (empty($orderData)) {
			return null;
		}

		return new WalleeTransactionEntity($orderData);
	}

	/**
	 * @param Settings $settings
	 * @param string $transactionId
	 * @param string $orderId
	 * @param array $orderData
	 */
	public function create(Settings $settings, string $transactionId, string $orderId, array $orderData): void
	{
		$insertData = [
			'transaction_id' => $transactionId,
			'data' => json_encode($orderData),
			'payment_method' => $orderData['info']['payment_method'],
			'order_id' => $orderId,
			'space_id' => $settings->getSpaceId(),
			'state' => TransactionState::PROCESSING,
			'created_at' => date('Y-m-d H:i:s')
		];
		xtc_db_perform('wallee_transactions', $insertData, 'insert');
	}

	/**
	 * @param string $newStatus
	 * @param int $orderId
	 * @throws \Exception
	 */
	public function updateTransactionStatus(string $newStatus, int $orderId): void
	{
		xtc_db_perform(
			'wallee_transactions',
			['state' => $newStatus],
			'update',
			'order_id = ' . xtc_db_input($orderId)
		);
	}

	/**
	 * @param int $orderId
	 * @return array
	 */
	private function getFromDbByOrderId(int $orderId): array {
		$query = xtc_db_query("SELECT * FROM `wallee_transactions` WHERE order_id = " . xtc_db_input($orderId));
		return xtc_db_fetch_array($query);
	}
}