<?php declare(strict_types=1);

class Wallee_AdminApplicationTopPrimalExtender extends Wallee_AdminApplicationTopPrimalExtender_parent
{
	protected const ORDER_ACTION_CONTROLLER = 'WalleeOrderAction';

	public function proceed()
	{
		$this->rejectUnauthorizedOrderActionRequest();

		parent::proceed();
	}

	/**
	 * The shop answers admin requests of not logged in users with a redirect to the login page.
	 * The order action endpoints are called by ajax and by direct links, so they answer with 401
	 * instead. The page token of the request is validated afterwards by the controller itself.
	 *
	 * @return void
	 */
	protected function rejectUnauthorizedOrderActionRequest(): void
	{
		$requestedController = \explode('/', (string)($_GET['do'] ?? ''))[0];

		if ($requestedController !== self::ORDER_ACTION_CONTROLLER) {
			return;
		}

		if (!empty($_SESSION['customer_id'])
			&& isset($_SESSION['customers_status']['customers_status_id'])
			&& (string)$_SESSION['customers_status']['customers_status_id'] === '0') {
			return;
		}

		header('HTTP/1.1 401 Unauthorized');
		header('Cache-Control: no-cache');
		die('Unauthorized');
	}
}
