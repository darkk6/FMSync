<?php
/* FMSync_Server 連線資訊 , Connection info to FMSync_Server */
	$FMSync_HOST = "http://localhost";
	$FMSync_DB = "The DataBase Name";
	$FMSync_USER = "admin";
	$FMSync_PSWD = "";
	
/* FMSync_Server 設定 , FMSync_Server setting */
	$FMSync_SessionTimeout = 300;	//每個工作 SESSION 最多存在多久 (秒) , Timout for every client working session.
	$FMSync_MaxClient = 1;			//同時最多同步連線數(建議 1 , 0 代表不限制) , Max clients at the same time. ( 1 is recommanded , 0 means unlimited )
	
/* 指定 FMSync_Server 上對應的腳本名稱 , Script name on FMSync_Server */
	$FMSync_ContainerBriderScript = "ContainerReceiver";	// 處理 Client 傳送 Binary 給 Server 的腳本 , Client push container data to server handler.
	$FMSync_RegisterSessionScript = "RegisterSession";		// 登記此 Client 正在同步的腳本 , Script name for register client is syncing
?>