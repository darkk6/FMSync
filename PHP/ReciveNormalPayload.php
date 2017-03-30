<?php
/*******************************
 * @File : ReciveNormalPayload.php
 *
 * FileMaker Just Another Sync solution -- by darkk6
 *
 * Simple way to sync data with server (Partially similar to FMEasySync),
 * But use PHP API instead of external data source.
 * So that you can use thing tool with Runtime Solution.
 *
 * 負責處理由 Client 傳來需要更新資料的 Payload
 *
 * @Author darkk6 (LuChun Pan)
 * @Version 1.1.0
 *
 * @License GPLv3
 *
 ********************************/
 
	// ini_set('auto_detect_line_endings',true);//這樣才能讓 php 讀取到 Mac 的 \r 換行 , 但應該不是很需要
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
	set_time_limit(0);
	
	$_request_body = file_get_contents('php://input');
	$_request_body = urldecode($_request_body);
	$payLoadLines=explode("<fmsbr/>",$_request_body);
	
	$SyncTable=array();
	$lastTable=NULL;
	$nextIsField=false;
	$ClientID=null;
	$TheUTC=-1;
	foreach($payLoadLines as $line){
		// $line = mb_convert_encoding($line,"UTF-8","UTF-16");// FileMaker export 出來的格式為 UTF-16 (開頭 FF FE)
		// $line = str_replace(array("\r","\n"),"",$line);
		
		if( strlen($line)==0 ) continue;
		
		//第一行一定是 ClientID , 透過 FileMaker Get(PersistentID) 取得
		if( $ClientID==null ){
			$ClientID = $line;
			continue;
		}
		
		//第二行一定是由 Client 傳來的 UTC , 所有更新的紀錄 UTC 都要改成這個時間
		if( $TheUTC==-1 ){
			$TheUTC = $line;//不能轉數字，因為 int 上限的關係
			continue;
		}
		
		
		if( preg_match("/^<table>/",$line) ){
			$lastTable = preg_replace("/<table>(.+)/","$1",$line);
			$nextIsField=true;
			$SyncTable[$lastTable]=array();
			$repFieldInfo = array();
			continue;
		}
		if( is_null($lastTable) ) continue;
		
		if($nextIsField){
			//取得欄位定義
			$fieldTable = array();
			$tmp = explode("</field>",$line);
			foreach($tmp as $field){
				if( preg_match("/\\[\\d+\\]$/",$field) ){
					$namePart = preg_replace("/(.+)\\[(\\d+)\\]/","$1",$field);
					$countPart = intval(preg_replace("/(.+)\\[(\\d+)\\]/","$2",$field));
					if( !isset($repFieldInfo[$namePart]) ) $repFieldInfo[$namePart]=0;
					$repFieldInfo[$namePart]++;
				}
				array_push($fieldTable,$field);
			}
			$nextIsField = false;
		}else{
			//取得欄位資料
			$record = array();
			$data = explode("</col>",$line);
			
			for($idx=0;$idx<count($data);$idx++){
				$col = $data[$idx];
				$field = $fieldTable[$idx];
				$checkRep = preg_replace("/(.+)\\[(\\d+)\\]/","$1",$field);
				if( array_key_exists($checkRep,$repFieldInfo) ){
					//是 repetition field
					$tmp = array();
					for($rp=0;$rp<$repFieldInfo[$checkRep];$rp++){
						$col = $data[$idx+$rp];
						array_push($tmp,$col);
					}
					$record[$checkRep] = $tmp;
					$idx+=($rp-1);
				}else{
					//不是 repetition field
					//如果是 UTC 欄位，強制改成 client 給的 UTC 時間
					if( $field=="SYNC_UTC" ) $record[$field] = $TheUTC;
					else $record[$field] = $col;
				}
			}
			
			//將 ClientID 放到 $record 最前面
			$record = array_reverse($record, true);
			$record["SYNC_CLIENTID"] = $ClientID;
			$record = array_reverse($record, true); 
			
			array_push($SyncTable[$lastTable],$record);
		}
	}
	
	
	
	require_once("config/Config.php");
	require_once("EzFMDB/EzFMDB.php");
	$db = new EzFMDB($FMSync_HOST,$FMSync_DB,$FMSync_USER,$FMSync_PSWD);
	
	/* 如有需要自行啟用 , Uncomment this is you need log file
		require_once("EzFMDB/util/FileLogger.php");
		$log = new FileLogger("log/playlod.log",false);
		$db->setDebug($log);
	*/
	
	$db->setCastResult(false);//因為 FileMaker 的 Number 超過了 php 的 int 上限 , 判斷上會有問題
	
	// Field Data 是直接透過 PHP API 寫入，因此要先執行登記 ClientID 的 UTC Override , 讓 UTC 改為 client 傳來的時間
	$res = $db->runScript("FMSync_ClientSession",$FMSync_RegisterSessionScript,$ClientID,"1");
	if( $db->isError($res) ) die($db->getErrInfo($res));
	
	//取得 layout 資料
	$layoutList = $db->getLayouts();
	
	//開始處理每一筆資料
	foreach($SyncTable as $layout => $records){
		//檢查 layout 是否存在
		if( !in_array($layout,$layoutList) ){
			$db->log("Check layout","'$layout' not exists");
			continue;
		}
		foreach($records as $record){
			//如果沒有 SYNC_UUID 或 SYNC_UTC 則跳過不處理
			if( !isset( $record['SYNC_UUID'] ) || !isset( $record['SYNC_UTC'] ) ){
				$db->log("Push Record","Record lacks UUID or UTC");
				continue;
			}
			//尋找是否有這筆資料 (取 uuid 和 delete mark)
			$res=$db->select($layout,"SYNC_UTC , SYNC_DELETE","WHERE SYNC_UUID='".$record['SYNC_UUID']."'");
			if( $db->isError($res) || ( is_array($res) && count($res)==0 ) ){
				$res=$db->insert($layout,$record);
			}else{
				//取出第一筆資料的 fm_recid 並比較 SYNC_UTC , 僅使用較大者
				$rcid = reset($res);
				$utc = $rcid['SYNC_UTC'];
				if( $rcid['SYNC_DELETE']==1 ){
					//如果在 Server 上，這筆資料是被刪除的，就過略這筆資料不處理
					$db->log("Push Record","Record ".$record['SYNC_UUID']." is marked deleted on server.");
					$res=null;
				}else if( $record['SYNC_UTC'] >= $utc  ){
					$rcid = $rcid['fm_recid'];
					$res=$db->updateByRecID($layout, $record, $rcid);
					// $res=$db->update($layout,$record,"WHERE SYNC_UUID='".$record['SYNC_UUID']."'");
				}else{
					$db->log("Push Record","Record ".$record['SYNC_UUID']." is older than server data.");
					$res=null;
				}
			}
			
			if( $db->isError($res) ){
				$db->log("Push Record","Record ".$record['SYNC_UUID']." push fail.");
				continue;
			}
		}
		
	}
	
	$res = $db->runScript("FMSync_ClientSession",$FMSync_RegisterSessionScript,$ClientID,"0");
	if( $db->isError($res) ) die($db->getErrInfo($res));
	
	echo "DONE";
?>