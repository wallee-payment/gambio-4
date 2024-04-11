<?php

use GXModules\Wallee\WalleePayment\Shop\Classes\Model\WalleeTransactionModel;
use GXModules\WalleePayment\Library\{Core\Settings\Options\Integration,
    Core\Settings\Struct\Settings,
    Helper\WalleeHelper
};
use Wallee\Sdk\Model\{AddressCreate, LineItemCreate, LineItemType, Transaction};
use Wallee\Sdk\Model\TransactionPending;

class Wallee_CheckoutProcessProcess extends Wallee_CheckoutProcessProcess_parent
{
    /**
     * The proceed method is the main method of the class and performs the complete checkout process.
     *
     * @return bool
     */
    public function proceed()
    {
        if (strpos($_SESSION['payment'] ?? '', 'wallee') === false) {
            parent::proceed();
            return true;
        }

        $_SESSION['gambio_hub_selection'] = $_SESSION['payment'];
        if ($this->check_redirect()) {
            return true;
        }

        $this->_initOrderData();

        if (!isset($_SESSION['tmp_oID']) || !is_int($_SESSION['tmp_oID'])) {
            $this->save_order();

            $this->save_module_data();
            $this->coo_order_total->apply_credit();

            $_SESSION['global_order'] = $GLOBALS['order'];
            $this->save_tracking_data();

            if ($this->tmp_order) {
                $this->coo_payment->payment_action();
            }
        }

        if ($this->tmp_order === false) {
            $settings = new Settings();

            if ($settings->isConfirmationEmailSendEnabled()) {
                $_SESSION['order_id'] = $this->order_id;
            }

            $this->coo_payment->after_process();
            if ($_SESSION['redirect_url']) {
                $redirectUrl = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
                xtc_redirect($redirectUrl);
                return true;
            }
            $this->set_redirect_url(xtc_href_link("shop.php", 'do=WalleePayment/PaymentPage', 'SSL'));
            return true;
        }

        return true;
    }

    /**
     * The save_order method stores the order and sets the orderId
     */
    public function save_order()
    {
        if (strpos($_SESSION['payment'] ?? '', 'wallee') === false) {
            return parent::save_order();
        }

        $settings = new Settings();
        $integration = $settings->getIntegration();
        $orderId = $this->createOrder();
        $createdTransactionId = $_SESSION['createdTransactionId'];

        $transaction = $this->getTransactionFromPortal($createdTransactionId);
        $this->confirmTransaction($transaction, $orderId, $settings);

        $transactionModel = new WalleeTransactionModel();
        $transactionModel->create($settings, $createdTransactionId, $orderId, (array)$this->coo_order);

        $_SESSION['integration'] = $integration;
        $this->_setOrderId($orderId);

        if ($integration == Integration::PAYMENT_PAGE) {
            $redirectUrl = $settings->getApiClient()->getTransactionPaymentPageService()
                ->paymentPageUrl($settings->getSpaceId(), $createdTransactionId);
            $_SESSION['redirect_url'] = $redirectUrl;
        } else {
            $_SESSION['javascriptUrl'] = $this->getTransactionJavaScriptUrl($createdTransactionId);
            $_SESSION['possiblePaymentMethod'] = $this->getTransactionPaymentMethod($settings, $createdTransactionId);
            $_SESSION['orderTotal'] = $this->coo_order_total->output_array();
        }
    }

    /**
     * @param string $transactionId
     * @return Transaction|null
     * @throws \Wallee\Sdk\ApiException
     * @throws \Wallee\Sdk\Http\ConnectionException
     * @throws \Wallee\Sdk\VersioningException
     */
    public function getTransactionFromPortal(string $transactionId): ?Transaction
    {
        $settings = new Settings();
        return $settings->getApiClient()
            ->getTransactionService()
            ->read($settings->getSpaceId(), $transactionId);
    }

