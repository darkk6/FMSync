<?php
/*******************************
 * @File : CrossSiteContainerBridge.php
 *
 * FileMaker Just Another Sync solution -- by darkk6
 *
 * Simple way to sync data with server (Partially similar to FMEasySync),
 * But use PHP API instead of external data source.
 * So that you can use thing tool with Runtime Solution.
 *
 * 提供 Request 轉送的功能，因為 FileMaker 的 getContainerData 必須在 local 端，若要跨站可以使用這個中介
 *
 * @Author darkk6 (LuChun Pan)
 * @Version 1.2.0
 *
 * @License GPLv3
 *
 ********************************/
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
	set_time_limit(0);
	if( !function_exists( 'curl_init' ) ) die( 'Error : cURL module is NOT loaded.' );
	
	require_once("config/Config.php");
	
	$_request_body = file_get_contents('php://input');
	$curlheader = array(
		'Content-Type: application/x-www-form-urlencoded'
	);
	
	$ch = curl_init($FMSync_CorssSiteContainerBridgeURL);
	curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_HEADER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $curlheader
		)
	);
	curl_setopt($ch,CURLOPT_POSTFIELDS,$_request_body);
	$response = curl_exec($ch);
	list($header,$body) = explode("\r\n\r\n",$response,2);
	
	curl_close($ch);
	
	$error="";
	//可能是伺服器掛了
	if($header=="") $error = "Can not connect to Server";
	
	$header = explode("\r\n",$header);
	$status = preg_replace("/HTTP\\/1.\d (\d+) (.+)/","$1",$header[0]);
	$statusMsg = preg_replace("/HTTP\\/1.\d (\d+) (.+)/","$2",$header[0]);
	if( $status!=200 ) $error="$status $statusMsg";
	
	if($error!=""){
		header("Content-type:plain/text");
		header("Content-Disposition: attachment; filename=Error.log");
		echo $error;
		exit(0);
	}
	
	array_shift($header);
	foreach($header as $h) header($h);
	echo $body;
?>