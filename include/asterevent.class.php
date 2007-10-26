<?
/*******************************************************************************
* asterevent.class.php
* asterisk事件处理类
* asterisk events class

* Public Functions List
			
			checkNewCall			检查是否有新的电话
			checkCallStatus			检查通话的状态
			checkExtensionStatus	读取所有分机的状态, 并返回HTML结果
			events					日志记录

* Private Functions List

			listStatus				生成列表格式的分机状态代码
			tableStatus				生成表格世的分机状态代码
			getEvents				从数据库中读取事件
			events					日志记录函数
			getCallerID				用于外部来电时获取主叫号码
			getInfoBySrcID			用于呼出时获取主叫号码
			getInfoByDescID			未使用
			checkLink				检查呼叫是否连接
			checkHangup				检查呼叫是否挂断
			checkIncoming			检查是否有来电
			checkDialout			检查是否有向外的呼叫

* Revision 0.041  2007/10/26 13:46:00  modified by solo
* Desc: 
* 描述: 修改了 listStatus和tableStatus, 增加了点击分机上的列表拨号功能


* Revision 0.045  2007/10/15 10:55:00  modified by solo
* Desc: 
* 描述: 修改了 checkIncoming , checkLink , checkHangup , checkDialout 函数的SQL语句, 修改为仅查询最近10秒的结果

* Revision 0.044  2007/09/12 10:55:00  modified by solo
* Desc: 
* 描述: 修改了 checkIncoming 和 checkDialout 函数的SQL语句


* Revision 0.044  2007/09/12 10:55:00  modified by solo
* Desc: 
* 描述: 修改了 getInfoBySrcID 函数 将数据集的排序顺序改为ASC

* Revision 0.044  2007/09/11 10:55:00  modified by solo
* Desc: fix extension status bug when user switch between user interface and admin interface
* 描述: 修正了分机状态显示的bug(如果用户在管理员界面和用户界面之间切换，分级状态列表会出现问题)

* Revision 0.044  2007/09/11 10:55:00  modified by solo
* Desc: add some comments
* 描述: 增加了一些注释信息


********************************************************************************/

/** \brief asterEvent Class
*

*
* @author	Solo Fu <solo.fu@gmail.com>
* @version	1.0
* @date		13 Auguest 2007
*/

class asterEvent extends PEAR
{

/*
	check if there's a new call, could be incoming or dial out
	@param	$curid					(int)		only check data after index(curid)
	@param	$exten					(string)	only check data about extension
	return	$call					(array)	
			$call['status']			(string)	'','incoming','dialout'
			$call['curid']			(int)		current id
			$call['callerid']		(string)	caller id/callee id
			$call['uniqueid']		(string)	uniqueid for the new call
*/
	function checkNewCall($curid,$exten){

		$call =& asterEvent::checkIncoming($curid,$exten);

		if ($call['status'] == 'incoming' && $call['callerid'] != '' ){
//		if ($call['status'] == 'incoming' ){
			return $call;
		}

		$call =& asterEvent::checkDialout($curid,$exten);

		return $call;
	}

/*
	check call status
	@param	$curid					(int)		only check data after index(curid)
	@param	$uniqueid				(string)	check events which id is $uniqueid
	return	$call					(array)	
			$call['status']			(string)	'','hangup','link'
			$call['curid']			(int)		current id
			$call['callerChannel']	(string)	caller channel (if status is link)
			$call['calleeChannel']	(string)	callee channel (if status is link)
*/
	function checkCallStatus($curid,$uniqueid){
		$call =& asterEvent::checkHangup($curid,$uniqueid);

		if ($call['status'] == 'hangup')
			return $call;

		$call =& asterEvent::checkLink($curid,$uniqueid);
		
	
		return $call;
	}

/*
	check call status
	@param	$curid					(int)		only check data after index(curid)
	@param	$type					(string)	list | table
	return	$html					(string)	HTML code from extension status
*/

