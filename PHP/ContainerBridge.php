<?php
/*******************************
 * @File : ContainerBridge.php
 *
 * FileMaker Just Another Sync solution -- by darkk6
 *
 * Simple way to sync data with server (Partially similar to FMEasySync),
 * But use PHP API instead of external data source.
 * So that you can use thing tool with Runtime Solution.
 *
 * 負責處理 Client 下載 Container 資料的部分 ( 透過 Insert From URL )
 *
 * @Author darkk6 (LuChun Pan)
 * @Version 1.0.0
 *
 * @License GPLv3
 *
 ********************************/
	set_time_limit(0);
	
	/* FileMaker 支援的 MINE type */
	$mimetypeList = array();
	$json=file_get_contents("minetype.json");
	if($json!==FALSE){
		$mimetypeList = json_decode($json,true);
	}
	if( !is_array($mimetypeList) ) $mimetypeList = array();
	
	//過濾參數傳來的網址
	$_request_body = file_get_contents('php://input');
	//$fileURL = urldecode($_request_body);
	$fileURL = $_request_body;
	
	
	$fileName = substr($fileURL,0,strpos($fileURL,'?'));
	$fileName = urldecode(substr($fileName,strrpos($fileName,'/')+1));
	$ext = pathinfo($fileName, PATHINFO_EXTENSION);
	
	$mimeType = "application/octet-stream";
	if( array_key_exists(strtolower($ext),$mimetypeList) ) $mimeType = $mimetypeList[$ext];
	
	require_once("config/Config.php");
	require_once("EzFMDB/EzFMDB.php");
	$db = new EzFMDB($FMSync_HOST,$FMSync_DB,$FMSync_USER,$FMSync_PSWD);
	
	$res = $db->getContainerData($fileURL);
	if( $db->isError($res) ){
		header("Content-type:plain/text");
		header("Content-Disposition: attachment; filename=Error.log");
		$res = $db->getErrInfo($res);
	}else{
		header("Content-type:".$mimeType);
		header("Content-Disposition: attachment; filename=".$fileName);
	}
	echo $res;
?>