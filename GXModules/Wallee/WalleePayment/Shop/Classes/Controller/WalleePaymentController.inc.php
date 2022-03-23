<?php declare(strict_types=1);

class WalleePaymentController extends HttpViewController
{
	/**
	 * @throws Exception
	 */
	public function actionPaymentPage()
	{
		$this->contentView->set_flat_assigns(true);
		$this->contentView->set_template_dir(DIR_FS_DOCUMENT_ROOT);
		$this->contentView->set_content_template('GXModules/Wallee/WalleePayment/Shop/Themes/All/checkout_payment_wallee.html');

		$order = (array)new order($_SESSION['tmp_oID']);

		$content = $this->_render(
			'GXModules/Wallee/WalleePayment/Shop/Themes/All/checkout_payment_wallee.html',
			[
				'appJsUrl' => 'GXModules/Wallee/WalleePayment/Shop/Templates/All/Javascript/extenders/wallee-app.js',
				'iframeJsUrl' => $_SESSION['javascriptUrl'],
				'transactionID' => $_SESSION['transactionID'],
				'transactionPossiblePaymentMethod' => $_SESSION['possiblePaymentMethod'],
				'integration' => $_SESSION['integration'],
				'returned_fields' => $_REQUEST,
				'order' => $order,
				'productsData' => $this->getProductsHtml($order),
				'orderSummaryData' => $_SESSION['orderTotal']
			]
		);

		$coo_layout_control = MainFactory::create_object('LayoutContentControl');
		$coo_layout_control->set_data('GET', $_GET);
		$coo_layout_control->set_data('POST', $_POST);

		$coo_layout_control->set_('coo_breadcrumb', $GLOBALS['breadcrumb']);
		$coo_layout_control->set_('coo_product', $GLOBALS['product']);
		$coo_layout_control->set_('coo_xtc_price', $GLOBALS['xtPrice']);
		$coo_layout_control->set_('c_path', $GLOBALS['cPath']);
		$coo_layout_control->set_('main_content', $content);
		$coo_layout_control->set_('request_type', $GLOBALS['request_type']);
		$coo_layout_control->proceed();

		return new HttpControllerResponse($coo_layout_control->get_response());
	}

