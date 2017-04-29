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
 * @Version 1.2.0
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
			SYNC_UUID</field>SYNC_DELETE</field>field_1</field>field_2</field>field_3</field>...	(註：若為 repetition field , 只會呈現名稱， FM 腳本要自己處理 repetetion)
			RECORD_UUID</col>0</col>val_for_field1</col>val_for_field2</col>val_for_field3</col>....	( 第一個必為 UUID , 第二個必為 DELETE mark, 以便對照 )
			RECORD_UUID</col>1</col>val_for_field1</col>val_for_field2</col>val_for_field3</col>....
			</table> (這邊後面不加<fmsbr/>)
			
			FM 拆解時先用 </table> 拆解，再用 <fmsbr/>
		
		若傳回 Container URL 時，會順便傳回目前 Server 的 MD5 , 並以 <fmsmd5/> 隔開 ,例： /fmi/xml/cnt.....<fmsmd5/>424681E0A98B940CA31221E563C246FF
		若該 Container 欄位沒給予 MD5 , 則會傳回 <fmsnomd5/> , 例 : /fmi/xml/cnt.....<fmsmd5/><fmsnomd5/>
		若該欄位沒有資料時，應該只會傳回 <fmsmd5/> (因為 URL="" , MD5="")，交給 client 自行判斷是否要求更新
		
	*/
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
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
	$db->setSkipEscapeCRLF(true,true);
	
	
	$result = array();
	foreach($QueryData as $tables){
		
		$hasMD5BinFieldList=array();//紀錄那些 Container field 有 SYNC_MD5_ 欄位
		//---- 先取得所有欄位名稱，找出是否有 SYNC_MD5_
			$res = $db->getFields($tables['table']);
			if( $db->isError($res) ){
				$db->log("Fetch fields","Fetching fields error : ".$res);
				continue;
			}
			$binFieldList=array();
			$md5NameList=array();
			foreach($res as $field){
				if( $field['type']=="container" ) $binFieldList[] = $field['name'];
				else if( preg_match("/^SYNC_MD5_/",$field['name']) ) $md5NameList[] = $field['name'];
			}
			//取出所有 container 欄位之後，尋找是否有 SYNC_MD5_xxx
			if( count($binFieldList)>0 ){
				foreach($binFieldList as $field){
					if( in_array("SYNC_MD5_".$field,$md5NameList) )
						$hasMD5BinFieldList[] = $field;
				}
			}
		//-----------------
			//如果 "要取得的欄位"($tables['fields'] ) 裡面含有 hasMD5BinFieldList 所儲存的內容，則要加入 SYNC_MD5_xxx 
			//NOTE : 從 Client 端送來的 Field 不會包含 SYNC_CLIENTID , SYNC_MD5_xxx  (有過濾)
			$appendField = array();
			foreach($tables['fields'] as $field){
				if( in_array($field , $hasMD5BinFieldList) ){//如果有指定要這個資料 , 將 SYNC_MD5_xxx 加入
					$appendField[] = "SYNC_MD5_".$field;
				}
			}
			
			$realSelectFields = $tables['fields'];
			if( count($appendField)>0 )
				$realSelectFields = array_merge( $tables['fields'] , $appendField);
			
		//-----------------
		
		//開始取得此 table 資料
		$where = array();
		if( is_array($tables['UUIDs']) ){
			foreach($tables['UUIDs'] as $uuid)
				$where[] = "WHERE SYNC_UUID = ".$uuid;
		}
		if(count($where)>0){
			//上面有判斷並處理實際要 Select 的欄位(若沒有新增則和 $tables['fields'] 相同)
			$param = array_merge( array($tables['table'],$realSelectFields) , $where );
			$res = call_user_func_array(array($db,'select'),$param);
			if( $db->isError($res) || ( is_array($res) && count($res)==0 ) ){
				$db->log("Fetch Record","Fetching record error : ".$res);
				continue;
			}
			//配置結果陣列
			$tableData = array( 'fields' => null , 'values' => array() );
			foreach($res as $record){
				if( is_null($tableData['fields']) ){
					$fields = array();
					$fields[]  = 'SYNC_UUID';
					$fields[]  = 'SYNC_DELETE';
					foreach( array_keys($record) as $field ){
						if( in_array($field, array('fm_recid','SYNC_UUID','SYNC_DELETE') ) ) continue;//跳過欄位(fm_recid 不需要 , 其他的要改動順序)
						if( preg_match("/^SYNC_MD5_/",$field) ) continue;// SYNC_MD5 的也跳過
						
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
				//這裡只會根據上面配置的 $tableData['fields'] (非 select 出來的) 欄位名稱做歷遍，因此不會有 SYNC_MD5 系列
				//$record 是 select 出來的資料，會包含 SYNC_MD5 系列
				foreach($tableData['fields'] as $field){
					//如果存在 $hasMD5BinFieldList 中，代表要傳回 MD5 (代表一定是 container)
					$isContainer = in_array($field,$binFieldList);
					$needCheckMD5 = in_array($field,$hasMD5BinFieldList);
					$binMD5 = "";
					
					if( is_array($record[$field]) ){
						//有 repetition
						if( in_array($field,$skip) ) continue; //找到第一個就會把所有 repetition 列出，所以之後遇到就跳過
						$skip[] = $field;
						$md5RepCount = count($record["SYNC_MD5_".$field]);
						for($i=0;$i<count($record[$field]);$i++){
							//如果需要判斷 MD5 , 且目前的 repIdx 在範圍內 , 就要傳回這個的 MD5 讓 client 判斷
							if( $needCheckMD5 && $i<$md5RepCount ){
								$binMD5 = "<fmsmd5/>".$record["SYNC_MD5_".$field][$i];
							}else if($isContainer){
								//有可能沒 SYNC_MD5_ 或者 repetition 沒那麼多 , 都傳回 "<fmsnomd5/>"
								$binMD5 = "<fmsmd5/><fmsnomd5/>";
							}
							//不是 container 時 $binMD5 是空字串
							$value[] = fixCRLF($record[$field][$i]).$binMD5;
						}
					}else{
						if( $needCheckMD5 ){
							$binMD5 = "<fmsmd5/>".$record["SYNC_MD5_".$field];
						}else if($isContainer){
							//沒 SYNC_MD5_ 傳回 "<fmsnomd5/>"
							$binMD5 = "<fmsmd5/><fmsnomd5/>";
						}
						//不是 container 時 $binMD5 是空字串
						$value[] = fixCRLF($record[$field]).$binMD5;
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
	function fixCRLF($str){
		return preg_replace("/[\r\n]/","<fmcrlf/>",$str);
	}
?>