	function checkExtensionStatus($curid, $type = 'list'){
		if ($type == 'list')
			$_SESSION['curuser']['extensions_session'] = $_SESSION['curuser']['extensions'];
		else
			$_SESSION['curuser']['extensions_session'] = array();

		$events =& asterEvent::getEvents($curid);
		if (!isset($_SESSION['sipstatus']))
			$status = array();
		else
			$status = $_SESSION['sipstatus'];

		if (!isset($_SESSION['curuser']['extensions_session']) or $_SESSION['curuser']['extensions_session'] == '')
			$phones = array();
		else
			$phones = $_SESSION['curuser']['extensions_session'];

		$events =& asterEvent::getEvents($curid);
		while ($events->fetchInto($list)) {
			$data  = trim($list['event']);
			list($event,$event_val,$ev,$priv,$priv_val,$pv,$chan,$chan_val,$cv,$stat,$stat_val,$sv,$extra) = split(" ", $data, 13);
			if (strtolower(substr($chan_val,0,3)) != "sip") continue;
			if (substr($event_val,0,10) == "PeerStatus") {
				if (!in_array($chan_val,$phones)) $phones[] = $chan_val;
				if (substr($stat_val,0,11) == "Unreachable")  { $status[$chan_val] = 2; continue; }
				if (substr($stat_val,0,12) == "Unregistered") { $status[$chan_val] = 2; continue; }
				if (substr($stat_val,0,9)  == "Reachable")    {
					if ($status[$chan_val] == 1) continue;
					$status[$chan_val] = 0;
					continue;
				}
				if (substr($stat_val,0,12) == "Registered")   { 
					if ($status[$chan_val] == 1) continue; 
					$status[$chan_val] = 0; 
					continue;
				}
				if (!isset($status[$chan_val])) $status[$chan_val] = 0;
				continue;
			} 
			if (substr($event_val,0,10) == "Newchannel") {
				$peer_val = split("-",$chan_val);
				if (!in_array($peer_val[0],$phones)) $phones[] = $peer_val[0];
				$status[$peer_val[0]] = 1;
				continue;
			} 
			if (substr($event_val,0,8) == "Newstate") {
				$peer_val = split("-",$chan_val);
				if (!in_array($peer_val[0],$phones)) $phones[] = $peer_val[0];
				$status[$peer_val[0]] = 1;
				continue;
			} 
			if (substr($event_val,0,6) == "Hangup") {
				$peer_val = split("-",$chan_val);
				if (!in_array($peer_val[0],$phones)) $phones[] = $peer_val[0];
				$status[$peer_val[0]] = 0;
				continue;
		   } 
		} 
		
		if ($type == 'list'){
			if (!isset($_SESSION['curuser']['extensions']) or $_SESSION['curuser']['extensions'] == '')
				$phones = array();
			else
				$phones = $_SESSION['curuser']['extensions'];
			$action =& asterEvent::listStatus($phones,$status);
		}else{
			$_SESSION['curuser']['extensions_session'] = $phones;
			$action =& asterEvent::tableStatus($phones,$status);
		}

		$_SESSION['sipstatus'] = $status;

		$html .= $action;
		return $html;
	}

	function &tableStatus($phones,$status){
		//print_r($phones);
		$action .= '<table width="100%" cellpadding=2 cellspacing=2 border=0>';
		$action .= '<tr>';
		foreach ($phones as $key => $value) {
			//$value = "SIP/".$value;
			if ( (($key %  6) == 0) && ($key != 0) ) $action .= "</tr><tr>";
			$action .= "<td align=center><button onclick=\"xajax_dial ('".substr($value,4)."','callee');return false;\" name='" . substr($value,4) . "' ";
			if (isset($status[$value])) {
				if ($status[$value] == 2) {
					$action .= "  id='ButtonU'>\n";
				}
				else {
					if ($status[$value] == 1) {
						$action .= "  id='ButtonR'>\n";
					}
					else {
						$action .= "  id='ButtonG'>\n";
					}
				}
			}
			else {
				$action .= "  id='ButtonB'>\n";
			}
			$action .= strtoupper(substr($value,4));
			$action .= "</button>\n";

			$action .=  "</td>\n";
		}
		$action .= '</tr></table><br>';
		return $action;
	}

	function &listStatus($phones,$status){
		$action .= '<table width="100%" cellpadding=2 cellspacing=2 border=0>';
		foreach ($phones as $key => $value) {
			if (!strstr($value,'SIP/'))
				$value = "SIP/".$value;
			$action .= "<tr><td align=center><button onclick=\"xajax_dial ('".substr($value,4)."','callee');return false;\" name='" . substr($value,4)."'";
			if (isset($status[$value])) {
				if ($status[$value] == 2) {
					$action .= "  id='ButtonU'>\n";
				}
				else {
					if ($status[$value] == 1) {
						$action .= "  id='ButtonR'>\n";
					}
					else {
						$action .= "  id='ButtonG'>\n";
					}
				}
			}
			else {
				$action .= "  id='ButtonB'>\n";
			}
			$action .= $value;
			$action .= "</button>\n";

			$action .=  "</td></tr>\n";
		 }
		 $action .= '</table><br>';
		return $action;
	}

/*
	get events from database
	@param	$curid					(int)		only check data after index(curid)
	return	$res					(array)	
*/

