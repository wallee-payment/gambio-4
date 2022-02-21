<?php declare(strict_types=1);

use GXModules\WalleePayment\Library\Core\Settings\Struct\Settings;

use Wallee\Sdk\{
	Model\LineItem,
	Model\RefundCreate,
	Model\RefundType,
	Model\Transaction,
	Model\TransactionState
};
use WalleePayment\Core\Util\Exception\InvalidPayloadException;

class WalleeOrderActionController extends AdminHttpViewController
{
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
		$this->settings = new Settings(MainFactory::create('WalleeStorage'));

		parent::__construct($httpContextReader, $httpResponseProcessor, $defaultContentView);
	}

	/**
	 * @return HttpControllerResponse
	 * @throws \Wallee\Sdk\ApiException
	 * @throws \Wallee\Sdk\Http\ConnectionException
	 * @throws \Wallee\Sdk\VersioningException
	 */
	public function actionChangeTransactionStatus(): HttpControllerResponse
	{
		$orderId = $this->_getPostData('orderId');
		$action = $this->_getPostData('action');
		$query = xtc_db_query("SELECT * FROM `wallee_transaction` WHERE order_id = " . xtc_db_input($orderId));
		$transactionData = xtc_db_fetch_array($query);

		$transactionStateAuthorized = TransactionState::AUTHORIZED;
		if (strtolower($transactionData['state']) === strtolower($transactionStateAuthorized)) {
			$transactionID = (int)$transactionData['transaction_id'];
			switch ($action) {
				case 'Complete':
					$this->settings->getApiClient()->getTransactionCompletionService()->completeOnline($this->settings->getSpaceId(), $transactionID);
					return new HttpControllerResponse('');

				case 'Cancel':
					$this->settings->getApiClient()->getTransactionVoidService()->voidOnline($this->settings->getSpaceId(), $transactionID);
					return new HttpControllerResponse('');

				default:
					return new HttpControllerResponse('Unknown action called to updated transaction status.');
			}
		}

		return new HttpControllerResponse(
			sprintf('Transaction should be in state %s', $transactionStateAuthorized)
		);
	}

	/**
	 * @return HttpControllerResponse
	 * @throws \Wallee\Sdk\ApiException
	 * @throws \Wallee\Sdk\Http\ConnectionException
	 * @throws \Wallee\Sdk\VersioningException
	 */
	public function actionDownloadFile()
	{
		$orderID = $_GET['orderId'];
		$action = $_GET['action'];
		$query = xtc_db_query("SELECT * FROM `wallee_transaction` WHERE order_id = " . xtc_db_input($orderID));
		$transactionData = xtc_db_fetch_array($query);

		$transactionStateFulfill = TransactionState::FULFILL;

		$allowedStates = [
			TransactionState::FULFILL,
			'REFUNDED',
			'PARTIALY REFUNDED',
			'PAID'
		];

		if (\in_array(strtoupper($transactionData['state']), $allowedStates)) {
			//if (strtolower($transactionData['state']) === strtolower($transactionStateFulfill)) {
			$transactionID = (int)$transactionData['transaction_id'];

			switch ($action) {
				case 'invoice':
					$document = $this->settings->getApiClient()->getTransactionService()->getInvoiceDocument($this->settings->getSpaceId(), $transactionID);
					break;

				case 'package-slip':
					$document = $this->settings->getApiClient()->getTransactionService()->getPackingSlip($this->settings->getSpaceId(), $transactionID);
					break;

				default:
					return new HttpControllerResponse('Unknown action called to updated transaction status.');
			}

			if ($document) {
				$filename = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '_', $document->getTitle()) . '.pdf';
				$filedata = base64_decode($document->getData());
				header('Content-Description: File Transfer');
				header('Content-Type: ' . $document->getMimeType());
				header('Content-Disposition: attachment; filename=' . $filename);
				header('Content-Transfer-Encoding: binary');
				header('Expires: 0');
				header('Cache-Control: must-revalidate');
				header('Pragma: public');
				header('Content-Length: ' . strlen($filedata));
				ob_clean();
				flush();
				echo $filedata;
			}
		}

		return new HttpControllerResponse(
			sprintf('Transaction should be in state %s', $transactionStateFulfill)
		);
	}

	public function actionRefund()
	{
		$orderId = $this->_getPostData('orderId');
		$amount = floatval($this->_getPostData('amount'));

		if ($amount <= 0) {
			return new HttpControllerResponse('Amount should be greater than 0');
		}

		$query = xtc_db_query("SELECT * FROM `wallee_transaction` WHERE order_id = " . xtc_db_input($orderId));
		$transactionData = xtc_db_fetch_array($query);

		$transactionInfo = json_decode($transactionData['data'], true);
		$transactionAmount = floatval($transactionInfo['info']['total']);

		$transactionStateFulfill = TransactionState::FULFILL;
		if (
			strtolower($transactionData['state']) === strtolower($transactionStateFulfill) &&
			$amount <= $transactionAmount
		) {
			$transactionID = (int)$transactionData['transaction_id'];
			$refundPayload = (new RefundCreate())
				->setAmount(\round($amount, 2))
				->setTransaction($transactionID)
				->setMerchantReference((string)$orderId)
				->setExternalId(uniqid('refund_', true), 100)
				->setType(RefundType::MERCHANT_INITIATED_ONLINE);

			if (!$refundPayload->valid()) {
				$this->logger->critical('Refund payload invalid:', $refundPayload->listInvalidProperties());
				throw new InvalidPayloadException('Refund payload invalid:' . json_encode($refundPayload->listInvalidProperties()));
			}

			$this->settings->getApiClient()->getRefundService()->refund($this->settings->getSpaceId(), $refundPayload);

			return new HttpControllerResponse('');
		}

		return new HttpControllerResponse(
			sprintf('Transaction should be in state %s', $transactionStateFulfill)
		);
	}
}