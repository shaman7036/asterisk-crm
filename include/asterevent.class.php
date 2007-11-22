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
			getInfoByDestID			未使用
			checkLink				检查呼叫是否连接
			checkHangup				检查呼叫是否挂断
			checkIncoming			检查是否有来电
			checkDialout			检查是否有向外的呼叫

* Revision 0.0456  2007/11/7 14:45:00  modified by solo
* Desc: add chanspy triger on extension panel 

* Revision 0.0456  2007/11/1 11:54:00  modified by solo
* Desc: add callerid in extension status, when click the callerid, 
* it could show user information if it's stored before

* Revision 0.0456  2007/10/31 10:54:00  modified by solo
* Desc: return channel information when there's dialin or dialout events

* Revision 0.0456  2007/10/31 10:13:00  modified by solo
* Desc: replace DescUniqueID with DestUniqueID 

* Revision 0.0451  2007/10/26 13:46:00  modified by solo
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
	check if there's a new call for the extension, 
	could be incoming or dial out

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
			return $call;
		}
	
//		print_r($call);
		
		$call =& asterEvent::checkDialout($curid,$exten,$call['curid']);

//		print_r($call);
//		exit;
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
	check extension status
	@param	$curid					(int)		only check data after index(curid)
	@param	$type					(string)	list | table
	return	$html					(string)	HTML code for extension status
