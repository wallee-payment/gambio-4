<?php declare(strict_types = 1);

/**
 * Class WalleeStorage
 */
class WalleeStorage extends ConfigurationStorage
{

	const CONFIG_VERSION = 'version';
	const CONFIG_ACTIVE = 'active';
	const CONFIG_SPACE_ID = 'space_id';
	const CONFIG_USER_ID = 'user_id';
	const CONFIG_APPLICATION_KEY = 'application_key';
	const CONFIG_SPACE_VIEW_ID = 'space_view_id';
	const CONFIG_INTEGRATION = 'integration';
	const CONFIG_LINE_ITEM_CONSISTENCY = 'line_item_consistency';
	const CONFIG_SEND_ORDER_CONFIRMATION = 'send_order_confirmation_email';
	const CONFIG_INVOICE_DOWNLOAD = 'invoice_download';
	const CONFIG_PAYMENT_METHODS = 'payment_methods';

	/**
	 * namespace inside the configuration storage
	 */
	const CONFIG_STORAGE_NAMESPACE = 'modules/Wallee/WalleePayment';

	/**
	 * array holding default values to be used in absence of configured values
	 */
	protected $default_configuration;

	/**
	 * constructor; initializes default configuration
	 */
	public function __construct()
	{
		parent::__construct(self::CONFIG_STORAGE_NAMESPACE);
		$this->setDefaultConfiguration();
	}

	/**
	 * fills $default_configuration with initial values
	 */
	protected function setDefaultConfiguration()
	{
		$this->default_configuration = [
			self::CONFIG_VERSION => 0,
			self::CONFIG_ACTIVE => 1,
			self::CONFIG_SPACE_ID => '',
			self::CONFIG_USER_ID => '',
			self::CONFIG_APPLICATION_KEY => '',
			self::CONFIG_SPACE_VIEW_ID => 0,
			self::CONFIG_INTEGRATION => 'iframe',
			self::CONFIG_LINE_ITEM_CONSISTENCY => 1,
			self::CONFIG_SEND_ORDER_CONFIRMATION => 1,
			self::CONFIG_INVOICE_DOWNLOAD => 1,
		];
	}

	/**
	 * returns a single configuration value by its key
	 *
	 * @param string $key a configuration key (relative to the namespace prefix)
	 *
	 * @return string configuration value
	 */
	public function get($key)
	{
		$value = parent::get($key);
		if ($value === false && array_key_exists($key, $this->default_configuration)) {
			$value = $this->default_configuration[$key];
		}

		return $value;
	}

	/**
	 * Retrieves all keys/values from a given prefix namespace
	 *
	 * @param string $p_prefix
	 *
	 * @return array
	 */
	public function get_all($p_prefix = '')
	{
		$values = parent::get_all($p_prefix);
		foreach ($this->default_configuration as $key => $default_value) {
			$key_prefix = substr($key, 0, strlen($p_prefix));
			if (!array_key_exists($key, $values) && $key_prefix === $p_prefix) {
				$values[$key] = $default_value;
			}
		}

		return $values;
	}

	public function set($p_key, $p_value)
	{
		switch ($p_key) {
			case self::CONFIG_ACTIVE;
			case self::CONFIG_LINE_ITEM_CONSISTENCY;
			case self::CONFIG_SEND_ORDER_CONFIRMATION;
			case self::CONFIG_INVOICE_DOWNLOAD;
				$value = (bool)$p_value ? '1' : '0';
				break;
			case self::CONFIG_VERSION:
			case self::CONFIG_SPACE_ID:
			case self::CONFIG_USER_ID:
			case self::CONFIG_SPACE_VIEW_ID:
				$value = (string)(int)$p_value;
				break;
			case self::CONFIG_APPLICATION_KEY:
			case self::CONFIG_INTEGRATION:
			case self::CONFIG_PAYMENT_METHODS:
				$value = strip_tags($p_value);
				break;
			default:
				$value = null;
		}
		$rc = parent::set($p_key, $value);

		return $rc;
	}
}
