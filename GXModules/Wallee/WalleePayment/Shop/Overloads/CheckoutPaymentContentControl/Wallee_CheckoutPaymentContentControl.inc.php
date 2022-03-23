<?php

use GXModules\WalleePayment\Library\Core\Settings\Struct\Settings;

class Wallee_CheckoutPaymentContentControl extends Wallee_CheckoutPaymentContentControl_parent
{
	public function proceed()
	{
		$_SESSION['gm_error_message'] = $this->getErrorMessage();
		return  parent::proceed();
	}

	/**
	 * @return string
	 * @throws \Wallee\Sdk\ApiException
	 * @throws \Wallee\Sdk\Http\ConnectionException
	 * @throws \Wallee\Sdk\VersioningException
	 */
	private function getErrorMessage()
	{
		$transactionId = $_SESSION['transactionID'] ?? null;

		if (empty($transactionId)) {
			return '';
		}

		$settings = new Settings();
		$transaction = $settings->getApiClient()->getTransactionService()->read($settings->getSpaceId(), $_SESSION['transactionID']);

		return isset($_GET['payment_error']) ? $transaction->getUserFailureMessage() : '';
	}
}