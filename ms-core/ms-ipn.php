<?php
	if( !defined( 'MS_H_URL' ) ) { echo 'Direct access not allowed.';  exit; }
	error_reporting( E_ERROR | E_PARSE );
    echo 'Start IPN';

	$ipn_parameters = array();
	$_parameters = explode('|', $_GET['ms-action']);
	foreach($_parameters as $_parameter)
	{
		$_parameter_parts = explode('=',$_parameter);
		if(count($_parameter_parts) == 2)
		{
			$ipn_parameters[$_parameter_parts[0]] = $_parameter_parts[1];
		}
	}

    global $music_store_settings;

    $item_name = $_POST['item_name'];
	$item_number = $_POST['item_number'];
	$payment_status = $_POST['payment_status'];
	$payment_amount = $_POST['mc_gross'];
	if( !empty( $_POST[ 'tax' ] ) ) $payment_amount -= $_POST[ 'tax' ];
	$payment_currency = $_POST['mc_currency'];
	$txn_id = $_POST['txn_id'];
	$receiver_email = $_POST['receiver_email'];
	$payer_email = $_POST['payer_email'];
	$payment_type = $_POST['payment_type'];

	if ($payment_status != 'Completed' && $payment_type != 'echeck') exit;
	if ($payment_type == 'echeck' && $payment_status == 'Completed') exit;

	$price = -1;

	if(!isset($ipn_parameters['id']) || !isset($ipn_parameters['purchase_id'])) exit;

	$id = intval($ipn_parameters['id']);
	$_post = get_post($id);
	if(is_null($_post)) exit;
	switch ($_post->post_type){
		case "ms_song":
			$obj = new MSSong($id);
		break;
		case "ms_collection":
			$obj = new MSCollection($id);
		break;
		default:
			exit;
		break;
	}

	if (!isset($obj->price) || abs($payment_amount - $obj->price) > 0.2) exit;

	$str = "";
	foreach ($_POST as $item => $value) $str .= $item."=".$value."\r\n";

	// Insert purchase in database
	if($wpdb->insert(
						$wpdb->prefix.MSDB_PURCHASE,
						array(
							'product_id'  => $ipn_parameters['id'],
							'purchase_id' => $ipn_parameters['purchase_id'],
							'date'		  => date( 'Y-m-d H:i:s'),
							'email'		  => $payer_email,
							'amount'	  => $payment_amount,
							'paypal_data' => $str
						),
						array('%d', '%s', '%s', '%s', '%f', '%s')
					))
	{
		// Increase sales in elements
		$obj->purchases++;
	}

	$licenses = "";
	if( !empty( $music_store_settings[ 'ms_license_for_regular' ] ) )
	{
		$licenses = __( "License for regular purchases", MS_TEXT_DOMAIN ).": ".$music_store_settings[ 'ms_license_for_regular' ]."\n";
	}

	do_action('ms_paypal_ipn_received', $_POST, $obj);

	$ms_notification_from_email 		= $music_store_settings[ 'ms_notification_from_email' ];
	$ms_notification_to_email   		= $music_store_settings[ 'ms_notification_to_email' ];

	$ms_notification_to_payer_subject   = $music_store_settings[ 'ms_notification_to_payer_subject' ];
	$ms_notification_to_payer_message   = $music_store_settings[ 'ms_notification_to_payer_message' ];

	$ms_notification_to_seller_subject  = $music_store_settings[ 'ms_notification_to_seller_subject' ];
	$ms_notification_to_seller_message  = $music_store_settings[ 'ms_notification_to_seller_message' ];

    $dlurl = $GLOBALS['music_store']->_ms_create_pages( 'ms-download-page', 'Download Page' ); // for download-page
    $dlurl .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' );

	$information_payer = "Product: {$item_name}\n".
						 "Amount: {$payment_amount} {$payment_currency}\n".
						 "Download Link: ".$dlurl."ms-action=download&purchase_id={$ipn_parameters['purchase_id']}\n";

	$information_seller = "Product: {$item_name}\n".
						  "Amount: {$payment_amount} {$payment_currency}\n".
						  "Buyer Email: {$payer_email}\n".
						  "Download Link: ".$dlurl."ms-action=download&purchase_id={$ipn_parameters['purchase_id']}\n";

	$ms_notification_to_payer_message  = str_replace("%INFORMATION%", $information_payer."\n".$licenses, $ms_notification_to_payer_message);
	$ms_notification_to_seller_message = str_replace("%INFORMATION%", $information_seller, $ms_notification_to_seller_message);

    // Send email to payer
	try
	{
		wp_mail($payer_email, $ms_notification_to_payer_subject, $ms_notification_to_payer_message,
				"From: \"$ms_notification_from_email\" <$ms_notification_from_email>\r\n".
				"Content-Type: text/plain; charset=utf-8\n".
				"X-Mailer: PHP/" . phpversion());
	}
	catch( Exception $mail_err ){}

    // Send email to seller
	try
	{
		wp_mail($ms_notification_to_email , $ms_notification_to_seller_subject, $ms_notification_to_seller_message,
				"From: \"$ms_notification_from_email\" <$ms_notification_from_email>\r\n".
				"Content-Type: text/plain; charset=utf-8\n".
				"X-Mailer: PHP/" . phpversion());
	}
	catch( Exception $mail_err ){}

	echo 'OK';
	exit();
?>