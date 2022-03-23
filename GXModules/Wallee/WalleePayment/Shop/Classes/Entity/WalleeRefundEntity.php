<?php declare(strict_types=1);

namespace GXModules\Wallee\WalleePayment\Shop\Classes\Entity;

class WalleeRefundEntity
{
	public const FIELD_ID = 'id';
	public const FIELD_REFUND_ID = 'refund_id';
	public const FIELD_ORDER_ID = 'order_id';
	public const FIELD_AMOUNT = 'amount';
	public const FIELD_CREATED_AT = 'created_at';

	/**
	 * @var int $id
	 */
	public $id;

	/**
	 * @var int $refundId
	 */
	public $refundId;

	/**
	 * @var int $orderId
	 */
	public $orderId;

	/**
	 * @var float $amount
	 */
	public $amount;

	/**
	 * @var string $createdAt
	 */
	public $createdAt;

	/**
	 * @param array $entityData
	 */
	public function __construct(array $entityData)
	{
		$this->setId((int)$entityData[self::FIELD_ID])
			->setRefundId((int)$entityData[self::FIELD_REFUND_ID])
			->setOrderId((int)(bool)$entityData[self::FIELD_ORDER_ID])
			->setAmount((float)$entityData[self::FIELD_AMOUNT])
			->setCreatedAt($entityData[self::FIELD_CREATED_AT]);
	}

	/**
	 * @return int
	 */
	public function getId(): int
	{
		return $this->id;
	}

	/**
	 * @param int $id
	 * @return $this
	 */
	public function setId(int $id): WalleeRefundEntity
	{
		$this->id = $id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getRefundId(): int
	{
		return $this->refundId;
	}

	/**
	 * @param int $refundId
	 * @return $this
	 */
	public function setRefundId(int $refundId): WalleeRefundEntity
	{
		$this->refundId = $refundId;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getOrderId(): int
	{
		return $this->orderId;
	}

	/**
	 * @param int $orderId
	 * @return $this
	 */
	public function setOrderId(int $orderId): WalleeRefundEntity
	{
		$this->orderId = $orderId;
		return $this;
	}

	/**
	 * @return float
	 */
	public function getAmount(): float
	{
		return $this->amount;
	}

	/**
	 * @param float $amount
	 * @return $this
	 */
	public function setAmount(float $amount): WalleeRefundEntity
	{
		$this->amount = $amount;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getCreatedAt(): string
	{
		return $this->createdAt;
	}

	/**
	 * @param string $createdAt
	 * @return $this
	 */
	public function setCreatedAt(string $createdAt): WalleeRefundEntity
	{
		$this->createdAt = $createdAt;
		return $this;
	}

}