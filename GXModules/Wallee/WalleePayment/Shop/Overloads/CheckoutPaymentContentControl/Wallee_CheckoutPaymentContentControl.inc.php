<?php

use GXModules\WalleePayment\Library\{Core\Settings\Options\Integration, Core\Settings\Struct\Settings};
use Wallee\Sdk\Model\{AddressCreate, Transaction, TransactionCreate};

class Wallee_CheckoutPaymentContentControl extends Wallee_CheckoutPaymentContentControl_parent
{
    public function proceed()
    {
	    $settings = new Settings();
	    $createdTransaction = $this->createRemoteTransaction($settings);
	    $_SESSION['createdTransactionId'] = $createdTransaction->getId();
		
		$_SESSION['gm_error_message'] = $this->getErrorMessage();
		return parent::proceed();
    }

    /**
     * @return string
     * @throws \Wallee\Sdk\ApiException
     * @throws \Wallee\Sdk\Http\ConnectionException
     * @throws \Wallee\Sdk\VersioningException
     */
    private function getErrorMessage()
    {
        $transactionId = $_SESSION['createdTransactionId'] ?? null;

	    if (!isset($_GET['payment_error']) || empty($transactionId)) {
	        return '';
	    }

	    $settings = new Settings();
	    $transaction = $settings->getApiClient()->getTransactionService()->read($settings->getSpaceId(), $_SESSION['createdTransactionId']);
	    $languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
	    if (!empty($_GET['payment_error'])) {
	        return $languageTextManager->get_text($_GET['payment_error'], 'wallee');
	    }

	    return $transaction->getUserFailureMessage();
    }
	
	private function createRemoteTransaction(Settings $settings): Transaction
	{
		$lineItems = [];
		$billingAddress = $this->getBillingAddress();
		$shippingAddress = $this->getShippingAddress();
		$transactionPayload = new TransactionCreate();
		$transactionPayload->setCurrency($_SESSION['currency']);
		$transactionPayload->setLineItems($lineItems);
		$transactionPayload->setBillingAddress($billingAddress);
		$transactionPayload->setShippingAddress($shippingAddress);
		$transactionPayload->setMetaData([
		  'spaceId' => $settings->getSpaceId(),
		]);
		$transactionPayload->setSpaceViewId($settings->getSpaceViewId());
		$transactionPayload->setAutoConfirmationEnabled(getenv('WALLEE_AUTOCONFIRMATION_ENABLED') ?: false);
		
		if ($settings->getIntegration() === Integration::PAYMENT_PAGE) {
			$paymentMethodConfigurationId = $this->getPaymentMethodConfigurationId();
			if ($paymentMethodConfigurationId) {
				$transactionPayload->setAllowedPaymentMethodConfigurations([$paymentMethodConfigurationId]);
			}
		}
		
		$transactionPayload->setSuccessUrl(xtc_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
		$transactionPayload->setFailedUrl(xtc_href_link(FILENAME_CHECKOUT_PAYMENT . '?payment_error', '', 'SSL'));
		$createdTransaction = $settings->getApiClient()->getTransactionService()->create($settings->getSpaceId(), $transactionPayload);
		
		return $createdTransaction;
	}
	
	private function getBillingAddress(): AddressCreate
	{
		$billingAddress = new AddressCreate();
		$billingAddress->setCountry($_SESSION['customer_country_iso']);
		$billingAddress->setFamilyName($_SESSION['customer_last_name']);
		$billingAddress->setGivenName($_SESSION['customer_first_name']);
		
		return $billingAddress;
	}
	
	private function getShippingAddress(): AddressCreate
	{
		$shippingAddress = new AddressCreate();
		$shippingAddress->setCountry($_SESSION['customer_country_iso']);
		$shippingAddress->setFamilyName($_SESSION['customer_last_name']);
		$shippingAddress->setGivenName($_SESSION['customer_first_name']);
		
		return $shippingAddress;
	}
}
