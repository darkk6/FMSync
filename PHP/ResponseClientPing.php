<?php
/*******************************
 * @File : ResponseClientPing.php
 *
 * FileMaker Just Another Sync solution -- by darkk6
 *
 * Simple way to sync data with server (Partially similar to FMEasySync),
 * But use PHP API instead of external data source.
 * So that you can use thing tool with Runtime Solution.
 *
 * 負責處理回應 Client 的 Ping , 分為兩個功能，若 0:刪除過期資料並 Ping Server , 檢查並登記同步上限 , 1:只會再次檢查同步上限(會排除自己)
 *
 * @Author darkk6 (LuChun Pan)
 * @Version 1.2.0
 *
 * @License GPLv3
 *
 ********************************/
	require_once("config/internal_config.php");
	
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
	set_time_limit(0);
	$_request_body = file_get_contents('php://input');
	$_request_body=urldecode($_request_body);
	$params = explode("<fmsbr/>",$_request_body);
	$ClientID = $params[0];
	$type = intval($params[1]);
	$type = ($type==1 ? 1 : 0);
	
	//加入版本判斷，必須要版本完全相符才可以使用
	$FMSyncVer = $params[2];
	if( $FMSyncVer != FMSYNC_VERSION ) die('<fmsync_version/>'.FMSYNC_VERSION);
	
	$startTime = microtime(true);
	
	require_once("config/Config.php");
	require_once("EzFMDB/EzFMDB.php");
	$db = new EzFMDB($FMSync_HOST,$FMSync_DB,$FMSync_USER,$FMSync_PSWD);
	$db->setSkipEscapeCRLF(true,true);
	
	if($type==0){
		//將過期的 Session， Working 為 1 的設回 0 (避免有人不正常離開卡住)
		$time = fmTimestamp( time() - intval($FMSync_SessionTimeout) );
		if( $time===FALSE ) die("ServerSettingError");
		$res = $db->update("FMSync_ClientSession","working=0","WHERE working=1 AND utime < '$time'");
		if( $db->isError($res) ){
			$tmp = $db->getErrInfo($res,false);
			if($tmp['ErrCode']!=401){
				die("ServerError_".$res);
			}
		}
		
		//若有任何 Client Session 的 syncing = 1 但 working = 0 一定有問題，趁這個時候設回來
		$res = $db->update("FMSync_ClientSession","syncing=0","WHERE working=0 AND syncing=1");
		if( $db->isError($res) ){
			$tmp = $db->getErrInfo($res,false);
			if($tmp['ErrCode']!=401){
				die("ServerErr_".$res);
			}
		}
		//計算所有 working = 1 的 client 數量
		$res = $db->select("FMSync_ClientSession","","WHERE working=1");
		if( $db->isError($res) ){
			$tmp = $db->getErrInfo($res,false);
			if($tmp['ErrCode']!=401){
				die("ServerError_".$res);
			}else{
				$res=array();
			}
		}
		
		if( $FMSync_MaxClient > 0 ){
			if( count($res) >= $FMSync_MaxClient )
				die("<fmsync_wait/>");
		}
		
		//如果可以繼續，將此 Client 登記為 working
		$recid = -1;
		$res = $db->select("FMSync_ClientSession","","WHERE ClientID='$ClientID'");
		if( $db->isError($res) ){
			$tmp = $db->getErrInfo($res,false);
			if($tmp['ErrCode']!=401) die("ServerError-".$res);
		}else{
			$recid = $res[0]['fm_recid']; //有找到此 Client Session , 用 update
			if( !is_numeric($recid) ) $recid = -1;
		}
		
		if( $recid==-1 ){
			$res = $db->insert("FMSync_ClientSession", array( "ClientID"=>$ClientID , "syncing"=>0 , "working"=>1 ) );
		}else{
			$res = $db->updateByRecID("FMSync_ClientSession", "working=1", $recid);
		}
/**/}else{/****************************************************************************************/

		//計算所有 working = 1 的數量，如果第一筆資料
		$res = $db->select("FMSync_ClientSession","ClientID","WHERE working=1","ORDER BY utime ASC");
		if( $db->isError($res) ){
			$tmp = $db->getErrInfo($res,false);
			if($tmp['ErrCode']!=401){
				die("ServerError_".$res);
			}else{
				$res=array();
			}
		}
		
		if( $FMSync_MaxClient > 0 ){
			if( count($res) >= $FMSync_MaxClient ){
				//依序判斷在 $FMSync_MaxClient 內是否有自己，若有可以繼續
				$pass = false;
				for($i=0;$i<$FMSync_MaxClient;$i++){
					if( $res[$i]['ClientID']==$ClientID ){
						$pass=true;
						break;
					}
				}
			}
			
			if( !$pass ) die("<fmsync_wait/>");
		}
	}
	$delta = round(microtime(true) - $startTime,2);
	echo "<fmsync_ok/>".$delta;
?>