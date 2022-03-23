<?php

namespace GXModules\WalleePayment\Library\Core\Api\WebHooks\Struct;

/**
 * Class WebHookRequest
 * @package GXModules\WalleePayment\Library\Core\Api\WebHooks\Struct
 */
class WebHookRequest
{
	public const PAYMENT_METHOD_CONFIGURATION = 'PaymentMethodConfiguration';
	public const REFUND = 'Refund';
	public const TRANSACTION = 'Transaction';
	public const TRANSACTION_INVOICE = 'TransactionInvoice';
}