<?php declare(strict_types=1);

namespace GXModules\WalleePayment\Library\Core\Api\WebHooks\Service;

use GXModules\WalleePayment\Library\Core\{Service\PaymentService, Settings\Struct\Settings};
use Wallee\Sdk\{Model\CreationEntityState,
	Model\CriteriaOperator,
	Model\EntityQuery,
	Model\EntityQueryFilter,
	Model\EntityQueryFilterType,
	Model\RefundState,
	Model\TransactionInvoiceState,
	Model\TransactionState,
	Model\WebhookListener,
	Model\WebhookListenerCreate,
	Model\WebhookListenerUpdate,
	Model\WebhookUrl,
	Model\WebhookUrlCreate};
use WalleeStorage;

/**
 * Class WebHooksService
 *
 * @package WalleePayment\Core\Api\WebHooks\Service
 */
class WebHooksService
{
	/**
	 * WebHook configs
	 */
	protected $webHookEntitiesConfig = [];

	/**
	 * WebHook configs
	 */
	protected $webHookEntityArrayConfig = [
		/**
		 * Transaction WebHook Entity Id
		 *
		 * @link https://www.wallee.com/doc/api/webhook-entity/view/1472041829003
		 */
		[
			'id' => '1472041829003',
			'name' => 'Gambio4::WebHook::Transaction',
			'states' => [
				TransactionState::AUTHORIZED,
				TransactionState::COMPLETED,
				TransactionState::CONFIRMED,
				TransactionState::DECLINE,
				TransactionState::FAILED,
				TransactionState::FULFILL,
				TransactionState::PROCESSING,
				TransactionState::VOIDED,
			],
			'notifyEveryChange' => false,
		],
		/**
		 * Transaction Invoice WebHook Entity Id
		 *
		 * @link https://www.wallee.com/doc/api/webhook-entity/view/1472041816898
		 */
		[
			'id' => '1472041816898',
			'name' => 'Gambio4::WebHook::Transaction Invoice',
			'states' => [
				TransactionInvoiceState::NOT_APPLICABLE,
				TransactionInvoiceState::PAID,
				TransactionInvoiceState::DERECOGNIZED,
			],
			'notifyEveryChange' => false,
		],
		/**
		 * Refund WebHook Entity Id
		 *
		 * @link https://www.wallee.com/doc/api/webhook-entity/view/1472041839405
		 */
		[
			'id' => '1472041839405',
			'name' => 'Gambio4::WebHook::Refund',
			'states' => [
				RefundState::FAILED,
				RefundState::SUCCESSFUL,
			],
			'notifyEveryChange' => false,
		],
		/**
		 * Payment Method Configuration Id
		 *
		 * @link https://www.wallee.com/doc/api/webhook-entity/view/1472041857405
		 */
		[
			'id' => '1472041857405',
			'name' => 'Gambio4::WebHook::Payment Method Configuration',
			'states' => [
				CreationEntityState::ACTIVE,
				CreationEntityState::DELETED,
				CreationEntityState::DELETING,
				CreationEntityState::INACTIVE
			],
			'notifyEveryChange' => true,
		],

	];

	/**
	 * @var Settings $settings
	 */
	public $settings;

	/**
	 * @var WalleeStorage $configuration
	 */
	public $configuration;

	/**
	 * @param $configuration
	 */
	public function __construct($configuration)
	{
		$this->configuration = $configuration;
		$this->settings = new Settings($configuration);

		$this->setWebHookEntitiesConfig();
	}

	/**
	 * Set webhook configs
	 */
	protected function setWebHookEntitiesConfig(): void
	{
		foreach ($this->webHookEntityArrayConfig as $item) {
			$this->webHookEntitiesConfig[] = [
				"id" => $item['id'],
				"name" => $item['name'],
				"states" => $item['states'],
				"notifyEveryChange" => $item['notifyEveryChange']
			];
		}
	}

	/**
	 * Install WebHooks
	 *
	 * @return array
	 * @throws \Wallee\Sdk\ApiException
	 * @throws \Wallee\Sdk\Http\ConnectionException
	 * @throws \Wallee\Sdk\VersioningException
	 */
	public function install(): array
	{
		$listeners = $this->installListeners();
		// If webhook creation from the admin backend is triggered, but webhooks are already pre-created in the portal, make sure to update the webhooks to use the signature.
		$this->updateListenersToEnableSignatureAndState();
		$this->configuration->set('enforce_webhook_signature', 1);
		return $listeners;
	}

	/**
	 * Update WebHooks to use signature
	 *
	 * @throws \Wallee\Sdk\ApiException
	 * @throws \Wallee\Sdk\Http\ConnectionException
	 * @throws \Wallee\Sdk\VersioningException
	 */
	public function update(): void
	{
		$this->updateListenersToEnableSignatureAndState();
		$this->configuration->set('enforce_webhook_signature', 1);
	}

	/**
	 * Install Listeners
	 *
	 * @return array
	 */
	protected function installListeners(): array
	{
		$returnValue = [];
		try {
			$webHookUrlId = $this->getOrCreateWebHookUrl()->getId();
			$installedWebHooks = $this->getInstalledWebHookListeners($webHookUrlId);
			$webHookEntityIds = array_map(function (WebhookListener $webHook) {
				return $webHook->getEntity();
			}, $installedWebHooks);

			foreach ($this->webHookEntitiesConfig as $data) {

				if (in_array($data['id'], $webHookEntityIds)) {
					continue;
				}

				$entity = (new WebhookListenerCreate())
					->setName($data['name'])
					->setEntity($data['id'])
					->setNotifyEveryChange($data['notifyEveryChange'])
					->setState(CreationEntityState::CREATE)
					->setEntityStates($data['states'])
					->setUrl($webHookUrlId)
					->setEnablePayloadSignatureAndState(true);

				$returnValue[] = $this->settings->getApiClient()->getWebhookListenerService()->create($this->settings->getSpaceId(), $entity);
			}
		} catch (\Exception $exception) {
			throw $exception;
		}

		return $returnValue;
	}

