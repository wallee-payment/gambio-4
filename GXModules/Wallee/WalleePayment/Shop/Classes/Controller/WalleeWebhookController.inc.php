<?php declare(strict_types=1);

use GXModules\WalleePayment\Library\Core\{
	Api\WebHooks\Service\WebhooksService,
	Api\WebHooks\Struct\WebHookRequest,
	Settings\Struct\Settings
};

use Wallee\Sdk\Model\{
	TransactionState,
	Transaction,
	TransactionInvoice,
	TransactionInvoiceState
};

use GXModules\Wallee\WalleePayment\Shop\Classes\Model\WalleeTransactionModel;
use GXModules\Wallee\WalleePayment\Shop\Classes\Model\WalleeRefundModel;

class WalleeWebhookController extends HttpViewController
{
	/**
	 * @var WebhooksService $webHooksService
	 */
	protected $webHooksService;

	/**
	 * @var Settings $settings
	 */
	public $settings;

	/**
	 * @param HttpContextReaderInterface $httpContextReader
	 * @param HttpResponseProcessorInterface $httpResponseProcessor
	 * @param ContentViewInterface $defaultContentView
	 */
	public function __construct(HttpContextReaderInterface $httpContextReader, HttpResponseProcessorInterface $httpResponseProcessor, ContentViewInterface $defaultContentView)
	{
		$this->configuration = MainFactory::create('WalleeStorage');
		$this->webHooksService = new WebHooksService($this->configuration);
		$this->settings = new Settings();

		parent::__construct($httpContextReader, $httpResponseProcessor, $defaultContentView);
	}

	public function actionIndex()
	{
		$data = $this->_getParsedBody();
		$listenerEntityTechnicalName = $data['listenerEntityTechnicalName'] ?? null;

		switch ($listenerEntityTechnicalName) {
			case WebHookRequest::TRANSACTION:
				$transaction = $this->settings->getApiClient()
					->getTransactionService()
					->read($this->settings->getSpaceId(), $data['entityId']);

				$orderId = (int)$transaction->getMetaData()['orderId'];

				if (empty($orderId)) {
					throw new Exception('Transaction not updated. Empty order ID');
				}

				$this->updateTransaction($transaction, $orderId);

				return new JsonHttpControllerResponse(['Transaction updated']);

			case WebHookRequest::TRANSACTION_INVOICE:
				$transactionInvoice = $this->settings->getApiClient()
					->getTransactionInvoiceService()
					->read($this->settings->getSpaceId(), $data['entityId']);

				$orderId = (int)$transactionInvoice->getCompletion()
					->getLineItemVersion()
					->getTransaction()
					->getMetaData()['orderId'];

				if (empty($orderId)) {
					throw new Exception('Transaction invoice not updated. Empty order ID');
				}

				$this->updateTransactionInvoice($transactionInvoice, $orderId);

				return new JsonHttpControllerResponse(['Transaction invoice updated']);

			case WebHookRequest::REFUND:
				/**
				 * @var \Wallee\Sdk\Model\Refund $refund
				 */
				$refund = $this->settings->getApiClient()->getRefundService()
					->read($this->settings->getSpaceId(), $data['entityId']);

				$orderId = (int)$refund->getTransaction()->getMetaData()['orderId'];

				$query = xtc_db_query("SELECT * FROM `wallee_transactions` WHERE order_id = " . xtc_db_input($orderId));
				$transactionData = xtc_db_fetch_array($query);
				$transactionInfo = json_decode($transactionData['data'], true);

				WalleeRefundModel::createRefundRecord((int)$data['entityId'], $orderId, $refund->getAmount());
				$refunds = WalleeRefundModel::getRefunds($orderId);
				$amountToBeRefunded = floatval($transactionInfo['info']['total']) - WalleeRefundModel::getTotalRefundsAmount($refunds);

				if ($amountToBeRefunded > 0) {
					$this->updateOrderStatus('PARTIALLY REFUNDED', $orderId);
				} else {
					$this->updateOrderStatus('REFUNDED', $orderId);
				}

				return new JsonHttpControllerResponse(['Refund updated']);

			case WebHookRequest::PAYMENT_METHOD_CONFIGURATION:
				$result = $this->webHooksService->synchronize();

				return new JsonHttpControllerResponse(['result' => $result]);
		}

		throw new Exception('Unknown request');
	}

	/**
	 * @param Transaction $transaction
	 * @param int $orderId
	 * @throws Exception
	 */
	private function updateTransaction(Transaction $transaction, int $orderId): void
	{
		switch ($transaction->getState()) {
			case TransactionState::FULFILL:
				$this->updateOrderAndTransactionStatus(TransactionState::FULFILL, $orderId);
				break;

			case TransactionState::DECLINE:
			case TransactionState::VOIDED:
				$this->updateOrderAndTransactionStatus(TransactionState::VOIDED, $orderId);
				break;

			case TransactionState::FAILED:
				$this->updateOrderAndTransactionStatus(TransactionState::FAILED, $orderId);
				break;

			case TransactionState::AUTHORIZED:
				$this->updateOrderAndTransactionStatus(TransactionState::AUTHORIZED, $orderId);

				if ($this->settings->isConfirmationEmailSendEnabled()) {
					$this->sendOrderConfirmationEmail($orderId);
				}

				break;
		}
	}

