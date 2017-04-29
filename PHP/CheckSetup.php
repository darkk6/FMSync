<?php
/*******************************
 * @File : CheckSetup.php
 *
 * FileMaker Just Another Sync solution -- by darkk6
 *
 * Simple way to sync data with server (Partially similar to FMEasySync),
 * But use PHP API instead of external data source.
 * So that you can use thing tool with Runtime Solution.
 *
 * 檢查 PHP 以及 Lib 訊息
 *
 * @Author darkk6 (LuChun Pan)
 * @Version 1.2.0
 *
 * @License GPLv3
 *
 ********************************/

	if( $_POST['m']==1 ){
		require_once("config/Config.php");
		require_once("EzFMDB/EzFMDB.php");
		$db = new EzFMDB($FMSync_HOST,$FMSync_DB,$FMSync_USER,$FMSync_PSWD."QQ");
		$res = $db->getLayouts();
		if( $db->isError($res) ) echo $db->getErrInfo($res);
		else echo "測試連線成功";
		exit(0);
	}


	require_once("config/internal_config.php");
	
	$ic_check = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pg0KPHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJMYXllcl8xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDQyNi42NjcgNDI2LjY2NyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNDI2LjY2NyA0MjYuNjY3OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+DQo8cGF0aCBzdHlsZT0iZmlsbDojNkFDMjU5OyIgZD0iTTIxMy4zMzMsMEM5NS41MTgsMCwwLDk1LjUxNCwwLDIxMy4zMzNzOTUuNTE4LDIxMy4zMzMsMjEzLjMzMywyMTMuMzMzIGMxMTcuODI4LDAsMjEzLjMzMy05NS41MTQsMjEzLjMzMy0yMTMuMzMzUzMzMS4xNTcsMCwyMTMuMzMzLDB6IE0xNzQuMTk5LDMyMi45MThsLTkzLjkzNS05My45MzFsMzEuMzA5LTMxLjMwOWw2Mi42MjYsNjIuNjIyIGwxNDAuODk0LTE0MC44OThsMzEuMzA5LDMxLjMwOUwxNzQuMTk5LDMyMi45MTh6Ii8+DQo8L3N2Zz4g';
	$ic_cross = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pg0KPHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJMYXllcl8xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDQyNi42NjcgNDI2LjY2NyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNDI2LjY2NyA0MjYuNjY3OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+DQo8cGF0aCBzdHlsZT0iZmlsbDojRjA1MjI4OyIgZD0iTTIxMy4zMzMsMEM5NS41MTQsMCwwLDk1LjUxNCwwLDIxMy4zMzNzOTUuNTE0LDIxMy4zMzMsMjEzLjMzMywyMTMuMzMzIHMyMTMuMzMzLTk1LjUxNCwyMTMuMzMzLTIxMy4zMzNTMzMxLjE1MywwLDIxMy4zMzMsMHogTTMzMC45OTUsMjc2LjY4OWwtNTQuMzAyLDU0LjMwNmwtNjMuMzYtNjMuMzU2bC02My4zNiw2My4zNmwtNTQuMzAyLTU0LjMxIGw2My4zNTYtNjMuMzU2bC02My4zNTYtNjMuMzZsNTQuMzAyLTU0LjMwMmw2My4zNiw2My4zNTZsNjMuMzYtNjMuMzU2bDU0LjMwMiw1NC4zMDJsLTYzLjM1Niw2My4zNkwzMzAuOTk1LDI3Ni42ODl6Ii8+DQo8L3N2Zz4=';

	// PHP 版本
	$phpVer = PHP_VERSION_ID;
	
	//cURL module
	$hasCURL = extension_loaded("curl");
	
	// mb_string module
	$hasMBString = extension_loaded("mbstring");
	
	//檢查是否有 EzFMDB.php 檔案
	$hasEzFMDB = file_exists("./EzFMDB/EzFMDB.php");
	
	//檢查是否有 FileMaker.php 檔案
	$hasFMAPI = file_exists("./EzFMDB/fm_api/FileMaker.php");
	
	//取得 POST 最大 Size
	$postMaxSize = ini_get('post_max_size');
	
	$imgOK = '<img class="icon" src="'.$ic_check.'" />';
	$imgNO = '<img class="icon" src="'.$ic_cross.'" />';
	
	$canDoTest = $phpVer >= PHP_REQUIRE_VERSION && $hasCURL && $hasMBString && $hasEzFMDB && $hasFMAPI;