    /**
     * @param Transaction $transaction
     * @param string $orderId
     * @param Settings $settings
     * @return Transaction
     * @throws \Wallee\Sdk\ApiException
     * @throws \Wallee\Sdk\Http\ConnectionException
     * @throws \Wallee\Sdk\VersioningException
     */
    private function confirmTransaction(Transaction $transaction, string $orderId, Settings $settings): Transaction
    {
        $order = (array)$this->coo_order;

        $lineItems = [];
        foreach ($order['products'] as $product) {
            $lineItem = new LineItemCreate();
            $lineItem->setName($product['name']);
            $lineItem->setUniqueId($product['id']);
            $lineItem->setSku($product['id']);
            $lineItem->setQuantity($product['qty']);
            $lineItem->setAmountIncludingTax(floatval((string)$product['final_price']));
            $lineItem->setType(LineItemType::PRODUCT);
            $lineItems[] = $lineItem;
        }

        $shippingLineItem = $this->getShippingLineItem($order);
        if ($shippingLineItem) {
            $lineItems[] = $shippingLineItem;
        }

        $discountLineItem = $this->getDiscountLineItem();
        if ($discountLineItem) {
            $lineItems[] = $discountLineItem;
        }

        $giftCouponLineItem = $this->getGiftVoucherLineItem();
        if ($giftCouponLineItem) {
            $lineItems[] = $giftCouponLineItem;
        }

        $pendingTransaction = new TransactionPending();
        $pendingTransaction->setId($transaction->getId());
        $pendingTransaction->setVersion($transaction->getVersion());
        $pendingTransaction->setLineItems($lineItems);

        $billingAddress = $this->getBillingAddress($order);
        $shippingAddress = $this->getShippingAddress($order);

        $pendingTransaction->setCurrency($order['info']['currency']);
        $pendingTransaction->setLineItems($lineItems);
        $pendingTransaction->setBillingAddress($billingAddress);
        $pendingTransaction->setShippingAddress($shippingAddress);

        $pendingTransaction->setMetaData([
            'spaceId' => $settings->getSpaceId(),
            'orderId' => $orderId
        ]);

        $pendingTransaction->setMerchantReference($orderId);

        if ($settings->getIntegration() === Integration::PAYMENT_PAGE) {
            $paymentMethodConfigurationId = WalleeHelper::getPaymentMethodConfigurationId();
            if ($paymentMethodConfigurationId) {
                $pendingTransaction->setAllowedPaymentMethodConfigurations([$paymentMethodConfigurationId]);
            }
        }

        $pendingTransaction->setSuccessUrl(xtc_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
        $pendingTransaction->setFailedUrl(xtc_href_link(FILENAME_CHECKOUT_PAYMENT . '?payment_error', '', 'SSL'));

        $settings = new Settings();
        $transaction = $settings->getApiClient()->getTransactionService()
            ->confirm($settings->getSpaceId(), $pendingTransaction);

        return $transaction;
    }

    /**
     * @return mixed
     */
    protected function _getOrderPaymentType()
    {
        $walleePaymentType = $_SESSION['payment_methods_title'] ?? null;
        if (empty($walleePaymentType) || strpos($_SESSION['payment'], 'wallee') === false) {
            return parent::_getOrderPaymentType();
        }

        return MainFactory::create('OrderPaymentType', new StringType($_SESSION['payment_methods_title']),
            new StringType($_SESSION['payment_methods_title']));
    }

    /**
     * @param array $order
     * @return LineItemCreate|null
     */
    private function getShippingLineItem(array $order): ?LineItemCreate
    {
        $shippingCost = floatval((string)$order['info']['shipping_cost']);
        if ($shippingCost > 0) {
            $lineItem = new LineItemCreate();
            $lineItem->setName('Shipping: ' . $order['info']['shipping_method']);
            $lineItem->setUniqueId('shipping-' . $order['info']['shipping_class']);
            $lineItem->setSku('shipping-' . $order['info']['shipping_class']);
            $lineItem->setQuantity(1);
            $lineItem->setAmountIncludingTax($shippingCost);
            $lineItem->setType(LineItemType::SHIPPING);
            return $lineItem;
        }

        return null;
    }

    /**
     * @return LineItemCreate|null
     */
    private function getDiscountLineItem(): ?LineItemCreate
    {
        global $xtPrice;
        if (($GLOBALS['ot_coupon']->deduction ?? 0) > 0) {
            $lineItem = new LineItemCreate();
            $lineItem->setName($GLOBALS['ot_coupon']->output['0']['title']);
            $lineItem->setUniqueId('coupon-' . $GLOBALS['ot_coupon']->coupon_code);
            $lineItem->setSku('coupon-' . $GLOBALS['ot_coupon']->coupon_code);
            $lineItem->setQuantity(1);
            $lineItem->setAmountIncludingTax(round($xtPrice->xtcFormat($GLOBALS['ot_coupon']->output['0']['value'], false)));
            $lineItem->setType(LineItemType::DISCOUNT);
            return $lineItem;
        }
        return null;
    }

    /**
     * @return LineItemCreate|null
     */
    private function getGiftVoucherLineItem(): ?LineItemCreate
    {
        $orderTotals = $GLOBALS['order_totals'] ?? null;
        if (empty($orderTotals)) {
            return null;
        }

        $customerCredit = null;
        foreach ($orderTotals as $orderItem) {
            if ($orderItem['code'] === 'ot_gv') {
                $customerCredit = $orderItem;
                break;
            }
        }

        if ($customerCredit) {
            $amount = $customerCredit['value'];
            $lineItem = new LineItemCreate();
            $lineItem->setName('Gift Voucher');
            $lineItem->setUniqueId('gift-voucher-' . $amount);
            $lineItem->setSku('gift-voucher-' . $amount);
            $lineItem->setQuantity(1);
            $lineItem->setAmountIncludingTax(-1 * $amount);
            $lineItem->setType(LineItemType::DISCOUNT);
            return $lineItem;
        }

        return null;
    }

    /**
     * @return string
     */
    private function createOrder(): string
    {
        return $this->orderWriteService->createNewCustomerOrder($this->_getCustomerId(),
            $this->_getCustomerStatusInformation(),
            $this->_getCustomerNumber(),
            $this->_getCustomerEmail(),
            $this->_getCustomerTelephone(),
            $this->_getCustomerVatId(),
            $this->_getCustomerDefaultAddress(),
            $this->_getBillingAddress(),
            $this->_getDeliveryAddress(),
            $this->_getOrderItemCollection(),
            $this->_getOrderTotalCollection(),
            $this->_getOrderShippingType(),
            $this->_getOrderPaymentType(),
            $this->_getCurrencyCode(),
            $this->_getLanguageCode(),
            $this->_getOrderTotalWeight(),
            $this->_getComment(),
            $this->_getOrderStatusId(),
            $this->_getOrderAddonValuesCollection());
    }

    /**
     * @param Settings $settings
     * @param string $transactionId
     * @return array
     * @throws \Wallee\Sdk\ApiException
     * @throws \Wallee\Sdk\Http\ConnectionException
     * @throws \Wallee\Sdk\VersioningException
     */
    private function getTransactionPaymentMethod(Settings $settings, string $transactionId): array
    {
        $possiblePaymentMethods = $settings->getApiClient()
            ->getTransactionService()
            ->fetchPaymentMethods(
                $settings->getSpaceId(),
                $transactionId,
                $settings->getIntegration()
            );

        $chosenPaymentMethod = $_SESSION['choosen_payment_method'];

        return array_filter($possiblePaymentMethods, function ($possiblePaymentMethod) use ($chosenPaymentMethod) {
            $slug = 'wallee_' . trim(strtolower(WalleeHelper::slugify($possiblePaymentMethod->getName())));
            return $slug === $chosenPaymentMethod;
        }) ?? [];
    }

    /**
     * @param int $transactionId
     * @return string
     * @throws \Wallee\Sdk\ApiException
     * @throws \Wallee\Sdk\Http\ConnectionException
     * @throws \Wallee\Sdk\VersioningException
     */
    private function getTransactionJavaScriptUrl(int $transactionId): string
    {
        $settings = new Settings();

        return $settings->getApiClient()->getTransactionIframeService()
            ->javascriptUrl($settings->getSpaceId(), $transactionId);
    }

    /**
     * @param array $order
     * @return AddressCreate
     */
    private function getBillingAddress(array $order): AddressCreate
    {
        $billingAddress = new AddressCreate();
        $billingAddress->setStreet($order['billing']['street_address']);
        $billingAddress->setCity($order['billing']['city']);
        $billingAddress->setCountry($order['billing']['country']['iso_code_2']);
        $billingAddress->setEmailAddress($order['customer']['email_address']);
        $billingAddress->setFamilyName($order['billing']['lastname']);
        $billingAddress->setGivenName($order['billing']['firstname']);
        $billingAddress->setPostCode($order['billing']['postcode']);
        $billingAddress->setPostalState($order['billing']['state']);
        $billingAddress->setOrganizationName($order['billing']['company']);
        $billingAddress->setPhoneNumber($order['customer']['telephone']);
        $billingAddress->setSalutation($order['customer']['gender'] === 'm' ? 'Mr' : 'Ms');

        return $billingAddress;
    }

    /**
     * @param array $order
     * @return AddressCreate
     */
    private function getShippingAddress(array $order): AddressCreate
    {
        $shippingAddress = new AddressCreate();
        $shippingAddress->setStreet($order['delivery']['street_address']);
        $shippingAddress->setCity($order['delivery']['city']);
        $shippingAddress->setCountry($order['delivery']['country']['iso_code_2']);
        $shippingAddress->setEmailAddress($order['customer']['email_address']);
        $shippingAddress->setFamilyName($order['delivery']['lastname']);
        $shippingAddress->setGivenName($order['delivery']['firstname']);
        $shippingAddress->setPostCode($order['delivery']['postcode']);
        $shippingAddress->setPostalState($order['delivery']['state']);
        $shippingAddress->setOrganizationName($order['delivery']['company']);
        $shippingAddress->setPhoneNumber($order['customer']['telephone']);
        $shippingAddress->setSalutation($order['customer']['gender'] === 'm' ? 'Mr' : 'Ms');

        return $shippingAddress;
    }
}
