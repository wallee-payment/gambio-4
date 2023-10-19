<?php

class Wallee_CheckoutSuccessContentControl extends Wallee_CheckoutSuccessContentControl_parent
{
	public function proceed()
	{
		if (strpos($_SESSION['payment'] ?? '', 'wallee') !== false) {
			$coo_checkout_process = MainFactory::create_object('CheckoutProcessProcess');
			$coo_checkout_process->set_data('GET', $_GET);
			$coo_checkout_process->set_data('POST', $_POST);
			$coo_checkout_process->set_data('GLOBALS', $GLOBALS);
			
			$coo_checkout_process->set_('coo_order_total', new order_total());
			$coo_checkout_process->set_('coo_properties', MainFactory::create_object('PropertiesControl'));
			$coo_checkout_process->set_('coo_order', $_SESSION['global_order']);
			$coo_checkout_process->process_products();
			
			$this->reset();
		}

		if ($_SESSION['order_id'] !== null && $_SESSION['email_sent_' . $_SESSION['order_id']] !== true) {
		    $this->sendEmail();
		}
		
		parent::proceed();
		return true;
	}
	
	public function reset()
	{
		$_SESSION['cart']->reset(true);
		
		// unregister session variables used during checkout
		unset($_SESSION['sendto']);
		unset($_SESSION['billto']);
		unset($_SESSION['shipping']);
		unset($_SESSION['payment']);
		unset($_SESSION['comments']);
		unset($_SESSION['last_order']);
		unset($_SESSION['tmp_oID']);
		unset($_SESSION['cc']);
		unset($_SESSION['nvpReqArray']);
		unset($_SESSION['reshash']);
		$GLOBALS['last_order'] = $this->order_id;
		
		//GV Code Start
		if (isset($_SESSION['credit_covers'])) {
			unset($_SESSION['credit_covers']);
		}
		unset($_SESSION['transactionID']);
		unset($_SESSION['payment_methods_title']);
		unset($_SESSION['createdTransactionId']);
		unset($_SESSION['possiblePaymentMethod']);
		unset($_SESSION['javascriptUrl']);
		unset($_SESSION['integration']);
	}
	
	protected function sendEmail()
	{
	    $coo_send_order_process = MainFactory::create_object('SendOrderProcess');
	    $coo_send_order_process->set_('order_id', $_SESSION['order_id']);
	    $coo_send_order_process->proceed();
	    $_SESSION['email_sent_' . $_SESSION['order_id']] = true;
	}
 
}