?>
<html>
	<head>
		<meta http-equiv="Content-Language" content="zh-tw" />
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>FMSync Server Checker</title>
		<style type="text/css">
			table , tr , td{
				border-collapse: collapse;
			}
			tr{
				border:1px solid gray;
			}
			tr.divider , tr.divider td{
				border:0px solid white !important;
			}
			td{
				padding:5px 10px;
			}
			td:first-child{
				border-right:1px solid gray;
			}
			
			.icon{
				width:24px;
				height:24px;
				vertical-align:middle;
			}
			.ok{
				color:#469737;
			}
			.no{
				color:#F05228;
			}
		</style>
	</head>
	<body>	
		<center>
			<h2>FMSync check page</h2>
			<table>
				<tr>
					<td style="font-weight:bold;">FMSync Version</td>
					<td style="font-weight:bold;" colspan=2><?php echo FMSYNC_VERSION; ?></td>
				</tr>
			<!----------->
				<tr>
					<td> PHP Version </td>
				<?php
					$title = ( $phpVer < PHP_REQUIRE_VERSION ? "需要 php 5.6+ (Need php 5.6+)" : "" );
					$img   = ( $phpVer < PHP_REQUIRE_VERSION ? $imgNO : $imgOK );
					$class = ( $phpVer < PHP_REQUIRE_VERSION ? "no" : "ok" );
					echo "<td title='$title'>$img</td>";
					echo "<td class='".$class."'>".phpversion()."</td>";
				?>
				</tr>
			<!----------->
				<tr>
					<td> cURL Module </td>
				<?php
					$img = ( $hasCURL ? $imgOK : $imgNO );
					$text = ( $hasCURL ? "<span class='ok'>已啟用 (Enabled)</span>" : "<span class='no'>未啟用 (Disabled)</span>" );
					echo "<td>$img</td>";
					echo "<td>$text</td>";
				?>
				</tr>
			<!----------->
				<tr>
					<td> mb_string Module </td>
				<?php
					$img = ( $hasMBString ? $imgOK : $imgNO );
					$text = ( $hasMBString ? "<span class='ok'>已啟用 (Enabled)</span>" : "<span class='no'>未啟用 (Disabled)</span>" );
					echo "<td>$img</td>";
					echo "<td>$text</td>";
				?>
				</tr>
			<!----------->
				<tr>
					<td> EzFMDB Lib </td>
				<?php
					$img = ( $hasEzFMDB ? $imgOK : $imgNO );
					$text = ( $hasEzFMDB ? "<span class='ok'>已確認 (Found)</span>" : "<span class='no'>不存在 (Not Found)</span>" );
					echo "<td>$img</td>";
					echo "<td>$text</td>";
				?>
				</tr>
			<!----------->
				<tr>
					<td> FileMaker API </td>
				<?php
					$img = ( $hasFMAPI ? $imgOK : $imgNO );
					$text = ( $hasFMAPI ? "<span class='ok'>已確認 (Found)</span>" : "<span class='no'>不存在 (Not Found)</span>" );
					echo "<td>$img</td>";
					echo "<td>$text</td>";
				?>
				</tr>
			<!----------->
			<tr class="divider"><td colspan=3 style="height:20px;"></td></tr>
			<!----------->
				<tr>
					<td> EzFMBD Version </td>
				<?php
					$text = "<font style='color:gray'>---</font>";
					if($hasEzFMDB && $hasFMAPI){
						require_once("EzFMDB/EzFMDB.php");
						$text = EzFMDB::VERSION;
						if( $text < EzFMDB_VERSION ){
							$canDoTest = false;
							$text = '<span class="no">'.$text.'</span>&nbsp;&nbsp;<font style="color:gray;font-size:0.8em">Need '.EzFMDB_VERSION.'</font>';
						}
					}
					echo "<td colspan=2>$text</td>";
				?>
				</tr>
			<!----------->
				<tr>
					<td> FM API Version </td>
				<?php
					$text = "<font style='color:gray'>---</font>";
					if($hasEzFMDB && $hasFMAPI){
						$text = FileMaker_Implementation::getAPIVersion();
					}
					echo "<td colspan=2>$text</td>";
				?>
				</tr>
			<!----------->
				<tr>
					<td> Max post size </td>
				<?php
					echo "<td colspan=2>$postMaxSize</td>";
				?>
				</tr>
			<!----------->
				<tr>
					<td> Connection Test </td>
				<?php
					if($canDoTest){
						$btn = '<button onClick="conTest();"> Try it. </button>';
					}else{
						$btn = '<button disabled> Fix up first </button>';
					}
					echo "<td id='btn' colspan=2>$btn</td>";
				?>
				</tr>
			</table>
			<div style="padding-top:20px;font-size:0.8em;">
				Icons are made by <a target="_blank" href="http://www.flaticon.com/authors/maxim-basinski">Maxim Basinski</a>
				from <a target="_blank" href="http://www.flaticon.com">flaticon</a> CC-BY
			</div>
		</center>
<?php if($canDoTest){ ?>
			<script type="text/javascript" src="http://code.jquery.com/jquery-latest.min.js"></script>
			<script type="text/javascript">
				function conTest(){
					if(!window.jQuery){
						alert("無法載入必要元件，請確認網路狀態是否正常");
						return;
					}
					$("#btn").html('測試中請稍候...');
					$.post("CheckSetup.php",{m:1},function(res){
						alert(res);
						$("#btn").html('<button onClick="conTest();"> Try it. </button>');
					});
				}
			</script>
<?php } ?>
	</body>
</html>