	function &getEvents($curid){
		global $db;
		$query = "SELECT * FROM events WHERE id > $curid order by id";
		asterEvent::events($query);
		$res = $db->query($query);
		return $res;
	}

/*
	check if a call linked
	@param	$curid					(int)		only check data after index(curid)
	@param	$uniqueid				(string)	uniqueid for the current call
	return	$call					(array)	
			$call['status']			(string)	'', 'link'
			$call['curid']			(int)		current id
			$call['callerChannel']	(string)	caller channel
			$call['calleeChannel']	(string)	callee channel
*/

	function &checkLink($curid,$uniqueid){
		global $db;
		// SELECT "1997-12-31 23:59:59" + INTERVAL 1 SECOND; 

		$query = "SELECT * FROM events WHERE event LIKE 'Event: Link%' AND event LIKE '%" . $uniqueid. "%' AND id > $curid AND timestamp > (now()-INTERVAL 10 SECOND) order by id desc ";
		asterEvent::events($query);
		$res = $db->query($query);

		if ($res->fetchInto($list)) {
			$flds	= split("  ",$list['event']);
//			print_r($flds);
//			exit;
			$call['callerChannel'] = trim(substr($flds[2],9));
			$call['callerChannel'] = split(",",$call['callerChannel']);
			$call['callerChannel'] = $call['callerChannel'][0];

			$call['calleeChannel'] = trim(substr($flds[3],9));
			//检查是否是local事件

			$call['status'] = 'link';
			$call['curid'] = $list['id'];

			//检查是否是local事件
			//如果是local, 返回状态为空闲
			//if (strstr($call['callerChannel'],'Local')){
			//	//print_r($call['callerChannel']);
			//	$call['status'] = '';
			//}

		} else
			$call['status'] = '';

		return $call;
	}

/*
	check if a call hangup
	@param	$curid					(int)		only check data after index(curid)
	@param	$uniqueid				(string)	uniqueid for the current call
	return	$call					(array)	
			$call['status']			(string)	'','hangup'
			$call['curid']			(int)		current id
*/

	function &checkHangup($curid,$uniqueid){
		global $db;
		$query = "SELECT * FROM events WHERE event LIKE '%Hangup%' AND event LIKE '%" . $uniqueid . "%' AND timestamp > (now()-INTERVAL 10 SECOND) AND id> $curid order by id desc ";
		asterEvent::events($query);
		$res = $db->query($query);

		if ($res->fetchInto($list)) {
			$flds	= split("  ",$list['event']);
			$call['status'] = 'hangup';
			$call['curid'] = $list['id'];
		} else
			$call['status'] = '';

		return $call;
	}

/*
	check if there's a new incoming call
	@param	$curid					(int)		only check data after index(curid)
	@param	$exten					(string)	only check data about extension
	return	$call					(array)	
			$call['status']			(string)	'','incoming'
			$call['curid']			(int)		current id
			$call['callerid']		(string)	caller id/callee id
			$call['uniqueid']		(string)	uniqueid for the new call
*/

	function &checkIncoming($curid,$exten){
		global $db;

		$query = "SELECT * FROM events WHERE (event LIKE 'Event: New% % Channel: %".$exten."% % State: Ring%' ) AND timestamp > (now()-INTERVAL 10 SECOND) AND id > " . $curid . " order by id desc";

		asterEvent::events($query);
		$res = $db->query($query);

		if ($res->fetchInto($list)) {
			$id        = $list['id'];
			$timestamp = $list['timestamp'];
			$event     = $list['event'];
			$flds      = split("  ",$event);
			$c         = count($flds);
			$callerid  = '';
			$transferid= '';

			if ($flds[3] == 'State: Ringing'){
				for($i=4;$i<$c;++$i) {
					if (strstr($flds[$i],"CallerID:"))	
						$transferid = substr($flds[$i],9);

					if (strstr($flds[$i],"Uniqueid:")){	
							$uniqueid = substr($flds[$i],9);
							$callerid =& asterEvent::getCallerID($uniqueid);
					}
				}
			}
			
			if ($callerid == '')	//	if $callerid is null, the call should be transfered
				$callerid = $transferid;

			if ($id > $curid) 
				$curid = $id;

			$call['status'] = 'incoming';
			$call['callerid'] = trim($callerid);
			$call['uniqueid'] = trim($uniqueid);
			$call['curid'] = trim($curid);
		} else
			$call['status'] = '';

		return $call;
	}

/*
	check if there's a new dial out
	@param	$curid					(int)		only check data after index(curid)
	@param	$exten					(string)	only check data about extension
	return	$call					(array)	
			$call['status']			(string)	'','incoming'
			$call['curid']			(int)		current id
			$call['callerid']		(string)	caller id/callee id
			$call['uniqueid']		(string)	uniqueid for the new call
*/

