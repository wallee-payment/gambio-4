<?php

class Wallee_payment extends Wallee_payment_parent
{
	public function __construct($module = '')
	{
		$payment = $_SESSION['payment'] ?? '';
		parent::__construct($module);
		if (strpos(strtolower($payment), 'wallee') !== false) {
			$_SESSION['payment'] = $payment;
		}
	}
}
