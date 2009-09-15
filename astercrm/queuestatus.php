<?php
/*******************************************************************************
* queuestatus.php
* 系统状态文件
* systerm status interface
* 功能描述
	 显示分机状态和正在进行的通话

* Function Desc


* javascript function:		
						showStatus				show sip extension status
						showChannelsInfo		show asterisk channels information
						init					initialize function after page loaded

* Revision 0.045  2007/10/18 17:55:00  last modified by solo
* Desc: page created
********************************************************************************/

require_once('queuestatus.common.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
	<head>
		<?php $xajax->printJavascript('include/'); ?>
		<meta http-equiv="Content-Language" content="utf-8" />
		<SCRIPT LANGUAGE="JavaScript">
		<!--
			var timerShowStatus,timerShowChannelsInfo;
			function showStatus(){
				xajax_showStatus();
				timerShowStatus = setTimeout("showStatus()", xajax.$('check_interval').value);
			}

			function init(){
				xajax_init();
				showStatus();
			}

			function  agentlogoff(agent){

			}

			function hangup(srcChan,dstChan){
				var callerChan = srcChan;
				var calleeChan = dstChan;
				xajax_hangup(callerChan);
				xajax_hangup(calleeChan);
			}
		//-->
		</SCRIPT>
<style>
.groups_queue table{
padding:3px;
margin:0 auto;
}
.groups_queue table th{
	background-color:#F6F2ED;
	border-top: 1px solid #EDE0CE;
	border-bottom: 1px solid #EDE0CE;
	color:#A88E68;
	text-align:right;
	height:26px;
	line-height:26px;
	
}
</style>
		<script language="JavaScript" src="js/astercrm.js"></script>

	<LINK href="skin/default/css/dragresize.css" type=text/css rel=stylesheet>
	<LINK href="skin/default/css/style.css" type=text/css rel=stylesheet>
	<LINK href="skin/default/css/dialer.css" type="text/css" rel="stylesheet" />

	</head>
	<body onload="init();">
		<div id="divNav"></div>
		<div id="AMIStatudDiv" name="AMIStatudDiv"></div>
		<br>
		<br>
		<div id="divStatus" align="center"></div>
		<div id="channels" align="left" class="groupsystem_channel"></div>
		<div id="divCopyright"></div>
		<input type="hidden" id="check_interval" name="check_interval" value="2000">
	</body>
</html>