*/

	function checkExtensionStatus($curid, $type = 'list'){
		if ($type == 'list')
			$_SESSION['curuser']['extensions_session'] = $_SESSION['curuser']['extensions'];
		else
			$_SESSION['curuser']['extensions_session'] = array();

		$events =& asterEvent::getEvents($curid);
		if (!isset($_SESSION['sipstatus'])){
			$status = array();
			$callerid = array();
			$direction = array();
		}else{
			/*
			because there could be no full datas in the database
			we need to inherit status from last time this function get
			*/
			$status = $_SESSION['sipstatus'];
			$callerid = $_SESSION['callerid'];
			$direction = $_SESSION['direction'];
		}

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
				
				//get unique id
				//add by solo 2007-11-1
				$extra = split("  ", $extra);
				foreach ($extra as $temp){
					if (preg_match("/^Uniqueid:/",$temp)){
						$uniqueid = substr($temp,9);
						$callerid[$peer_val[0]] =& asterEvent::getCallerID($uniqueid);
						$direction[$peer_val[0]] = "dialin";
					}
				}

				if ($callerid[$peer_val[0]] == 0 ){	// it's a dial out
					$srcInfo = & asterEvent::getInfoBySrcID($uniqueid);
					$callerid[$peer_val[0]] = $srcInfo['Extension'];
					$direction[$peer_val[0]] = "dialout";
				}
				//**************************

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
				$callerid[$peer_val[0]] = "";
				continue;
		   } 
		} 

		if ($type == 'list'){
			if (!isset($_SESSION['curuser']['extensions']) or $_SESSION['curuser']['extensions'] == ''){
				$phones = array();
			}else{
				$phones = $_SESSION['curuser']['extensions'];
			}
			$action =& asterEvent::listStatus($phones,$status,$callerid,$direction);
		}else{
			$_SESSION['curuser']['extensions_session'] = $phones;
			$action =& asterEvent::tableStatus($phones,$status,$callerid,$direction);
		}

		$_SESSION['sipstatus'] = $status;
		$_SESSION['callerid'] = $callerid;
		$_SESSION['direction'] = $direction;

		$html .= $action;
		return $html;
	}
	
	/*
	for now this mode could be used in administror interface
	allow to spy extension
	but no click-to-call
	*/
	function &tableStatus($phones,$status,$callerid,$direction){
		//print_r($phones);
		$action .= '<table width="100%" cellpadding=2 cellspacing=2 border=0>';
		$action .= '<tr>';
		foreach ($phones as $key => $value) {
			//$value = "SIP/".$value;
			if ( (($key %  6) == 0) && ($key != 0) ) $action .= "</tr><tr>";
			$action .= "<td align=center ><br><button name='" . substr($value,4) . "' ";
			if (isset($status[$value])) {
				if ($status[$value] == 2) {
					$action .= "  id='ButtonU'>\n";
				}
				else {
					if ($status[$value] == 1) {
						$action .= "  onclick=\"xajax_chanspy (".$_SESSION['curuser']['extension'].",'".substr($value,4)."');return false;\" id='ButtonR'>\n";
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

			if ($status[$value] == 1) {
				//$action .= "<span align=left>";
				$action .= "<BR>".$direction[$value];
				$action .= "<BR>".$callerid[$value]."";
				//$action .= "</span>";
			}

			$action .=  "</td>\n";
		}
		$action .= '</tr></table><br>';
		return $action;
	}

	/*
	for now this mode could be used in extension panel in agent interface
	allow to spy extension (when busy) and click-to-call (when idle)
	*/

	function &listStatus($phones,$status,$callerid,$direction){
		$action .= '<table width="100%" cellpadding=2 cellspacing=2 border=0>';
		foreach ($phones as $key => $value) {
			if (!strstr($value,'SIP/'))
				$value = "SIP/".$value;
			$action .= "<tr><td align=center><button name='" . substr($value,4)."'";
			if (isset($status[$value])) {
				if ($status[$value] == 2) {
					$action .= " onclick=\"xajax_dial ('".substr($value,4)."','callee');return false;\" id='ButtonU'>\n";
				}
				else {
					if ($status[$value] == 1) {
						$action .= " onclick=\"xajax_chanspy (".$_SESSION['curuser']['extension'].",'".substr($value,4)."');return false;\" id='ButtonR'>\n";
					}
					else {
						$action .= " onclick=\"xajax_dial ('".substr($value,4)."','callee');return false;\" id='ButtonG'>\n";
					}
				}
			}
			else {
				$action .= " onclick=\"xajax_dial ('".substr($value,4)."','callee');return false;\" id='ButtonB'>\n";
			}
			$action .= $value;
			$action .= "</button>\n";
			if ($status[$value] == 1) {
				//$action .= "<span align=left>";
				$action .= "<BR>".$direction[$value];
				$action .= "<BR><a href=? onclick=\"xajax_getContact('".$callerid[$value]."');return false;\">".$callerid[$value]."</a>";
				//$action .= "</span>";
			}

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
		//$db->disconnect();
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

//		$query = "SELECT * FROM events WHERE event LIKE 'Event: Link%' AND event LIKE '%" . $uniqueid. "%' AND id > $curid AND timestamp > (now()-INTERVAL 10 SECOND) order by id desc ";

		$query = "SELECT * FROM events WHERE event LIKE 'Event: Link%' AND event LIKE '%" . $uniqueid. "%' AND id > $curid AND timestamp >  '".date ("Y-m-d H:i:s" ,time()-10)."' order by id desc ";

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
		//$query = "SELECT * FROM events WHERE event LIKE '%Hangup%' AND event LIKE '%" . $uniqueid . "%' AND timestamp > (now()-INTERVAL 10 SECOND) AND id> $curid order by id desc ";

		$query = "SELECT * FROM events WHERE event LIKE '%Hangup%' AND event LIKE '%" . $uniqueid . "%' AND timestamp > '".date ("Y-m-d H:i:s" ,time()-10)."' AND id> $curid order by id desc ";

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
			$call['callerChannel']		(string)	channel who start the call
*/

	function &checkIncoming($curid,$exten){
		global $db;

		//$pasttime = date ("Y-m-d H:i:s" ,time() - 10);
		$query = "SELECT id FROM events ORDER BY timestamp desc limit 0,1";
		asterEvent::events($query);
		$maxid = $db->getOne($query);
		if (!$maxid){
			$call['curid'] = 0;
			return $call;
		}

		$query = "SELECT * FROM events WHERE (event LIKE 'Event: New% % Channel: %".$exten."% % State: Ring%' ) AND timestamp < '".date ("Y-m-d H:i:s" ,time() - 10)."' AND id > " . $curid . "  AND id < ".$maxid." order by id desc limit 0,1";

//		$query = "SELECT * FROM events WHERE (event LIKE 'Event: New% % Channel: %".$exten."% % State: Ring%' ) AND id > " . $curid . " AND id <= ".$maxid." order by id desc limit 0,1";

		asterEvent::events($query);
		$res = $db->query($query);

//		$list = $db->getRow($query);
//		asterEvent::events("incoming:".$res->numRows());

		if ($res->fetchInto($list)) {

			$id        = $list['id'];
			$timestamp = $list['timestamp'];
			$event     = $list['event'];
			$flds      = split("  ",$event);
			$c         = count($flds);
			$callerid  = '';
			$transferid= '';

			if ($flds[3] == 'State: Ringing'){
				for($i=0;$i<$c;++$i) {
					if (strstr($flds[$i],"Channel:"))	
						$channel = substr($flds[$i],8);

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
			$call['callerChannel'] = trim($channel);
		} else{
			$call['status'] = '';
			$call['curid'] = $maxid;
		}

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
			$call['callerChannel']	(string)	source channel
			$call['calleeChannel']	(string)	destination channel
*/

	function &checkDialout($curid,$exten,$maxid){
		global $db;
//		$query = "SELECT * FROM events WHERE event LIKE 'Event: Dial% Source: %".$exten."%' AND id > " . $curid . " AND id < ".$maxid." AND timestamp > '".date ("Y-m-d H:i:s" ,time()-10)."' order by id desc limit 0,1";	

		$query = "SELECT * FROM events WHERE event LIKE 'Event: Dial% Source: %".$exten."%' AND id > " . $curid . " AND id <= ".$maxid." order by id desc limit 0,1";	
		asterEvent::events($query);

		$res = $db->query($query);
//		asterEvent::events("dialout:".$res->numRows());
//		print_r($res);
		if ($res->fetchInto($list)) {
			$id        = $list['id'];
			$timestamp = $list['timestamp'];
			$event     = $list['event'];
			$flds      = split("  ",$event);
			$callerid  = '';

/*
Event: Dial  Privilege: call,all
Source: Local/13909846473@from-sipuser-47d9,2  
Destination: SIP/trunk1-ec3f  
CallerID: 8000  
CallerIDName: <unknown>  
SrcUniqueID: 1193886661.15682  
DestUniqueID: 1193886661.15683
*/
			if ($flds[0] == 'Event: Dial'){
				$SrcUniqueID = trim(substr($flds[6],12));
				$DestUniqueID = trim(substr($flds[7],13));
				$SrcChannel = trim(substr($flds[2],7));			//add by solo 2007/10/31
				$DestChannel = trim(substr($flds[3],12));		//add by solo 2007/10/31

				$srcInfo = & asterEvent::getInfoBySrcID($SrcUniqueID);
				$callerid = $srcInfo['Extension'];
				asterEvent::events("dialout: ".$event);

			}

			if ($id > $curid) 
				$curid = $id;

			$call['status'] = 'dialout';
			$call['callerid'] = trim($callerid);
			$call['uniqueid'] = $SrcUniqueID;
			$call['curid'] = trim($curid);

			//add by solo 2007/10/31
			//******************
			$call['callerChannel'] = $SrcChannel;
			$call['calleeChannel'] = $DestChannel;
			//******************

		} else{
			$call['status'] = '';
			$call['curid'] = $maxid;
		}

		return $call;
	}

/*
	get more information from events table by DestUniqueID
	@param	$DestUniqueID			(string)	DestUniqueID field in manager event
	return	$call					(array)	
			$call['status']			(string)	'','found'
			$call['Extension']		(string)	extension which unique id is $DestUniqueID
			$call['Channel']		(string)	channel which unique id is $DestUniqueID
*/

	function &getInfoByDestID($DestUniqueID){
		global $db;
		$DestUniqueID = trim($DestUniqueID);
		$query  = "SELECT * FROM events WHERE event LIKE '%Uniqueid: $DestUniqueID%' AND event LIKE 'Event: Newcallerid%' ORDER BY id DESC";
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