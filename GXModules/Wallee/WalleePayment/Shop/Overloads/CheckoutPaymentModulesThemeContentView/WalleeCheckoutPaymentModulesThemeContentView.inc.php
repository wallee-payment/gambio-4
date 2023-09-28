<?php

use GXModules\WalleePayment\Library\{Core\Settings\Struct\Settings, Helper\WalleeHelper};
use JTL\Cart\CartItem;
use JTL\Checkout\Bestellung;
use JTL\Helpers\PaymentMethod;
use JTL\Shop;
use Wallee\Sdk\Model\{AddressCreate, LineItemCreate, LineItemType, Transaction, TransactionPending};

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
				
				$createdTransactionId = $_SESSION['createdTransactionId'] ?? null;
				$arrayOfPossibleMethods = $_SESSION['arrayOfPossibleMethods'] ?? null;
				$addressCheck = $_SESSION['addressCheck'] ?? null;
				$currencyCheck = $_SESSION['currencyCheck'] ?? null;
				
				$billingAddress = $order->billing;
				$currency = $_SESSION['currency'];
				if ($addressCheck !== md5(json_encode((array)$billingAddress)) || $currencyCheck !== $currency || empty($_SESSION['addressCheck'])) {
					$arrayOfPossibleMethods = null;
					$this->updateTransaction($createdTransactionId);
					$_SESSION['addressCheck'] = md5(json_encode((array)$billingAddress));
					$_SESSION['currencyCheck'] = $currency;
				}
				
				if (!$arrayOfPossibleMethods) {
					$possiblePaymentMethods = $this->fetchPossiblePaymentMethods((string)$createdTransactionId);
					$_SESSION['possiblePaymentMethods'] = $possiblePaymentMethods;
					$arrayOfPossibleMethods = [];
					foreach ($possiblePaymentMethods as $possiblePaymentMethod) {
						$arrayOfPossibleMethods[] = 'wallee_' . WalleeHelper::slugify($possiblePaymentMethod->getName(), '_');
					}
					$_SESSION['arrayOfPossibleMethods'] = $arrayOfPossibleMethods;
				}
				
				
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
							if (\in_array($t_method_array['id'], $activePaymentMethods) && \in_array($t_method_array['id'], $arrayOfPossibleMethods)) {
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
	
	public function fetchPossiblePaymentMethods(string $transactionId)
	{
		$settings = new Settings();
		return $settings->getApiClient()->getTransactionService()
		  ->fetchPaymentMethods($settings->getSpaceId(), $transactionId, 'iframe');
	}
	
	
	/**
	 * @param Transaction $transaction
	 * @return void
	 */
	public function updateTransaction(int $transactionId): void
	{
		$order = (array)$this->coo_order;
		
		$settings = new Settings();
		$pendingTransaction = new TransactionPending();
		$pendingTransaction->setId($transactionId);
		
		$transaction = $this->getTransactionFromPortal($transactionId);
		$pendingTransaction->setVersion($transaction->getVersion());
		
		$lineItems = [];
		foreach ($order['products'] as $product) {
			$lineItem = new LineItemCreate();
			$lineItem->setName($product['name']);
			$lineItem->setUniqueId($product['id']);
			$lineItem->setSku($product['id']);
			$lineItem->setQuantity($product['qty']);
			$lineItem->setAmountIncludingTax(floatval((string)$product['final_price']));
			$lineItem->setType(LineItemType::PRODUCT);
			$lineItems[] = $lineItem;
		}
		
		$shippingCost = floatval((string)$order['info']['shipping_cost']);
		if ($shippingCost > 0) {
			$lineItem = new LineItemCreate();
			$lineItem->setName('Shipping: ' . $order['info']['shipping_method']);
			$lineItem->setUniqueId('shipping-' . $order['info']['shipping_class']);
			$lineItem->setSku('shipping-' . $order['info']['shipping_class']);
			$lineItem->setQuantity(1);
			$lineItem->setAmountIncludingTax($shippingCost);
			$lineItem->setType(LineItemType::SHIPPING);
			$lineItems[] = $lineItem;
		}
		
		$billingAddress = $this->getBillingAddress($order);
		$shippingAddress = $this->getShippingAddress($order);
		
		$pendingTransaction->setCurrency($_SESSION['currency']);
		$pendingTransaction->setLineItems($lineItems);
		$pendingTransaction->setMetaData([
		  'spaceId' => $settings->getSpaceId(),
		]);
		$pendingTransaction->setBillingAddress($billingAddress);
		$pendingTransaction->setShippingAddress($shippingAddress);
		
		$settings->getApiClient()->getTransactionService()
		  ->update($settings->getSpaceId(), $pendingTransaction);
	}
	
	private function getBillingAddress($order): AddressCreate
	{
		$billingAddress = new AddressCreate();
		$billingAddress->setStreet($order['billing']['street_address']);
		$billingAddress->setCity($order['billing']['city']);
		$billingAddress->setCountry($order['billing']['country']['iso_code_2']);
		$billingAddress->setEmailAddress($order['customer']['email_address']);
		$billingAddress->setFamilyName($order['billing']['lastname']);
		$billingAddress->setGivenName($order['billing']['firstname']);
		$billingAddress->setPostCode($order['billing']['postcode']);
		$billingAddress->setPostalState($order['billing']['state']);
		$billingAddress->setOrganizationName($order['billing']['company']);
		$billingAddress->setPhoneNumber($order['customer']['telephone']);
		$billingAddress->setSalutation($order['customer']['gender'] === 'm' ? 'Mr' : 'Ms');
		
		return $billingAddress;
	}
	
	private function getShippingAddress($order): AddressCreate
	{
		$shippingAddress = new AddressCreate();
		$shippingAddress->setStreet($order['delivery']['street_address']);
		$shippingAddress->setCity($order['delivery']['city']);
		$shippingAddress->setCountry($order['delivery']['country']['iso_code_2']);
		$shippingAddress->setEmailAddress($order['customer']['email_address']);
		$shippingAddress->setFamilyName($order['delivery']['lastname']);
		$shippingAddress->setGivenName($order['delivery']['firstname']);
		$shippingAddress->setPostCode($order['delivery']['postcode']);
		$shippingAddress->setPostalState($order['delivery']['state']);
		$shippingAddress->setOrganizationName($order['delivery']['company']);
		$shippingAddress->setPhoneNumber($order['customer']['telephone']);
		$shippingAddress->setSalutation($order['customer']['gender'] === 'm' ? 'Mr' : 'Ms');
		
		return $shippingAddress;
	}
	
	/**
	 * @param $transactionId
	 * @return Transaction|null
	 */
	public function getTransactionFromPortal($transactionId): ?Transaction
	{
		$settings = new Settings();
		return $settings->getApiClient()
		  ->getTransactionService()
		  ->read($settings->getSpaceId(), $transactionId);
	}
	
}