	/**
	 * @param array $order
	 * @return string
	 */
	private function getProductsHtml(array $order): string
	{
		$coo_properties_control = MainFactory::create_object('PropertiesControl');
		$coo_properties_view = MainFactory::create_object('PropertiesView');

		$t_products_array = [];
		for ($i = 0, $n = sizeof($order['products']); $i < $n; $i++) {
			$coo_product_item = new product(xtc_get_prid($order['products'][$i]['id']));

			$t_options_values_array = [];
			$t_attr_weight = 0;
			$t_attr_model_array = [];

			if (isset($order['products'][$i]['attributes']) && is_array($order['products'][$i]['attributes'])) {
				foreach ($order['products'][$i]['attributes'] as $t_attributes_data_array) {
					$t_options_values_array[$t_attributes_data_array['option_id']] = $t_attributes_data_array['value_id'];
				}

				// calculate attributes weight and get attributes model
				foreach ($t_options_values_array as $t_option_id => $t_value_id) {
					$t_attr_sql = "SELECT
						options_values_weight AS weight,
						weight_prefix AS prefix,
						attributes_model
					FROM
						products_attributes
					WHERE
						products_id				= '" . (int)xtc_get_prid($order['products'][$i]['id']) . "' AND
						options_id				= '" . (int)$t_option_id . "' AND
						options_values_id		= '" . (int)$t_value_id . "'
					LIMIT 1";
					$t_attr_result = xtc_db_query($t_attr_sql);

					if (xtc_db_num_rows($t_attr_result) == 1) {
						$t_attr_result_array = xtc_db_fetch_array($t_attr_result);

						if (trim($t_attr_result_array['attributes_model']) != '') {
							$t_attr_model_array[] = $t_attr_result_array['attributes_model'];
						}

						if ($t_attr_result_array['prefix'] == '-') {
							$t_attr_weight -= (double)$t_attr_result_array['weight'];
						} else {
							$t_attr_weight += (double)$t_attr_result_array['weight'];
						}
					}
				}
			}

			$t_shipping_time = '';
			if (ACTIVATE_SHIPPING_STATUS == 'true') {
				$t_shipping_time = $order['products'][$i]['shipping_time'];
			}

			$t_products_weight = '';
			if (!empty($coo_product_item->data['gm_show_weight'])) {
				$t_products_weight = gm_prepare_number((double)$order['products'][$i]['weight'] + $t_attr_weight, $GLOBALS['xtPrice']->currencies[$GLOBALS['xtPrice']->actualCurr]['decimal_point']);
			}

			$t_products_model = $order['products'][$i]['model'];
			if ($t_products_model != '' && isset($t_attr_model_array[0])) {
				$t_products_model .= '-' . implode('-', $t_attr_model_array);
			} else {
				$t_products_model .= implode('-', $t_attr_model_array);
			}

			$t_properties = '';
			$t_combis_id = '';
			$t_properties_array = [];

			if (strpos($order['products'][$i]['id'], 'x') !== false) {
				$t_combis_id = (int)substr($order['products'][$i]['id'], strpos($order['products'][$i]['id'], 'x') + 1);
			}

			if ($t_combis_id != '') {
				$t_properties = $coo_properties_view->get_order_details_by_combis_id($t_combis_id, 'cart');
				$t_properties_array = $coo_properties_view->v_coo_properties_control->get_properties_combis_details($t_combis_id, $this->languages_id);

				if (method_exists($coo_properties_control, 'get_properties_combis_model')) {
					$t_combi_model = $coo_properties_control->get_properties_combis_model($t_combis_id);

					if (APPEND_PROPERTIES_MODEL == "true") {
						if ($t_products_model != '' && $t_combi_model != '') {
							$t_products_model = $t_products_model . '-' . $t_combi_model;
						} else if ($t_combi_model != '') {
							$t_products_model = $t_combi_model;
						}
					} else {
						if ($t_combi_model != '') {
							$t_products_model = $t_combi_model;
						}
					}

					if ($coo_product_item->data['use_properties_combis_shipping_time'] == 1 && ACTIVATE_SHIPPING_STATUS == 'true') {
						$t_shipping_time = $coo_properties_control->get_properties_combis_shipping_time($t_combis_id);
					}
				}
			}

			require_once(DIR_FS_INC . 'get_products_vpe_array.inc.php');
			$t_products_item = [
				'products_name' => '',
				'quantity' => '',
				'price' => $GLOBALS['xtPrice']->xtcFormat($order['products'][$i]['price'], true),
				'final_price' => '',
				'shipping_status' => '',
				'attributes' => '',
				'flag_last_item' => false,
				'PROPERTIES' => $t_properties,
				'properties_array' => $t_properties_array,
				'products_image' => (!empty($coo_product_item->data['gm_show_image']) && !empty($coo_product_item->data['products_image'])) ? DIR_WS_THUMBNAIL_IMAGES . $coo_product_item->data['products_image'] : '',
				'products_vpe_array' => get_products_vpe_array($order['products'][$i]['id'], $order['products'][$i]['price'], $t_options_values_array),
				'products_alt' => (!empty($coo_product_item->data['gm_alt_text'])) ? $coo_product_item->data['gm_alt_text'] : $order['products'][$i]['name'],
				'checkout_information' => $coo_product_item->data['checkout_information'],
				'products_url' => xtc_href_link('request_port.php', 'module=ProductDetails&id=' . $order['products'][$i]['id'], 'SSL'),
				'products_model' => $t_products_model,
				'products_weight' => $t_products_weight,
				'shipping_time' => $t_shipping_time,
				'DATA_ARRAY' => $coo_product_item->data
			];
			$t_products_attributes = [];

			if (ACTIVATE_SHIPPING_STATUS == 'true') {
				$t_products_item['shipping_status'] = SHIPPING_TIME . $order['products'][$i]['shipping_time'];
			}

			$t_products_item['quantity'] = gm_convert_qty($order['products'][$i]['qty'], false);
			$t_products_item['products_name'] = $order['products'][$i]['name'];
			$t_products_item['final_price'] = $GLOBALS['xtPrice']->xtcFormat($order['products'][$i]['final_price'], true);
			$t_products_item['unit'] = $order['products'][$i]['unit_name'];

			if ((isset($order['products'][$i]['attributes'])) && (sizeof($order['products'][$i]['attributes']) > 0)) {
				for ($j = 0, $n2 = sizeof($order['products'][$i]['attributes']); $j < $n2; $j++) {
					$t_products_attributes_item = [
						'option' => $order['products'][$i]['attributes'][$j]['option'],
						'value' => $order['products'][$i]['attributes'][$j]['value']
					];
					$t_products_attributes[] = $t_products_attributes_item;
				}

				$this->add_customizer_data($t_products_attributes, $order['products'][$i]['id']);

				$t_products_item['attributes'] = $t_products_attributes;
			}

			$t_products_array[] = $t_products_item;
		}
		$coo_content_view = MainFactory::create_object('ContentView');
		$coo_content_view->set_content_template('checkout_confirmation_products.html');
		$coo_content_view->set_content_data('products_data', $t_products_array);

		return $coo_content_view->get_html();
	}
}
