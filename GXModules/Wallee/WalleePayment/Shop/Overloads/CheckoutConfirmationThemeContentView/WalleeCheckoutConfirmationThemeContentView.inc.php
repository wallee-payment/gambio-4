<?php

require_once DIR_FS_CATALOG . 'inc/get_transfer_charge_text.inc.php'; // Required in older shop versions.

class WalleeCheckoutConfirmationThemeContentView extends WalleeCheckoutConfirmationThemeContentView_parent
{
	public function prepare_data()
	{
		if (strpos($_SESSION['payment'], 'wallee') === false) {
			return parent::prepare_data();
		}

		$this->coo_order->info['payment_method'] = '';
		parent::prepare_data();
		
		$configurationStorage = MainFactory::create('WalleeStorage');
		$paymentMethodsArray = \json_decode($configurationStorage->get('payment_methods'), true);
		
		if ($paymentMethodsArray && \is_array($paymentMethodsArray)) {
			$currentPaymentMethod = \array_filter($paymentMethodsArray, function ($paymentMethod) {
				return ($paymentMethod['id'] == $_SESSION['payment']);
			});
			
			$this->content_array['PAYMENT_METHOD'] = $currentPaymentMethod[0]['titles'][$_SESSION['language']];
		}
		
		if (isset($_GET['payment_error']) && is_object(${$_GET['payment_error']}) && ($error = ${$_GET['payment_error']}->get_error())) {
			$this->content_array['error'] = $error['title'] . '<br />' . htmlspecialchars_wrapper($error['error']);
		}
	}
}
