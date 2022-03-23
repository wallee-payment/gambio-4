<?php declare(strict_types=1);

namespace GXModules\Wallee\WalleePayment\Shop\Classes\Entity;

class WalleeTransactionEntity
{
	public const FIELD_ID = 'id';
	public const FIELD_TRANSACTION_ID = 'transaction_id';
	public const FIELD_CONFIRMATION_EMAIL_SENT = 'confirmation_email_sent';
	public const FIELD_DATA = 'data';
	public const FIELD_PAYMENT_METHOD = 'payment_method';
	public const FIELD_ORDER_ID = 'order_id';
	public const FIELD_SPACE_ID = 'space_id';
	public const FIELD_STATE = 'state';
	public const FIELD_CREATED_AT = 'created_at';
	public const FIELD_UPDATED = 'updated_at';

	/**
	 * @var int $id
	 */
	public $id;

	/**
	 * @var int $transactionId
	 */
	public $transactionId;

	/**
	 * @var int $confirmationEmailSent
	 */
	public $confirmationEmailSent;

	/**
	 * @var string $data
	 */
	public $data;

	/**
	 * @var string $paymentMethod
	 */
	public $paymentMethod;

	/**
	 * @var int $orderId
	 */
	public $orderId;

	/**
	 * @var int $spaceId
	 */
	public $spaceId;

	/**
	 * @var string $state
	 */
	public $state;

	/**
	 * @var string $createdAt
	 */
	public $createdAt;

	/**
	 * @var string $updatedAt
	 */
	public $updatedAt;

	/**
	 * @param array $entityData
	 */
	public function __construct(array $entityData)
	{
		$this->setId((int)$entityData[self::FIELD_ID])
			->setTransactionId((int)$entityData[self::FIELD_TRANSACTION_ID])
			->setConfirmationEmailSent((int)(bool)$entityData[self::FIELD_CONFIRMATION_EMAIL_SENT])
			->setData($entityData[self::FIELD_DATA])
			->setPaymentMethod($entityData[self::FIELD_PAYMENT_METHOD])
			->setOrderId((int)$entityData[self::FIELD_ORDER_ID])
			->setSpaceId((int)$entityData[self::FIELD_SPACE_ID])
			->setState($entityData[self::FIELD_STATE])
			->setCreatedAt($entityData[self::FIELD_CREATED_AT])
			->setUpdatedAt($entityData[self::FIELD_UPDATED]);
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
	 * @return WalleeTransactionEntity
	 */
	public function setId(int $id): WalleeTransactionEntity
	{
		$this->id = $id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getTransactionId(): int
	{
		return $this->transactionId;
	}

	/**
	 * @param int $transactionId
	 * @return WalleeTransactionEntity
	 */
	public function setTransactionId(int $transactionId): WalleeTransactionEntity
	{
		$this->transactionId = $transactionId;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getConfirmationEmailSent(): int
	{
		return $this->confirmationEmailSent;
	}

	/**
	 * @param int $confirmationEmailSent
	 * @return WalleeTransactionEntity
	 */
	public function setConfirmationEmailSent(int $confirmationEmailSent): WalleeTransactionEntity
	{
		$this->confirmationEmailSent = $confirmationEmailSent;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getData(): string
	{
		return $this->data;
	}

	/**
	 * @param string $data
	 * @return WalleeTransactionEntity
	 */
	public function setData(string $data): WalleeTransactionEntity
	{
		$this->data = $data;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getPaymentMethod(): string
	{
		return $this->paymentMethod;
	}

	/**
	 * @param string $paymentMethod
	 * @return WalleeTransactionEntity
	 */
	public function setPaymentMethod(string $paymentMethod): WalleeTransactionEntity
	{
		$this->paymentMethod = $paymentMethod;
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
	 * @return WalleeTransactionEntity
	 */
	public function setOrderId(int $orderId): WalleeTransactionEntity
	{
		$this->orderId = $orderId;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getSpaceId(): int
	{
		return $this->spaceId;
	}

	/**
	 * @param int $spaceId
	 * @return WalleeTransactionEntity
	 */
	public function setSpaceId(int $spaceId): WalleeTransactionEntity
	{
		$this->spaceId = $spaceId;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getState(): string
	{
		return $this->state;
	}

	/**
	 * @param string $state
	 * @return WalleeTransactionEntity
	 */
	public function setState(string $state): WalleeTransactionEntity
	{
		$this->state = $state;
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
	 * @return WalleeTransactionEntity
	 */
	public function setCreatedAt(string $createdAt): WalleeTransactionEntity
	{
		$this->createdAt = $createdAt;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getUpdatedAt(): string
	{
		return $this->updatedAt;
	}

	/**
	 * @param string $updatedAt
	 * @return WalleeTransactionEntity
	 */
	public function setUpdatedAt(?string $updatedAt = null): WalleeTransactionEntity
	{
		$this->updatedAt = $updatedAt;
		return $this;
	}

}