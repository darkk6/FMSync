<?php
/*******************************
 * @File : RevokeClientSession.php
 *
 * FileMaker Just Another Sync solution -- by darkk6
 *
 * Simple way to sync data with server (Partially similar to FMEasySync),
 * But use PHP API instead of external data source.
 * So that you can use thing tool with Runtime Solution.
 *
 * 註銷 Client 的 working session
 *
 * @Author darkk6 (LuChun Pan)
 * @Version 1.2.0
 *
 * @License GPLv3
 *
 ********************************/
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
	set_time_limit(0);
	$_request_body = file_get_contents('php://input');
	$ClientID = urldecode($_request_body);
	
	require_once("config/Config.php");
	require_once("EzFMDB/EzFMDB.php");
	$db = new EzFMDB($FMSync_HOST,$FMSync_DB,$FMSync_USER,$FMSync_PSWD);
	$db->setSkipEscapeCRLF(true,true);
	
	$res = $db->update("FMSync_ClientSession","working=0,syncing=0","WHERE ClientID='$ClientID'");
	
	echo "DONE";
?>