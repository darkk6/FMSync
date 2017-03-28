<?php
/*******************************
 * @File : SendUpdatePayload.php
 *
 * FileMaker Just Another Sync solution -- by darkk6
 *
 * Simple way to sync data with server (Partially similar to FMEasySync),
 * But use PHP API instead of external data source.
 * So that you can use thing tool with Runtime Solution.
 *
 * 負責處理 Client 要求 Server 要更新的資料內容
 *
 * @Author darkk6 (LuChun Pan)
 * @Version 1.0.0
 *
 * @License GPLv3
 *
 ********************************/
	/*
		請求資料格式和 ReciveNormalPayload 類似 (每行分隔為 <fmsbr/> 底下將省略 )
		每個 table 資料有三行，
		
			第一行為 Table(layout) 名稱
			第二行為要取得的 FieldList (repetition 只要有第一個的名稱即可) , 以 </field> 分隔
			第三行為要取得的 UUID list , 以逗號分隔(因為 UUID 不會有逗號)
		
			<table>TableName
			field_1</field>field_2</field>field_3</field>.....
			UUID_1,UUID_2,UUID_3......
			
			
		傳回資料為：(每一行結尾都是<fmsbr/>底下將省略)
			<fmsync_ok/>(此行後方不接<fmsbr/>)
			tableName
			SYNC_UUID</field>field_1</field>field_2</field>field_3</field>...	(註：若為 repetition field , 只會呈現名稱， FM 腳本要自己處理 repetetion)
			RECORD_UUID</col>val_for_field1</col>val_for_field2</col>val_for_field3</col>....	( 第一個必為 UUID , 以便對照 )
			RECORD_UUID</col>val_for_field1</col>val_for_field2</col>val_for_field3</col>....
			</table> (這邊後面不加<fmsbr/>)
			
			FM 拆解時先用 </table> 拆解，再用 <fmsbr/>
		
	*/
	set_time_limit(0);
	$_request_body = file_get_contents('php://input');
	$_request_body = urldecode($_request_body);
	
	// $_request_body = "<table>SYNC_Data_Table<fmsbr/>idx</field>textData</field>binary</field>repText<fmsbr/>935421DE-B6DE-424D-A5BE-71D112BF1594,1B95A8D6-501F-0040-A129-1F75DCA573FE,41B95F6E-E624-024D-A0A2-1B54C44D7B44";
	// $_request_body = "<table>SYNC_Data_Table<fmsbr/>idx</field>textData</field>binary</field>repText[1]</field>repText[2]</field>repText[3]<fmsbr/>935421DE-B6DE-424D-A5BE-71D112BF1594";
	
	$searchData=explode("<fmsbr/>",$_request_body);
	/*
		將資料配置成
		QueryData = [
			{
				table : "Table Name",
				fields : [ "field_1", "field_2", "field_3", ... ],
				UUIDs : [ "UUID1" , "UUID2"..... ] 
			},.....
		]
	*/
	$QueryData = array();
	$lastTable = null;
	$nextIsField = false;
	$lastTableArr = array();
	foreach($searchData as $line){
		if( strlen($line)<=0 ) continue;
		
		if( preg_match("/^<table>/",$line) ){
			$lastTable = preg_replace("/<table>(.+)/","$1",$line);
			$lastTableArr = array( "table" => $lastTable , "fields" => array() , "UUIDs" => array() );
			$nextIsField = true;
			continue;
		}
		
		if( is_null($lastTable) ) continue;
		
		if($nextIsField){
			//取得欄位定義
			$tmp = explode("</field>",$line);
			foreach($tmp as $field){
				//這裡不需要 repetition 的 loop , 只要取得第一個的名稱即可
				$lastTableArr['fields'][] = $field;
			}
			$nextIsField = false;
		}else{
			//需要的 UUID 資料
			$uuids = explode(",",$line);
			$uuids = array_filter($uuids,"removeEmptyFunc");
			$lastTableArr['UUIDs'] = $uuids;
			$QueryData[] = $lastTableArr;
			$lastTable=null;
		}
	}
	
	require_once("config/Config.php");
	require_once("EzFMDB/EzFMDB.php");
	$db = new EzFMDB($FMSync_HOST,$FMSync_DB,$FMSync_USER,$FMSync_PSWD);
	
	/* 如有需要自行啟用 , Uncomment this is you need log file
		require_once("EzFMDB/util/FileLogger.php");
		$log = new FileLogger("log/update.log",false);
		$db->setDebug($log);
	*/
	$db->setCastResult(false);//因為 FileMaker 的 Number 超過了 php 的 int 上限
	
	$result = array();
	foreach($QueryData as $tables){
		
		$where = array();
		if( is_array($tables['UUIDs']) ){
			foreach($tables['UUIDs'] as $uuid)
				$where[] = "WHERE SYNC_UUID = ".$uuid;
		}
		if(count($where)>0){
			$param = array_merge( array($tables['table'],$tables['fields']) , $where );
			$res = call_user_func_array(array($db,'select'),$param);
			if( $db->isError($res) ){
				$db->log("Fetch Record","Fetching record error : ".$res);
				continue;
			}
			//配置結果陣列
			$tableData = array( 'fields' => null , 'values' => array() );
			foreach($res as $record){
				if( is_null($tableData['fields']) ){
					$fields = array();
					$fields[]  = 'SYNC_UUID';
					foreach( array_keys($record) as $field ){
						if($field=='fm_recid' || $field=='SYNC_UUID' ) continue;
						if( is_array($record[$field]) ){
							//有 repetition
							for($i=0;$i<count($record[$field]);$i++)
								$fields[] = $field;
						}else{
							$fields[] = $field;
						}
					}
					$tableData['fields'] = $fields;
				}
				
				$value=array();
				$skip = array();
				foreach($tableData['fields'] as $field){
					if( is_array($record[$field]) ){
						if( in_array($field,$skip) ) continue;
						$skip[] = $field;
						//有 repetition
						for($i=0;$i<count($record[$field]);$i++)
							$value[] = $record[$field][$i];
					}else{
						$value[] = $record[$field];
					}
				}
				$tableData['values'][] = $value;
				
			}
			
			$result[ $tables['table'] ] = $tableData;
		}
	}
	
	$output = array();
	foreach($result as $table => $data){
		$str = $table."<fmsbr/>";
		$str.= implode("</field>",$data['fields'])."<fmsbr/>";
		foreach($data['values'] as $val){
			$tmp = "";
			foreach($val as $data){
				$tmp.= $data."</col>";
			}
			$str.=$tmp."<fmsbr/>";
		}
		$output[] = $str;
	}
	echo "<fmsync_ok/>".implode("</table>",$output);
	
	
	function removeEmptyFunc($var){
		return strlen($var)>0;
	}
?>