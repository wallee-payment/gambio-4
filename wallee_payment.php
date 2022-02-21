<?php

require_once 'includes/application_top.php';

if(isset($_GET['back_button'])) {
	xtc_redirect(GM_HTTP_SERVER.DIR_WS_CATALOG.FILENAME_CHECKOUT_CONFIRMATION);
}


if($_SERVER['REQUEST_METHOD'] == 'GET') {
	$contentView = MainFactory::create('ContentView', $_GET, $_POST);
	$contentView->set_flat_assigns(true);
	$contentView->set_template_dir(DIR_FS_DOCUMENT_ROOT);
	$contentView->set_content_template('GXModules/Wallee/WalleePayment/Shop/Themes/All/checkout_payment_wallee.html');
	if(isset($_REQUEST['ret_errormsg'])) {
		$contentView->set_content_data('silent_error', utf8_encode(strip_tags($_REQUEST['ret_errormsg'])));
	}

	$contentView->set_content_data('appJsUrl', 'GXModules/Wallee/WalleePayment/Shop/Templates/All/Javascript/extenders/wallee-app.js');
	$contentView->set_content_data('iframeJsUrl', $_SESSION['javascriptUrl']);
	$contentView->set_content_data('transactionID', $_SESSION['transactionID']);
	$contentView->set_content_data('transactionPossiblePaymentMethod', $_SESSION['possiblePaymentMethod']);
	$contentView->set_content_data('paymentMachineName', 'wallee');
	$contentView->set_content_data('integration', $_SESSION['integration']);
	$contentView->set_content_data('returned_fields', $_REQUEST);
	$contentView->set_content_data('orders_id',  $_SESSION['tmp_oID']);
	$contentView->set_content_data('back_url',   GM_HTTP_SERVER.DIR_WS_CATALOG.basename(__FILE__).'?back_button=go');

	$languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
	$contentView->set_content_data('translations',   [
			'pay' => $languageTextManager->get_text('pay', 'wallee'),
			'cancel' => $languageTextManager->get_text('cancel', 'wallee'),
		]
	);
	$main_content = $contentView->get_html();

	$coo_layout_control = MainFactory::create_object('LayoutContentControl');
	$coo_layout_control->set_data('GET', $_GET);
	$coo_layout_control->set_data('POST', $_POST);

	$coo_layout_control->set_('coo_breadcrumb', $GLOBALS['breadcrumb']);
	$coo_layout_control->set_('coo_product',    $GLOBALS['product']);
	$coo_layout_control->set_('coo_xtc_price',  $GLOBALS['xtPrice']);
	$coo_layout_control->set_('c_path',         $GLOBALS['cPath']);
	$coo_layout_control->set_('main_content',   $main_content);
	$coo_layout_control->set_('request_type',   $GLOBALS['request_type']);
	$coo_layout_control->proceed();
	echo $coo_layout_control->get_response();
}
