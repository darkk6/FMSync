<?php
/*******************************
 * @File : PrepareUpdateList.php
 *
 * FileMaker Just Another Sync solution -- by darkk6
 *
 * Simple way to sync data with server (Partially similar to FMEasySync),
 * But use PHP API instead of external data source.
 * So that you can use thing tool with Runtime Solution.
 *
 * 負責處理 Client 詢問 Server 有哪些資料需要更新(給Client)的 UUID List
 *
 * @Author darkk6 (LuChun Pan)
 * @Version 1.1.0
 *
 * @License GPLv3
 *
 ********************************/
	/*
		Client 跟 Server 取得需要更新的 UUID 有哪些
		
		透過自訂格式 POST , 每筆資料分隔為 <fmsbr/>
		
			1. ClientID (透過 Get(PersistentID) 取得 )，實質上並無意義，僅供參考用
			2. LAST_UTC , 上次更新的時間，只會搜尋 > 此時間的項目傳回給 Client
			3. Client 端目前的 UTC (給 SYNC_DELETE = 1 的使用)
			
			==== 之後開始為每一個 table 以及其要求資料 ====
			(後面會有<fmsbr/> , 底下都省略)
			</table>table(layout) 1 Name
			</key>Key 1 Name
			</val>Value 1
			</key>Key 1 Name
			</val>Value 1
			...
			...
			</table>table(layout) 2 Name <fmsbr/>
			
		代表，要找 table 1 , 若底下含有 key-value pair , 則代表只找指定資料 , 舉例：
			(後面會有<fmsbr/> , 底下都省略)
			</table>MyDataTable
			</key>name
			</val>==darkk6
			</key>age
			</val><10
			</table>AllDataTable
			</table>ThirdTable
			</key>status
			</val>==10
			
		意義：
			尋找 MyDataTable 時，只找 name ==darkk6 且 age <10 的資料 ( 找 SYNC_UTC > LAST_UTC )
			尋找 AllDataTable 時，沒有限制其他條件，只要時間條件符合就找出來
			尋找 ThirdTable 時，只找 status ==10 的資料
			
			要找的 table 必須都要寫出來，沒寫的就不會做尋找
			
		
		傳回資料格式為要寫回 FMSync 暫存表格的內容，以 \r 分隔每一筆資料，每筆資料為：
			
			TableName</col>UUID</col>UTC</col>
		
	*/
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
	set_time_limit(0);
	$_request_body = file_get_contents('php://input');
	$_request_body = urldecode($_request_body);
	
	$searchData=explode("<fmsbr/>",$_request_body);
	
	$ClientID = null;
	$lastUTC = -1;
	$clientUTC = -1;
	$request = array();
	$currentTable = null;
	$lastKey = null;
	foreach($searchData as $line){
		if( strlen($line)<=0 ) continue;
		//先取得前兩個必要資料
		if( is_null($ClientID) ){
			$ClientID = $line;
			continue;
		}
		if($lastUTC===-1){
			$lastUTC = $line;//不能轉 int , 因為 php 上限沒那麼大
			continue;
		}
		if($clientUTC===-1){
			$clientUTC = $line;//不能轉 int , 因為 php 上限沒那麼大
			continue;
		}
		
		if( preg_match("/^<\\/table>/",$line) ){
			$tableName = preg_replace("/<\\/table>(.+)/","$1",$line);
			if( !array_key_exists($tableName,$request) ) $request[$tableName] = array();
			$currentTable = $tableName;
			$lastKey = null;
			continue;
		}
		
		if( is_null($currentTable) ) continue;
		
		if( preg_match("/^<\\/key>/",$line) ){
			$lastKey = preg_replace("/<\\/key>(.+)/","$1",$line);
		}else if( preg_match("/^<\\/val>/",$line) ){
			if( is_null($lastKey) ) continue;
			$theVal = preg_replace("/<\\/val>(.+)/","$1",$line);
			$request[$currentTable][$lastKey] = $theVal;
			$lastKey = null;
		}
	}
	
	require_once("config/Config.php");
	require_once("EzFMDB/EzFMDB.php");
	$db = new EzFMDB($FMSync_HOST,$FMSync_DB,$FMSync_USER,$FMSync_PSWD);
	
	/* 如有需要自行啟用 , Uncomment this is you need log file
		require_once("EzFMDB/util/FileLogger.php");
		$log = new FileLogger("log/prepare.log",true);
		$db->setDebug($log);
	*/
	
	$db->setCastResult(false);//因為 FileMaker 的 Number 超過了 php 的 int 上限
	
	$queryResult=array();
	foreach($request as $layout => $data){
		$queryCondition = array();
		$queryConditionDel=array();
		if( is_array($data)){
			//此 Layout 有指定條件
			$queryCondition = $data;
			$queryConditionDel = $data;
		}
		//最後都必須要符合此條件
		$queryCondition["SYNC_UTC"] = ">".$lastUTC;
		$queryConditionDel["SYNC_DELETE"] = "==1";
		
		//也要找出所有 SYNC_DELETE 為 1 的，並將時間設為目前 Client 的時間，藉此達到告知 Client 必須要更新這筆資料
		$res = $db->select($layout,"SYNC_UUID , SYNC_UTC , SYNC_DELETE","WHERE",$queryCondition,"WHERE",$queryConditionDel);
		if( $db->isError($res) || ( is_array($res) && count($res)==0 ) ){
			$db->log("Fetch Record","Fetching record error : ".$res);
			continue;
		}
		foreach($res as $rec){
			$tmp = $layout."</col>".$rec['SYNC_UUID']."</col>";
			$tmp.= ( $rec['SYNC_DELETE']==1 ? $clientUTC : $rec['SYNC_UTC']  );
			$queryResult[] = $tmp;
		}
	}
	if( count($queryResult)<=0 ) echo "<empty/>";
	else echo "<fmsync_ok/>\r".implode("\r",$queryResult);
	
?>