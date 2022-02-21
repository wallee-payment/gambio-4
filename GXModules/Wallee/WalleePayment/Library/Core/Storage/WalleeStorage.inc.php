<?php declare(strict_types = 1);

/**
 * Class WalleeStorage
 */
class WalleeStorage extends ConfigurationStorage
{
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
			'active' => 1,
			'space_id' => '',
			'user_id' => '',
			'application_key' => '',
			'space_view_id' => 0,
			'integration' => 'iframe',
			'line_item_consistency' => 1,
			'send_order_confirmation_email' => 1,
			'invoice_download' => 1,
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
			case 'active';
			case 'line_item_consistency';
			case 'send_order_confirmation_email';
			case 'invoice_download';
				$value = (bool)$p_value ? '1' : '0';
				break;
			case 'space_id':
			case 'user_id':
			case 'space_view_id':
				$value = (string)(int)$p_value;
				break;
			case 'application_key':
			case 'integration':
				$value = strip_tags($p_value);
				break;
			default:
				$value = null;
		}
		$rc = parent::set($p_key, $value);

		return $rc;
	}
}