	/**
	 * Update Listeners to enable signature use
	 *
	 * @return array
	 */
	protected function updateListenersToEnableSignatureAndState(): void
	{
		$returnValue = [];
		$webHookUrlId = $this->getOrCreateWebHookUrl()->getId();
		$installedListeners = $this->getInstalledWebHookListeners($webHookUrlId);
		foreach ($installedListeners as $listener) {
			if (!$listener->getEnablePayloadSignatureAndState()) {
				$updatedListener = (new WebhookListenerUpdate())
					->setId($listener->getId())
					->setVersion($listener->getVersion())
					->setEnablePayloadSignatureAndState(true);

				$returnValue[] = $this->settings->getApiClient()->getWebhookListenerService()->update($this->settings->getSpaceId(), $updatedListener);
			}
		}
	}

	/**
	 * Create WebHook URL
	 *
	 * @return WebhookUrl
	 * @throws \Wallee\Sdk\ApiException
	 * @throws \Wallee\Sdk\Http\ConnectionException
	 * @throws \Wallee\Sdk\VersioningException
	 */
	protected function getOrCreateWebHookUrl(): WebhookUrl
	{
		$url = $this->getWebHookCallBackUrl();
		/** @noinspection PhpParamsInspection */
		$entityQueryFilter = (new EntityQueryFilter())
			->setType(EntityQueryFilterType::_AND)
			->setChildren([
				$this->getEntityFilter('state', CreationEntityState::ACTIVE),
				$this->getEntityFilter('url', $url),
			]);

		$query = (new EntityQuery())->setFilter($entityQueryFilter)->setNumberOfEntities(1);

		$webHookUrls = $this->settings->getApiClient()->getWebhookUrlService()->search($this->settings->getSpaceId(), $query);

		if (!empty($webHookUrls[0])) {
			return $webHookUrls[0];
		}

		/** @noinspection PhpParamsInspection */
		$entity = (new WebhookUrlCreate())
			->setName('Gambio4::WebHookURL')
			->setUrl($url)
			->setState(CreationEntityState::ACTIVE);

		return $this->settings->getApiClient()->getWebhookUrlService()->create($this->settings->getSpaceId(), $entity);
	}

	/**
	 * Creates and returns a new entity filter.
	 *
	 * @param string $fieldName
	 * @param        $value
	 * @param string $operator
	 *
	 * @return \Wallee\Sdk\Model\EntityQueryFilter
	 */
	protected function getEntityFilter(string $fieldName, $value, string $operator = CriteriaOperator::EQUALS): EntityQueryFilter
	{
		/** @noinspection PhpParamsInspection */
		return (new EntityQueryFilter())
			->setType(EntityQueryFilterType::LEAF)
			->setOperator($operator)
			->setFieldName($fieldName)
			->setValue($value);
	}

	/**
	 * Get web hook callback url
	 *
	 * @return string
	 */
	protected function getWebHookCallBackUrl(): string
	{
		if (\function_exists('xtc_catalog_href_link')) {
			return \xtc_catalog_href_link("shop.php", 'do=WalleeWebhook/Index');
		}
		// Fallback, as xtc_catalog_href_link() is not loaded in transaction creation context
		if (\defined('HTTP_SERVER')
			&& \defined('HTTPS_CATALOG_SERVER')
			&& \defined('DIR_WS_CATALOG')
			&& \defined('ENABLE_SSL_CATALOG')) {
			return \get_href_link(HTTP_SERVER, HTTPS_CATALOG_SERVER, DIR_WS_CATALOG,
				ENABLE_SSL_CATALOG === 'true' || ENABLE_SSL_CATALOG === true, "shop.php", 'do=WalleeWebhook/Index');
		}

		$url = 'https://' . $_SERVER['HTTP_HOST'];
		if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
			$url = 'http://' . $_SERVER['HTTP_HOST'];
		}
		return $url . '/shop.php?do=WalleeWebhook/Index';
	}

	/**
	 * @param int $webHookUrlId
	 *
	 * @return array
	 * @throws \Wallee\Sdk\ApiException
	 * @throws \Wallee\Sdk\Http\ConnectionException
	 * @throws \Wallee\Sdk\VersioningException
	 */
	protected function getInstalledWebHookListeners(int $webHookUrlId): array
	{
		/** @noinspection PhpParamsInspection */
		$entityQueryFilter = (new EntityQueryFilter())
			->setType(EntityQueryFilterType::_AND)
			->setChildren([
				$this->getEntityFilter('state', CreationEntityState::ACTIVE),
				$this->getEntityFilter('url.id', $webHookUrlId),
			]);

		$query = (new EntityQuery())->setFilter($entityQueryFilter);

		return $this->settings->getApiClient()->getWebhookListenerService()->search($this->settings->getSpaceId(), $query);
	}

	/**
	 * @void
	 */
	public function synchronize(): void
	{
		$paymentService = new PaymentService($this->configuration);
		$paymentService->syncPaymentMethods();
	}

}