<?php declare(strict_types=1);

defined('GM_HTTP_SERVER') || define('GM_HTTP_SERVER', HTTP_SERVER);

if (file_exists(dirname(__DIR__) . '/../../../GXModules/wallee/WalleePayment/vendor/autoload.php')) {
	require_once dirname(__DIR__) . '/../../../GXModules/wallee/WalleePayment/vendor/autoload.php';
}

defined('GM_HTTP_SERVER') || define('GM_HTTP_SERVER', HTTP_SERVER);

/**
 * Class wallee_ORIGIN
 */
class wallee_ORIGIN {

	/**
	 * @var LanguageTextManager
	 */
	protected $languageTextManager;

	public function __construct() {
		global $order;
		$this->languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
		$this->title = $this->languageTextManager->get_text($this->code . '_title', 'wallee_payment');
		$this->description = $this->languageTextManager->get_text($this->code . '_description', 'wallee_payment');
		$this->sort_order = 'DESC';
		$this->enabled = true;

		if ((int)@constant('configuration/MODULE_PAYMENT_'.strtoupper($this->code).'_ORDER_STATUS_ID') > 0) {
			$this->order_status = @constant('configuration/MODULE_PAYMENT_'.strtoupper($this->code).'_ORDER_STATUS_ID');
		}
		$this->tmpStatus = (int)@constant('configuration/MODULE_PAYMENT_'.strtoupper($this->code).'_TMPORDER_STATUS_ID');

		if(is_object($order)) {
			$this->update_status();
		}
	}

	public function update_status() {

	}

	public function javascript_validation() {
		return false;
	}


	public function selection() {
		$selection = array(
			'id' => $this->code,
			'module' => $this->title,
			'description' => $this->description,
			'fields' => array(),
		);

		return $selection;
	}

	public function pre_confirmation_check() {
		return false;
	}

	public function confirmation() {
		$confirmation = [
			'title' => $this->title,
		];

		return $confirmation;
	}

	public function refresh() {
	}

	public function process_button() {
		return '';
	}

	public function payment_action() {
		$redirectUrl = xtc_href_link('wallee_payment.php', 'payment_error=' . $this->code);

		xtc_redirect($redirectUrl, '');
	}

	public function before_process() {
		return false;
	}

	public function after_process() {

	}

	public function get_error() {
		if(isset($_SESSION['wallee_error'])) {
			$error = array('error' => $_SESSION['wallee_error']);
			unset($_SESSION['wallee_error']);
			return $error;
		}
		return false;
	}

	public function check() {
		if (!isset ($this->_check)) {
			$check_query = xtc_db_query("select `value` from ".TABLE_CONFIGURATION." where `key` = 'configuration/MODULE_PAYMENT_".  strtoupper($this->code) ."_STATUS'");
			$this->_check = xtc_db_num_rows($check_query);
		}
		return $this->_check;
	}

	public function install()
	{
		$config     = $this->_configuration();
		$sort_order = 0;
		foreach ($config as $key => $data) {
			$install_query = "insert into `gx_configurations` (`key`, `value`, `legacy_group_id`, `sort_order`, `type`, `last_modified`) "
				. "values ('configuration/MODULE_PAYMENT_" . strtoupper($this->code) . "_" . $key . "', '"
				. $data['value'] . "', '6', '" . $sort_order . "', '" . addslashes($data['type'] ?? '')
				. "', now())";
			xtc_db_query($install_query);
			$sort_order++;
		}
		$defaultOrderStatus = [
			'TMPORDER_STATUS_ID'   => [
				'names' => ['en' => 'wallee temporary', 'de' => 'wallee temporaer'],
				'color' => '2196F3',
			],
			'ORDER_STATUS_ID'      => [
				'names' => ['en' => 'Paid', 'de' => 'Bezahlt'],
				'color' => '45a845',
			],
			'ERRORORDER_STATUS_ID' => [
				'names' => ['en' => 'Error', 'de' => 'Fehler'],
				'color' => 'e0412c',
			],
			'CANCELORDER_STATUS_ID' => [
				'names' => ['en' => 'Canceled', 'de' => 'Abgesagt'],
				'color' => 'ffa701',
			],
			'VOIDED_STATUS_ID' => [
				'names' => ['en' => 'Voided', 'de' => 'Entwertet'],
				'color' => 'ffa701',
			],
			'AUTHORIZED_STATUS_ID' => [
				'names' => ['en' => 'Authorized', 'de' => 'Autorisiert'],
				'color' => '68BBE3',
			],
			'PROCESSING_STATUS_ID' => [
				'names' => ['en' => 'Processing', 'de' => 'Verarbeitung'],
				'color' => '68BBE3',
			],
			'FULLFILL_STATUS_ID'      => [
				'names' => ['en' => 'Fulfill', 'de' => 'Erfüllen'],
				'color' => '45a845',
			],
			'DERECOGNIZED_STATUS_ID' => [
				'names' => ['en' => 'Derecognized', 'de' => 'Ausgebucht'],
				'color' => 'ffa701',
			],
			'REFUNDED_STATUS_ID' => [
				'names' => ['en' => 'Refunded', 'de' => 'Erstattet'],
				'color' => 'ffa701',
			],
			'PARTIALLY_REFUNDED_STATUS_ID' => [
				'names' => ['en' => 'Partially refunded', 'de' => 'Teilweise erstattet'],
				'color' => 'ffa701',
			],
		];
		foreach ($defaultOrderStatus as $configKey => $orderStatusDefaults) {
			$this->updateConfiguration($configKey,
				$this->getOrdersStatus($orderStatusDefaults['names'],
					$orderStatusDefaults['color']));
		}
	}

