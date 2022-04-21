<?php

class WalleeCheckoutPaymentModulesThemeContentView extends WalleeCheckoutPaymentModulesThemeContentView_parent
{

    public function __construct()
    {
	parent::__construct();

	$this->set_template_dir(DIR_FS_DOCUMENT_ROOT);
	$this->set_content_template('GXModules/Wallee/WalleePayment/Shop/Themes/All/checkout_payment_modules.html');
	$this->set_flat_assigns(true);
    }

    public function prepare_data()
    {
	$t_uninitialized_array = $this->get_uninitialized_variables([
	    'coo_order',
	    'coo_payment'
	]);

	if (empty($t_uninitialized_array)) {
	    $order = $this->coo_order;

	    if ($order->info['total'] > 0) {
		$payment_modules = $this->coo_payment;
		$this->set_methods_array($payment_modules->selection());

		$configuration = \MainFactory::create('WalleeStorage');
		$paymentMethods = json_decode($configuration->get('payment_methods'), true);

		$selection = $this->methods_array;
		$selection = \array_merge($selection, $paymentMethods);
		$radio_buttons = 0;

		foreach ($selection as $t_key => $t_method_array) {
		    $selection[$t_key]['radio_buttons'] = $radio_buttons;
		    if (($selection[$t_key]['id'] == $this->selected_payment_method) || ($t_key == 1)) {
			$selection[$t_key]['checked'] = 1;
		    }

		    if (sizeof($selection) > 1) {
			$input = xtc_draw_radio_field('payment', $selection[$t_key]['id'], ($selection[$t_key]['id'] == $this->selected_payment_method));
			$input = str_replace('type="radio"', 'id="' . $selection[$t_key]['id'] . '" type="radio"', $input);
			$selection[$t_key]['selection'] = $input;
		    } else {
			$input = xtc_draw_hidden_field('payment', $selection[$t_key]['id']);
			$input = str_replace('type="radio"', 'id="' . $selection[$t_key]['id'] . '" type="radio"', $input);
			$selection[$t_key]['selection'] = $input;
		    }

		    if (isset($selection[$t_key]['error']) == false) {
			$radio_buttons++;
		    }
		}

		$paymentMethodConfKey = 'configuration/MODULE_PAYMENT_WALLEE_';
		$query = xtc_db_query("SELECT `key` FROM `gx_configurations` WHERE `key` LIKE '" . xtc_db_input($paymentMethodConfKey) . "%' AND `type` = 'switcher' AND `value` = 'true'");
		$activePaymentMethods = [];
		while ($row = mysqli_fetch_assoc($query)) {
		    $activePaymentMethods[] = strtolower(str_replace($paymentMethodConfKey, '', $row['key']));
		}

		$array = [];
		foreach ($selection as $t_key => $t_method_array) {
		    if (strpos(strtolower($t_method_array['id']), 'wallee') !== false) {
			if (strtolower($t_method_array['id']) !== 'wallee') {
			    if (\in_array($t_method_array['id'], $activePaymentMethods)) {
				$array[] = $t_method_array;
			    }
			}
			unset($selection[$t_key]);
		    }
		}

		if (!empty($array)) {
		    $selection = array_merge([$array], $selection);
		}

		$this->set_content_data('module_content', $selection);
		$this->set_content_data('language', $_SESSION['language']);
	    }
	} else {
	    trigger_error("Variable(s) " . implode(', ',
		    $t_uninitialized_array) . " do(es) not exist in class "
		. get_class($this) . " or is/are null",
		E_USER_ERROR);
	}
    }
}