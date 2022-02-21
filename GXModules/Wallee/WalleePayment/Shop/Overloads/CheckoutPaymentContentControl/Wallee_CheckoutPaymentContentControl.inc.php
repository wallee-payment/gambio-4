<?php

use GXModules\WalleePayment\Library\{Core\Settings\Options\Integration,
	Core\Settings\Struct\Settings,
	Helper\WalleeHelper
};
use Wallee\Sdk\Model\{AddressCreate, LineItemCreate, LineItemType, Transaction, TransactionCreate};

// include classes
require_once(DIR_WS_CLASSES . 'payment.php');
require_once(DIR_WS_CLASSES . 'order_total.php');
MainFactory::load_class('CheckoutControl');

class Wallee_CheckoutPaymentContentControl extends Wallee_CheckoutPaymentContentControl_parent
{
	protected $coo_payment;

	public function __construct()
	{
		parent::__construct();
	}

	public function proceed()
	{
		unset($_SESSION['tmp_oID']);

		// moneybookers
		unset($_SESSION['transaction_id']);

		if ($this->check_stock() == false) {
			$this->set_redirect_url(xtc_href_link(FILENAME_SHOPPING_CART));
			return true;
		}

		if ($this->check_cart_id() == false || $this->check_shipping() == false) {
			$this->set_redirect_url(xtc_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
			return true;
		}

		if (isset($_SESSION['credit_covers'])) {
			unset($_SESSION['credit_covers']); //ICW ADDED FOR CREDIT CLASS SYSTEM
		}

		// if no billing destination address was selected, use the customers own address as default
		if (!isset($_SESSION['billto'])) {
			$_SESSION['billto'] = $_SESSION['customer_default_address_id'];
		} else {
			// verify the selected billing address
			$check_address_query = xtc_db_query("SELECT COUNT(*) AS total
													FROM " . TABLE_ADDRESS_BOOK . "
													WHERE
														customers_id = '" . (int)$_SESSION['customer_id'] . "' AND
														address_book_id = '" . (int)$_SESSION['billto'] . "'");
			$check_address = xtc_db_fetch_array($check_address_query);

			if ($check_address['total'] != '1') {
				$_SESSION['billto'] = $_SESSION['customer_default_address_id'];

				if (isset($_SESSION['payment'])) {
					unset($_SESSION['payment']);
				}
			}
		}

		if (!isset($_SESSION['sendto']) || $_SESSION['sendto'] == '') {
			$_SESSION['sendto'] = $_SESSION['billto'];
		}

		/* xtPrice needs to be re-instantiated after setting $_SESSION['sendto'] and $_SESSION_['billto']
		   to ensure correct calculation of tax rates */
		$GLOBALS['xtPrice'] = new xtcPrice($_SESSION['currency'], $_SESSION['customers_status']['customers_status_id']);

		$GLOBALS['order'] = new order();
		$order = $GLOBALS['order'];
		$order_total_modules = new order_total(); // GV Code ICW ADDED FOR CREDIT CLASS SYSTEM

		$GLOBALS['total_weight'] = $_SESSION['cart']->show_weight();
		$GLOBALS['total_count'] = $_SESSION['cart']->count_contents_non_virtual(); // GV Code ICW ADDED FOR CREDIT CLASS SYSTEM

		if ($order->billing['country']['iso_code_2'] != '') {
			$_SESSION['delivery_zone'] = $order->billing['country']['iso_code_2'];
		}

		// mediafinanz
		if (gm_get_conf('MODULE_CENTER_MEDIAFINANZ_INSTALLED') == true) {
			include_once(DIR_FS_CATALOG . 'includes/modules/mediafinanz/include_checkout_payment.php');
		}

		// load all enabled payment modules
		$this->coo_payment = new payment();

		// redirect if Coupon matches ammount
		$order_total_modules->process();

		if (gm_get_conf('GM_CHECK_WITHDRAWAL') == 1) {
			unset($_SESSION['withdrawal']);
		}

		if (gm_get_conf('GM_CHECK_CONDITIONS') == 1) {
			unset($_SESSION['conditions']);
		}

		$dataTransferSettings = explode(',', gm_get_conf('DATA_TRANSFER_TO_TRANSPORT_COMPANIES_SETTINGS'));
		$shippingModuleName = explode('_', $_SESSION['shipping']['id'])[0];

		if (in_array($shippingModuleName, $dataTransferSettings, true)) {
			unset($_SESSION['transport_conditions']);
		}

		$t_error_message = $this->getErrorMessage();

		// check if country of selected shipping address is not allowed
		if ($this->check_country_by_address_book_id($_SESSION['billto']) == false) {
			$t_error_message = ERROR_INVALID_PAYMENT_COUNTRY;
		}

		if ($order->info['total'] > 0 && isset($this->v_data_array['GET']['payment_error']) && is_object($GLOBALS[$this->v_data_array['GET']['payment_error']]) && ($error = $GLOBALS[$this->v_data_array['GET']['payment_error']]->get_error())) {
			$t_error_message = htmlspecialchars_wrapper($error['error']);
		}

		if (isset($_SESSION['gm_error_message']) && xtc_not_null($_SESSION['gm_error_message'])) {
			$t_error_message = htmlspecialchars_wrapper(urldecode($_SESSION['gm_error_message']));
			unset($_SESSION['gm_error_message']);
		}

		# phantom call for creating checkout cache-file
		MainFactory::create_object('GMJanolaw');

		$coo_checkout_payment_view = MainFactory::create_object('CheckoutPaymentContentView');

		$coo_checkout_payment_view->set_('address_book_id', $_SESSION['billto']);
		$coo_checkout_payment_view->set_('customer_id', $_SESSION['customer_id']);
		$coo_checkout_payment_view->set_('customers_status_id', $_SESSION['customers_status']['customers_status_id']);
		$coo_checkout_payment_view->set_('language', $_SESSION['language']);
		$coo_checkout_payment_view->set_('languages_id', $_SESSION['languages_id']);
		$coo_checkout_payment_view->set_('coo_payment', $this->coo_payment);
		$coo_checkout_payment_view->set_('coo_order', $order);
		$coo_checkout_payment_view->set_('coo_order_total', $order_total_modules);
		$coo_checkout_payment_view->set_('error_message', $t_error_message);
		$coo_checkout_payment_view->set_('cart_product_array', $_SESSION['cart']->get_products());

		if (isset($_SESSION['payment'])) {
			$coo_checkout_payment_view->set_('selected_payment_method', $_SESSION['payment']);
		}

		$t_comments = '';
		if (isset($_SESSION['comments'])) {
			$t_comments = $_SESSION['comments'];
		}
		$coo_checkout_payment_view->set_('comments', $t_comments);

		$t_style_edit_active = false;
		if (StyleEditServiceFactory::service()->isEditing()) {
			$t_style_edit_active = true;
		}
		$coo_checkout_payment_view->set_('style_edit_active', $t_style_edit_active);

		$this->v_output_buffer = $coo_checkout_payment_view->get_html();
		unset($_SESSION['abandonment_download']);
		unset($_SESSION['abandonment_service']);

		return true;
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

		$configuration = MainFactory::create('WalleeStorage');
		$settings = new Settings($configuration);
		$transaction = $settings->getApiClient()->getTransactionService()->read($settings->getSpaceId(), $_SESSION['transactionID']);

		return isset($_GET['payment_error']) ? $transaction->getUserFailureMessage() : '';
	}
}