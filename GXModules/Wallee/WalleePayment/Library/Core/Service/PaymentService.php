<?php declare(strict_types=1);

namespace GXModules\WalleePayment\Library\Core\Service;

use ExistingDirectory;
use Gambio\Core\Cache\CacheFactory;
use GXModules\WalleePayment\Library\{Core\Settings\Struct\Settings, Helper\WalleeHelper};
use LanguageTextManager;
use LegacyDependencyContainer;
use MainFactory;
use RequiredDirectory;
use StaticGXCoreLoader;
use ThemeDirectoryRoot;
use ThemeId;
use ThemeService;
use ThemeSettings;
use Wallee\Sdk\{Model\CreationEntityState,
	Model\CriteriaOperator,
	Model\EntityQuery,
	Model\EntityQueryFilter,
	Model\EntityQueryFilterType,
	Model\PaymentMethodConfiguration};
use WalleeStorage;

/**
 * Class WebHooksService
 *
 * @package WalleePayment\Core\Api\WebHooks\Service
 */
class PaymentService
{
	/**
	 * @var string $rootDir
	 */
	protected $rootDir;

	/**
	 * @var Settings $settings
	 */
	public $settings;

	/**
	 * @var WalleeStorage $configuration
	 */
	public $configuration;

	/**
	 * @var LanguageTextManager $languageTextManager
	 */
	public $languageTextManager;

	/**
	 * @var array
	 */
	private $localeLanguageMapping = [
		'de-DE' => 'german',
		'fr-FR' => 'french',
		'it-IT' => 'italian',
		'en-US' => 'english',
	];

	/**
	 * PaymentService constructor.
	 * @param WalleeStorage|null $configuration
	 */
	public function __construct(?WalleeStorage $configuration = null)
	{
		$this->rootDir = __DIR__ . '/../../../../../../';
		$this->configuration = $configuration;
		$this->settings = new Settings($this->configuration);
		$this->languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
	}

	public function syncPaymentMethods()
	{
		$paymentMethods = $this->getPaymentMethodConfigurations();

		$translations = [];

		$data = [];
		/**
		 * PaymentMethodConfiguration $paymentMethod
		 */
		foreach ($paymentMethods as $paymentMethod) {
			$name = 'Wallee ' . $paymentMethod->getName();
			$slug = trim(strtolower(WalleeHelper::slugify($name)));

			$descriptions = [];
			$languageMapping = $this->localeLanguageMapping;
			foreach ($paymentMethod->getResolvedDescription() as $locale => $text) {
				$language = $languageMapping[$locale];
				$descriptions[$language] = $translations[$language][$slug . '_description'] = addslashes($text);
			}

			$titles = [];
			foreach ($paymentMethod->getResolvedTitle() as $locale => $text) {
				$language = $languageMapping[$locale];
				$titles[$language] = $translations[$language][$slug . '_title'] = addslashes(str_replace('-/', ' / ', $text));
			}

			$data[] = [
				'logo_url' => $paymentMethod->getResolvedImageUrl(),
				'logo_alt' => $slug,
				'id' => $slug,
				'module' => $translations['english'][$slug . '_title'],
				'description' => $translations['english'][$slug . '_description'],
				'fields' => [],
				'titles' => $titles,
				'descriptions' => $descriptions
			];

			// We can allow this manually for the future
			//$this->downloadPaymentMethodLogo($paymentMethod->getResolvedImageUrl(), $slug);
		}

		$this->configuration->set('payment_methods', \json_encode($data));

		$this->updateLanguageFiles($translations);

		$this->clearCache();
	}

	/**
	 * @return mixed|WalleeStorage
	 */
	public function getConfiguration()
	{
		return $this->configuration ?? MainFactory::create('WalleeStorage');
	}