	/**
	 * @param int $orderId
	 */
	private function sendOrderConfirmationEmail(int $orderId): void
	{
		$order = new order($orderId);
		$t_mail_attachment_array = [];
		$t_payment_info_html = '';
		$t_payment_info_text = '';
		$t_mail_logo = '';
		$t_logo_mail = MainFactory::create_object('GMLogoManager', array("gm_logo_mail"));
		if ($t_logo_mail->logo_use == '1') {
			$t_mail_logo = $t_logo_mail->get_logo();
		}
		$additionalOrderData = xtc_db_query("SELECT `language` FROM orders WHERE orders_id='" . xtc_db_input($orderId) . "'");
		$orderLanguage = xtc_db_fetch_array($additionalOrderData);

		$additionalLanguageData = xtc_db_query("SELECT `languages_id`, `code`  FROM languages WHERE LOWER(name)='" . strtolower(addslashes($orderLanguage['language'])) . "'");
		$languageData = xtc_db_fetch_array($additionalLanguageData);

		$coo_send_order_content_view = MainFactory::create_object('SendOrderContentView');
		$coo_send_order_content_view->set_('order', $order);
		$coo_send_order_content_view->set_('order_id', $orderId);
		$coo_send_order_content_view->set_('language', $orderLanguage['language']);
		$coo_send_order_content_view->set_('language_id', $languageData['languages_id']);
		$coo_send_order_content_view->set_('language_code', $languageData['code']);
		$coo_send_order_content_view->set_('payment_info_html', $t_payment_info_html);
		$coo_send_order_content_view->set_('payment_info_text', $t_payment_info_text);
		$coo_send_order_content_view->set_('mail_logo', $t_mail_logo);

		$t_mail_content_array = $coo_send_order_content_view->get_mail_content_array();
		$t_content_mail = $t_mail_content_array['html'];
		$t_txt_mail = $t_mail_content_array['txt'];

		// CREATE SUBJECT
		if (extension_loaded('intl')) {
			$order_date = utf8_encode_wrapper(DateFormatter::formatAsFullDate(new DateTime(), new LanguageCode(new StringType($languageData['code']))));
		} else {
			$order_date = utf8_encode_wrapper(strftime(DATE_FORMAT_LONG));
		}

		$t_subject = gm_get_content('EMAIL_BILLING_SUBJECT_ORDER', $languageData['languages_id']);
		if (empty($t_subject)) {
			$t_subject = EMAIL_BILLING_SUBJECT_ORDER;
		}

		$order_subject = str_replace('{$nr}', (string)$orderId, $t_subject);
		$order_subject = str_replace('{$date}', $order_date, $order_subject);
		$order_subject = str_replace('{$lastname}', $order->customer['lastname'], $order_subject);
		$order_subject = str_replace('{$firstname}', $order->customer['firstname'], $order_subject);

		xtc_php_mail(EMAIL_BILLING_ADDRESS,
			EMAIL_BILLING_NAME,
			$order->customer['email_address'],
			$order->customer['firstname'] . ' ' . $order->customer['lastname'],
			'',
			EMAIL_BILLING_REPLY_ADDRESS,
			EMAIL_BILLING_REPLY_ADDRESS_NAME,
			$t_mail_attachment_array,
			'',
			$order_subject,
			$t_content_mail,
			$t_txt_mail
		);
	}

	/**
	 * @param TransactionInvoice $transactionInvoice
	 * @param int $orderId
	 * @throws Exception
	 */
	private function updateTransactionInvoice(TransactionInvoice $transactionInvoice, int $orderId): void
	{
		switch ($transactionInvoice->getState()) {
			case TransactionInvoiceState::DERECOGNIZED:
				$this->updateOrderAndTransactionStatus(TransactionInvoiceState::DERECOGNIZED, $orderId);
				break;

			case TransactionInvoiceState::NOT_APPLICABLE:
			case TransactionInvoiceState::PAID:
				$this->updateOrderAndTransactionStatus(TransactionInvoiceState::PAID, $orderId);
				break;
		}
	}

	/**
	 * @param string $newStatus
	 * @param int $orderId
	 * @throws Exception
	 */
	private function updateOrderAndTransactionStatus(string $newStatus, int $orderId): void
	{
		$this->updateOrderStatus($newStatus, $orderId);

		$transactionModel = new WalleeTransactionModel();
		$transactionModel->updateTransactionStatus($newStatus, $orderId);
	}

	/**
	 * @param string $newStatus
	 * @param int $orderId
	 * @throws Exception
	 */
	private function updateOrderStatus(string $newStatus, int $orderId): void
	{
		/** @var OrderWriteServiceInterface $orderWriteService */
		$orderWriteService = StaticGXCoreLoader::getService('OrderWrite');
		$orderStatusId = $this->getOrderStatusId($newStatus);
		$orderWriteService->updateOrderStatus(
			new IdType($orderId),
			new IntType($orderStatusId),
			new StringType(''),
			new BoolType(false)
		);
	}

	/**
	 * @param string $currentOrderStatusName
	 * @return int
	 * @throws Exception
	 */
	private function getOrderStatusId(string $currentOrderStatusName): int
	{
		$orderStatusId = 0;
		$orderStatusService = StaticGXCoreLoader::getService('OrderStatus');
		/** @var \OrderStatusInterface $orderStatus */
		foreach ($orderStatusService->findAll() as $orderStatus) {
			$orderStatusName = $orderStatus->getName(MainFactory::create('LanguageCode', new StringType('en')));
			if (strtolower($orderStatusName) === strtolower($currentOrderStatusName)) {
				$orderStatusId = $orderStatus->getId();
				break;
			}
		}

		return $orderStatusId;
	}

	/**
	 * Returns the parsed body contents of the request (JSON decoded array).
	 *
	 * @return array
	 */
	private function _getParsedBody(): array
	{
		return json_decode(file_get_contents('php://input'), true);
	}

}