	function &checkDialout($curid,$exten){
		global $db;
		$query = "SELECT * FROM events WHERE event LIKE 'Event: Dial% Source: %".$exten."%' AND id > " . $curid . " AND timestamp > (now()-INTERVAL 10 SECOND) order by id desc";	

		asterEvent::events($query);

		$res = $db->query($query);
		if ($res->fetchInto($list)) {
			$id        = $list['id'];
			$timestamp = $list['timestamp'];
			$event     = $list['event'];
			$flds      = split("  ",$event);
			$callerid  = '';


			if ($flds[0] == 'Event: Dial'){
				$SrcUniqueID = trim(substr($flds[6],12));
				$DescUniqueID = trim(substr($flds[7],13));

				$srcInfo = & asterEvent::getInfoBySrcID($SrcUniqueID);
				$callerid = $srcInfo['Extension'] ;
			}

			if ($id > $curid) 
				$curid = $id;

			$call['status'] = 'dialout';
			$call['callerid'] = trim($callerid);
			$call['uniqueid'] = $SrcUniqueID;
			$call['curid'] = trim($curid);
		} else
			$call['status'] = '';

		return $call;
	}

/*
	get more information from events table by DescUniqueID
	@param	$DescUniqueID			(string)	DescUniqueID field in manager event
	return	$call					(array)	
			$call['status']			(string)	'','found'
			$call['Extension']		(string)	extension which unique id is $DescUniqueID
			$call['Channel']		(string)	channel which unique id is $DescUniqueID
*/

	function &getInfoByDescID($DescUniqueID){
		global $db;
		$DescUniqueID = trim($DescUniqueID);
		$query  = "SELECT * FROM events WHERE event LIKE '%Uniqueid: $DescUniqueID%' AND event LIKE 'Event: Newcallerid%' ORDER BY id DESC";
		asterEvent::events($query);
		$res = $db->query($query);
		if ($res->fetchInto($list)){
			$event = $list['event'];
			$flds = split("  ",$event);

			foreach ($flds as $myFld) {
				if (strstr($myFld,"CallerID:")){	
					$call['Extension'] = substr($myFld,9);
				} 
				if (strstr($myFld,"Channel:")){	
					$call['Channel'] = substr($myFld,8);
				} 

			}
			$call['status'] = 'found';
		} else
			$call['status'] = '';

		return $call;
	}

/*
	get more information from events table by SrcUniqueID
	@param	$SrcUniqueID			(string)	SrcUniqueID field in manager event
	return	$call					(array)	
			$call['status']			(string)	'','found'
			$call['Extension']		(string)	extension which unique id is $SrcUniqueID
			$call['Channel']		(string)	channel which unique id is $SrcUniqueID
*/

	function &getInfoBySrcID($SrcUniqueID){
		global $db;
		$SrcUniqueID = trim($SrcUniqueID);
		$query  = "SELECT * FROM events WHERE event LIKE '%Uniqueid: $SrcUniqueID%' AND event LIKE 'Event: Newexten%' ORDER BY id ASC";
		asterEvent::events($query);
		$res = $db->query($query);
		if ($res->fetchInto($list)){
			$event = $list['event'];
			$flds = split("  ",$event);

			foreach ($flds as $myFld) {
				if (strstr($myFld,"Extension:")){	
					$call['Extension'] = substr($myFld,10);
				} 
				if (strstr($myFld,"Channel:")){	
					$call['Channel'] = substr($myFld,8);
				} 

			}
			$call['status'] = 'found';
		} else
			$call['status'] = '';

		return $call;
	}

/*
	get callerid for incoming calls
	@param	$uniqueid				(string)	
	return	$callerid				(string)	
*/

	function &getCallerID($uniqueid){
		global $db;
		$uniqueid = trim($uniqueid);
		$query  = "SELECT * FROM events WHERE event LIKE '%DestUniqueID: $uniqueid%' ORDER BY id DESC";
		$res = $db->query($query);

		if ($res->fetchInto($list)){
			$event = $list['event'];
			$flds = split("  ",$event);

			foreach ($flds as $myFld) {
				if (strstr($myFld,"CallerID:")){	
					return substr($myFld,9);
				} 
			}
		}

		return 0;
	}

/*
	for log
	@param	$events					(string)	things want to be logged
	return	null								nothing to be returned
*/
	function events($event = null){
		if(LOG_ENABLED){
			$now = date("Y-M-d H:i:s");
   		
			$fd = fopen (FILE_LOG,'a');
			$log = $now." ".$_SERVER["REMOTE_ADDR"] ." - $event \n";
	   		fwrite($fd,$log);
   			fclose($fd);
		}
	}

}
?>