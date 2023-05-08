<?php

class WalleeCheckoutConfirmationContentControl extends WalleeCheckoutConfirmationContentControl_parent
{
	public function proceed()
	{
		$choosenPaymentMethod = xtc_db_prepare_input($this->v_data_array['POST']['payment']) ?? '';
		if (strpos($choosenPaymentMethod, 'wallee') === false) {
			return parent::proceed();
		}
		
		$this->v_data_array['POST']['payment'] = 'wallee';
		parent::proceed();
		$_SESSION['choosen_payment_method'] = $choosenPaymentMethod;
	}
}
