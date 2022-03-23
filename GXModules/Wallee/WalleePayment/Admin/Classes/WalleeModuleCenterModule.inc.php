<?php declare(strict_types=1);

if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
	require_once dirname(__DIR__) . '/vendor/autoload.php';
}

use GXModules\WalleePayment\Library\Core\Service\PaymentService;

/**
 * Class WalleeModuleCenterModule
 */
class WalleeModuleCenterModule extends AbstractModuleCenterModule
{
	/**
	 * @var PaymentService $paymentService
	 */
	protected $paymentService;

	protected function _init(): void
	{
		$this->paymentService = new PaymentService();
		$this->name = 'Wallee';
		$this->title = 'Wallee ' . $this->languageTextManager->get_text('payment', 'wallee');
		$this->description = 'Wallee ' . $this->languageTextManager->get_text('description', 'wallee');
		$this->sortOrder = 10000;
	}

	/**
	 * Installs the module
	 */
	public function install(): void
	{
		$databasePath = dirname(__DIR__) . '/Database/';
		$possibleVersions = glob($databasePath . '*.sql');
		foreach ($possibleVersions as $migrationFile) {
			$fileVersion = (int) str_replace([$databasePath, '.sql'], ['', ''], $migrationFile);

			if ($fileVersion < $this->getVersion()) {
				continue;
			}

			try {
				$migrationContent = file_get_contents($migrationFile);
				$queries = explode("\n\n", $migrationContent);

				foreach ($queries as $query) {
					if (empty($query)) {
						continue;
					}

					xtc_db_query($query);
				}
			} catch (\Exception $e) {

			}
		}

		$this->increaseVersion();

		parent::install();
	}

	/**
	 * Uninstalls the module
	 */
	public function uninstall()
	{
		parent::uninstall();
	}

	/**
	 * @return int
	 */
	private function getVersion(): int
	{
		$configuration = $this->paymentService->getConfiguration();
		return (int) $configuration->get('version') ?? 1;
	}

	/**
	 * @void
	 */
	private function increaseVersion(): void
	{
		$configuration = $this->paymentService->getConfiguration();
		$configuration->set('version', $this->getVersion() + 1);
	}
}