	private function clearCache()
	{
		$coo_cache_control = MainFactory::create_object('CacheControl');
		$coo_cache_control->clear_cache();
		$_GET['manual_categories_index'] = '1';

		$themeId              = StaticGXCoreLoader::getThemeControl()->getCurrentTheme();
		$themeSourcePath      = DIR_FS_CATALOG . StaticGXCoreLoader::getThemeControl()->getThemesPath();
		$themeDestinationPath = DIR_FS_CATALOG . StaticGXCoreLoader::getThemeControl()->getThemePath();

		$destination   = ThemeDirectoryRoot::create(new RequiredDirectory($themeDestinationPath));
		$themeSettings = ThemeSettings::create(ThemeDirectoryRoot::create(new ExistingDirectory($themeSourcePath)),
			$destination);

		/** @var ThemeService $themeService */
		$themeService = StaticGXCoreLoader::getService('Theme');
		$themeService->buildTemporaryTheme(ThemeId::create($themeId), $themeSettings);

		$coo_cache_control->clear_content_view_cache();
		$coo_cache_control->clear_templates_c();
		$coo_cache_control->clear_template_cache();
		$coo_cache_control->clear_google_font_cache();
		$coo_cache_control->clear_css_cache();
		$coo_cache_control->clear_expired_shared_shopping_carts();
		$coo_cache_control->remove_reset_token();

		$coo_cache_control->clear_data_cache();
		$coo_cache_control->clear_menu_cache();

		$coo_cache_control->rebuild_feature_index();

		$coo_cache_control->rebuild_products_categories_index();

		$coo_cache_control->rebuild_products_properties_index();

		$coo_phrase_cache_builder = MainFactory::create_object('PhraseCacheBuilder', array());
		$coo_phrase_cache_builder->build();

		/** @var CacheFactory $cacheFactory */
		$cacheFactory = LegacyDependencyContainer::getInstance()->get(CacheFactory::class);
		$cacheFactory->createCacheFor('text_cache')->clear();

		$coo_cache_control->clear_data_cache();


		$mailTemplatesCacheBuilder = MainFactory::create_object('MailTemplatesCacheBuilder');
		$mailTemplatesCacheBuilder->build();
	}

	/**
	 * Fetch active merchant payment methods from Wallee API
	 *
	 * @return \Wallee\Sdk\Model\PaymentMethodConfiguration[]
	 * @throws \Wallee\Sdk\ApiException
	 * @throws \Wallee\Sdk\Http\ConnectionException
	 * @throws \Wallee\Sdk\VersioningException
	 */
	private function getPaymentMethodConfigurations(): array
	{
		$entityQueryFilter = (new EntityQueryFilter())
			->setOperator(CriteriaOperator::EQUALS)
			->setFieldName('state')
			->setType(EntityQueryFilterType::LEAF)
			->setValue(CreationEntityState::ACTIVE);

		$entityQuery = (new EntityQuery())->setFilter($entityQueryFilter);

		$settings = new Settings($this->configuration);
		$apiClient = $settings->getApiClient();
		$spaceId = $settings->getSpaceId();

		if (empty($spaceId)) {
			$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('no_payment_methods_were_imported_please_check_space_id_setting', 'wallee'), 'error');
			return [];
		}

		$paymentMethodConfigurations = $apiClient->getPaymentMethodConfigurationService()->search($spaceId, $entityQuery);

		usort($paymentMethodConfigurations, function (PaymentMethodConfiguration $item1, PaymentMethodConfiguration $item2) {
			return $item1->getSortOrder() <=> $item2->getSortOrder();
		});

		return $paymentMethodConfigurations;
	}

	private function updateLanguageFiles(array $translations)
	{
		$path = $this->rootDir . 'GXModules/Wallee/WalleePayment/Admin/';
		foreach ($translations as $language => $lines) {
			if (!is_dir($path . '/TextPhrases/' . $language)) {
				continue;
			}

			$fileName = $path . '/TextPhrases/' . $language . '/wallee_payment.lang.inc.php';
			$content = '';

			foreach ($lines as $key => $text) {
				$content .= "'" . $key . "' => " . "'" . $text . "',\n";
			}

			$fp = fopen($fileName, 'w+');
			fwrite($fp, '<?php declare(strict_types = 1);' . "\n");
			fwrite($fp, '$t_language_text_section_content_array = [' . "\n");
			fwrite($fp, $content);
			fwrite($fp, "];\n");
			fwrite($fp, "\n");
			fclose($fp);
		}
	}

	/**
	 * @param string $url
	 * @param $slug
	 */
	private function downloadPaymentMethodLogo(string $url, $slug): void
	{
		try {
			$ch = curl_init($url);
			$fp = fopen($this->rootDir . '/images/icons/payment/' . $slug . '.svg', 'wb');
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_exec($ch);
			curl_close($ch);
			fclose($fp);
		} catch (\Exception $e) {
			$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_downloading_logo_please_check_permissions', 'wallee'), 'error');
		}
	}
}