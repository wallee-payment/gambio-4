<?php

    class WalleeCheckoutConfirmationContentControl extends WalleeCheckoutConfirmationContentControl_parent
    {
        public function proceed()
        {
            $currencyCheck = $_SESSION['currencyCheck'] ?? null;
            if ($_SESSION['currency'] != $currencyCheck) {
                $this->set_redirect_url(xtc_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
                return;
            }

            $selectedPaymentMethod = trim((string)($this->v_data_array['POST']['payment'] ?? ''));
            if (empty($selectedPaymentMethod)) {
                $this->set_redirect_url(xtc_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=payment_method_not_available', 'SSL', true, false));
                return;
            }

            if (strpos($selectedPaymentMethod, 'wallee') === false) {
                return parent::proceed();
            }

            $this->v_data_array['POST']['payment'] = 'wallee';
            $_SESSION['chosen_payment_method'] = $selectedPaymentMethod;
            parent::proceed();
        }

        public function get_redirect_url()
        {
            if (!empty($_SESSION['credit_covers'])) {
                return null;
            }

            return parent::get_redirect_url();
        }
    }
