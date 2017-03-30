<?php
/*******************************
 * @File : ReciveBinaryPayload.php
 *
 * FileMaker Just Another Sync solution -- by darkk6
 *
 * Simple way to sync data with server (Partially similar to FMEasySync),
 * But use PHP API instead of external data source.
 * So that you can use thing tool with Runtime Solution.
 *
 * 負責處理由 Client 傳來需要更新的 Container 資料(Base64 String)
 *
 * @Author darkk6 (LuChun Pan)
 * @Version 1.1.0
 *
 * @License GPLv3
 *
 ********************************/
 
	/*
		為保險起見，這邊一次只送一筆資料，因此採用 \r 隔開，欄位意義依序如下：
		
			1. Client ID (透過 FileMaker Get (PersistentID) 取得的 )
			2. tableName
			3. fieldName
			4. repetition
			5. fileName
			6. Sync_UUID
			7. Sync_UTC
			8. base64 String
	*/
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
	set_time_limit(0);
	$_request_body = file_get_contents('php://input');
	$_request_body = urldecode($_request_body);
	$payLoadLines=explode("\r",$_request_body);
	
	$ClientID=null;
	$data=array();
	
	foreach($payLoadLines as $line){
		if( strlen($line)==0 ) continue;
		
		//第一行一定是 ClientID , 透過 FileMaker Get(PersistentID) 取得
		if( $ClientID==null ){
			$ClientID = $line;
			continue;
		}
		$data[] = $line;//之後直接再用 \r 合併當作參數傳給 Script
	}
	
	
	require_once("config/Config.php");
	require_once("EzFMDB/EzFMDB.php");
	$db = new EzFMDB($FMSync_HOST,$FMSync_DB,$FMSync_USER,$FMSync_PSWD);
	
	/* 如有需要自行啟用 , Uncomment this is you need log file
		require_once("EzFMDB/util/FileLogger.php");
		$log = new FileLogger("log/binary.log",true);
		$db->setDebug($log);
	*/
	
	$res = $db->runScript("FMSync_ClientSession",$FMSync_ContainerBriderScript,$_request_body);
	
	if( $db->isError($res) ) echo $db->getErrInfo($res);
	else echo "DONE";
?>