	public function _configuration()
	{
		$config = [
			'STATUS'               => [
				'value' => 'True',
				'type'  => 'switcher ',
			],
			'ALLOWED'              => [
				'value' => '',
			],
			'ACCOUNT_ID'           => [
				'value' => '99999',
			],
			'APPLICATION_ID'       => [
				'value' => '99998',
			],
			'APPLICATION_PASSWORD' => [
				'value' => '0',
			],
			'ADMINACTION_PASSWORD' => [
				'value' => '5cfgRT34xsdedtFLdfHxj7tfwx24fe',
			],
			'SECURITY_KEY'         => [
				'value' => 'testtest',
			],
			'AUTH_MODE'            => [
				'value' => 'auth',
				'type'  => 'auth-mode',
			],
			'ZONE'                 => [
				'value' => '0',
				'type'  => 'geo-zone',
			],
			'TMPORDER_STATUS_ID'   => [
				'value' => '',
				'type'  => 'order-status',
			],
			'ORDER_STATUS_ID'      => [
				'value' => '',
				'type'  => 'order-status',
			],
			'ERRORORDER_STATUS_ID' => [
				'value' => '',
				'type'  => 'order-status',
			],
			'CANCELORDER_STATUS_ID' => [
				'value' => '',
				'type'  => 'order-status',
			],
			'AUTHORIZED_STATUS_ID' => [
				'value' => '',
				'type'  => 'order-status',
			],
			'PROCESSING_STATUS_ID' => [
				'value' => '',
				'type'  => 'order-status',
			],
			'FULLFILL_STATUS_ID' => [
				'value' => '',
				'type'  => 'order-status',
			],
			'DERECOGNIZED_STATUS_ID' => [
				'value' => '',
				'type'  => 'order-status',
			],
			'REFUNDED_STATUS_ID' => [
				'value' => '',
				'type'  => 'order-status',
			],
			'PARTIALLY_REFUNDED_STATUS_ID' => [
				'value' => '',
				'type'  => 'order-status',
			],
			'SORT_ORDER'           => [
				'value' => '0',
			],
		];

		return $config;
	}

	protected function updateConfiguration($configurationKey, $configurationValue)
	{
		$db = StaticGXCoreLoader::getDatabaseQueryBuilder();
		$db->where('key', 'configuration/MODULE_PAYMENT_' . strtoupper($this->code) . '_' . $configurationKey);
		$db->update(TABLE_CONFIGURATION, ['value' => $configurationValue]);
	}

	protected function getOrdersStatus($names, $color)
	{
		$orderStatusId      = null;
		$orderStatusService = StaticGXCoreLoader::getService('OrderStatus');
		/** @var \OrderStatusInterface $orderStatus */
		foreach ($orderStatusService->findAll() as $orderStatus) {
			foreach ($names as $languageCode => $statusName) {
				if ($orderStatus->getName(MainFactory::create('LanguageCode', new StringType($languageCode)))
					=== $statusName) {
					$orderStatusId = $orderStatus->getId();
					break 2;
				}
			}
		}
		if ($orderStatusId === null) {
			$newOrderStatus = MainFactory::create('OrderStatus');
			foreach ($names as $languageCode => $statusName) {
				$newOrderStatus->setName(MainFactory::create('LanguageCode', new StringType($languageCode)),
					new StringType($statusName));
			}
			$newOrderStatus->setColor(new StringType($color));
			$orderStatusId = $orderStatusService->create($newOrderStatus);
		}

		return $orderStatusId;
	}

	public function remove() {
		xtc_db_query("delete from ".TABLE_CONFIGURATION." where `key` in ('".implode("', '", $this->keys())."')");
	}

	/**
	 * Determines the module's configuration keys
	 * @return array
	 */
	public function keys() {
		$ckeys = array_keys($this->_configuration());
		$keys = array();
		foreach($ckeys as $k) {
			$keys[] = 'configuration/MODULE_PAYMENT_'.strtoupper($this->code).'_'.$k;
		}
		return $keys;
	}

	public function isInstalled() {
		foreach($this->keys() as $key) {
			if(!defined($key)) {
				return false;
			}
		}
		return true;
	}

}
MainFactory::load_origin_class('wallee');
