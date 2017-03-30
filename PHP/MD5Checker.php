<?php
/*******************************
 * @File : MD5Checker.php
 *
 * FileMaker Just Another Sync solution -- by darkk6
 *
 * Simple way to sync data with server (Partially similar to FMEasySync),
 * But use PHP API instead of external data source.
 * So that you can use thing tool with Runtime Solution.
 *
 * 負責處理 Client 詢問 Server Container MD5 的部分
 *
 * @Author darkk6 (LuChun Pan)
 * @Version 1.1.0
 *
 * @License GPLv3
 *
 ********************************/
/*
	傳入資料格式為每行一筆資料 , 每筆資料行是以 \r 為換行分隔
	
		rec_id </col> tableName </col> fieldName </col> repetition </col> Sync_uuid </col> MD5
		
	傳回不需要更新的 rec_id , 以點 . 分隔 , 前後都會再加上 . , 如 : .2.4.7.14.
*/
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
	set_time_limit(0);
	
	$_request_body = file_get_contents('php://input');
	$_request_body = urldecode($_request_body);
	$dataArray = explode("\r",$_request_body);
	
	/*
		配置成
			array [tabelName] [UUID] [fieldName] [repetition] = array( md5 , recid );
			
		另外有一個用來撈資料用的
			tableName1 : [ UUID1 , UUID2... ],
			tableName2 : [ UUID1 , UUID2... ]...
			
	*/
	$queryTable = array();
	$uuidList = array();
	foreach($dataArray as $row){
		$data = explode("</col>",$row);
		$rec_id = $data[0]; $table = $data[1]; $field = "SYNC_MD5_".$data[2];	//欄位名稱加上 SYNC_MD5_ 加速底下搜尋
		$repeat = $data[3]; $uuid = $data[4]; $md5 = $data[5];
		// table
		if( !array_key_exists($table,$queryTable) ){
			//$uuidList 和 $queryTable 是同步增加，所以判斷一個即可
			$uuidList[ $table ] = array();
			$queryTable[ $table ] = array();
		}
		//UUID
		if( !array_key_exists($uuid,$queryTable[$table]) )
			$queryTable[$table][$uuid] = array();
		
		// Field
		if( !array_key_exists($field,$queryTable[$table][$uuid]) )
			$queryTable[$table][$uuid][$field] = array();
		
		$queryTable[$table][$uuid][$field][$repeat] = array($md5,$rec_id) ;
		$uuidList[$table][] = $uuid;
	}
	

	
	require_once("config/Config.php");
	require_once("EzFMDB/EzFMDB.php");
	$db = new EzFMDB($FMSync_HOST,$FMSync_DB,$FMSync_USER,$FMSync_PSWD);
	
	$result=array();
	foreach($uuidList as $table => $uuids){
		
		$where = array();
		foreach($uuids as $uuid)
			$where[] = "WHERE SYNC_UUID = ".$uuid;
		
		// 取出這個 table 中的欄位，並找出 SYNC_MD5_xxx 的項目
		$res = $db->getFields($table);
		if( $db->isError($res) ){
			$db->log("Fetch fields","Fetching fields error : ".$res);
			continue;
		}
		// 這邊就不檢查對應的欄位是不是 Container 了，如果愛惡搞就自己承擔XD
		$selects=array();
		$selects[] = "SYNC_UUID";
		foreach($res as $field){
			if( preg_match("/^SYNC_MD5_/",$field['name']) ) $selects[] = $field['name'];
		}
		
		// 從這些 table 取出所有指定 UUID 的 SYNC_MD5_ 開頭欄位
		$param = array_merge( array($table,$selects), $where );
		$res = call_user_func_array(array($db,'select'),$param);
		if( $db->isError($res) || ( is_array($res) && count($res)==0 ) ){
			$db->log("Fetch Record","Fetching record error : ".$res);
			continue;
		}
		
		
		foreach($res as $record){
			$theUUID = $record["SYNC_UUID"];
			foreach($record as $field => $v){
				if( !preg_match("/^SYNC_MD5_/",$field) ) continue;
				
				//強制變成 array 底下就不用區分了
				$values = $v;
				if( !is_array($v) ) $values = array($v);
				
				
				foreach($values as $rep => $svrMD5){
					// NOTE , select 出來的 $rep 是 0-based , 但 $queryTable 由 client 傳來是 1-based
					if( isset( $queryTable[$table][$theUUID][$field][$rep+1] ) ){
						//符合傳來的資訊內容，取出 md5 判斷
						$clientMD5 = $queryTable[$table][$theUUID][$field][$rep+1][0];
						$rec_id = $queryTable[$table][$theUUID][$field][$rep+1][1];
						//若比對結果相等，放入結果，代表不需更新此項目
						if( $svrMD5 == $clientMD5 ) $result[] = $rec_id;
					}
				}
			}
		}
	}
	
	echo ".".implode(".",$result).".";
?>