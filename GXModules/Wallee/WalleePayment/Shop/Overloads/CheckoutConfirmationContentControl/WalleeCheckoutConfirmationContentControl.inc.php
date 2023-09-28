<?php

class WalleeCheckoutConfirmationContentControl extends WalleeCheckoutConfirmationContentControl_parent
{
	public function proceed()
	{
		$currencyCheck = $_SESSION['currencyCheck'] ?? null;
		if ($_SESSION['currency'] != $currencyCheck) {
			$this->set_redirect_url(xtc_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
		}

		$choosenPaymentMethod = xtc_db_prepare_input($this->v_data_array['POST']['payment']) ?? '';
		if (strpos($choosenPaymentMethod, 'wallee') === false) {
			return parent::proceed();
		}
		
		$this->v_data_array['POST']['payment'] = 'wallee';
		$_SESSION['choosen_payment_method'] = $choosenPaymentMethod;
		parent::proceed();
	}
}
