<?php declare(strict_types=1);

namespace GXModules\Wallee\WalleePayment\Shop\Classes\Model;

use GXModules\Wallee\WalleePayment\Shop\Classes\Entity\WalleeRefundEntity;

class WalleeRefundModel
{
	/**
	 * @param int $orderId
	 * @return array
	 */
	public static function getRefunds(int $orderId): array
	{
		$query = xtc_db_query("SELECT * FROM `wallee_refunds` WHERE order_id='" . xtc_db_input($orderId) . "'");
		$refunds = [];

		while ($row = mysqli_fetch_assoc($query)) {
			$refunds[] = new WalleeRefundEntity($row);
		}
		return $refunds;
	}

	/**
	 * @param array $refunds
	 * @return float
	 */
	public static function getTotalRefundsAmount(array $refunds): float
	{
		$total = 0;

		foreach ($refunds as $refund) {
			$total += $refund->getAmount();
		}

		return round($total, 2);
	}

	/**
	 * @param int $refundId
	 * @param int $orderId
	 * @param float $amount
	 */
	public static function createRefundRecord(int $refundId, int $orderId, float $amount): void
	{
		$insertData = [
			'refund_id' => $refundId,
			'order_id' => $orderId,
			'amount' => $amount,
			'created_at' => date('Y-m-d H:i:s')
		];

		try {
			xtc_db_perform('wallee_refunds', $insertData, 'insert');
		} catch (\Exception $e) {

		}
	}
}