<?php
/*******************************************************************************
* poral.server.php
* agent portal interface

* Function Desc
	agent portal background script

* 功能描述
	座席管理脚本

* Function Desc

	showDetail
	getPrivateDialListNumber
	init
	listenCalls
	incomingCalls
	waitingCalls
	createGrid
	getContact
	monitor
	dial
	transfer
	addWithPhoneNumber
	invite
	chanspy

* Revision 0.0456  2007/11/7 14:45:00  last modified by solo
* Desc: add function chanspy

* Revision 0.0456  2007/11/7 11:01:00  last modified by solo
* Desc: fix table width

* Revision 0.0456  2007/11/1 9:48:00  last modified by solo
* Desc: fix bug: when use sendCall method, cant hangup until one party is connected

* Revision 0.0456  2007/10/30 12:47:00  last modified by solo
* Desc: add link for customer and contact

* Revision 0.0456  2007/10/30 8:47:00  last modified by solo
* Desc: add function invite

* Revision 0.0451  2007/10/25 15:21:00  last modified by solo
* Desc: remove confirmCustomer,confirmContact to common file

* Revision 0.0451  2007/10/24 20:37:00  last modified by solo
* Desc: use another dial method: sendCall() to replace Originate

* Revision 0.045  2007/10/18 14:19:00  modified by solo
* Desc: comment added

* Revision 0.045  2007/10/17 20:55:00  modified by solo
* Desc: change callerid match method to like '%callerid'
* 描述: 将电话号码匹配方式修改为前端模糊式检索

* Revision 0.045  2007/10/17 12:55:00  modified by solo
* Desc: fix bugs in search, ordering

********************************************************************************/

require_once ("db_connect.php");
require_once ('include/asterevent.class.php');
require_once ('include/asterisk.class.php');
require_once ('include/common.class.php');
require_once ('astercrm.server.common.php');
require_once ("portal.common.php");
require_once ('include/xajaxGrid.inc.php');
require_once ('portal.grid.inc.php');

/**
*  show customer contact detail based on
*  @param	noteid		int			noteid
*  @return	object					xajax response object
*/

function showDetail($noteid){
	$objResponse = new xajaxResponse();
	if ($config['system']['portal_display_type'] == "note"){
		$objResponse->addScript('xajax_showContact(\''.$noteid.'\',\'note\');');
		$objResponse->addScript('xajax_showCustomer(\''.$noteid.'\',\'note\');');
	}else{
		$objResponse->addScript('xajax_showContact(\''.$noteid.'\',\'customer\');');
		$objResponse->addScript('xajax_showCustomer(\''.$noteid.'\',\'customer\');');
	}
	return $objResponse;
}

/**
*  show phone numbers and dial button if there are phone numbers assigned to this agent
*  in diallist table
*  @param	extension		int			extension
*  @return	object						xajax response object
*/

function getPrivateDialListNumber($extension = null){
	global $locate,$db;
	$objResponse = new xajaxResponse();
	// get dial list
	if ($extension == null)
		$query = 'SELECT COUNT(*) FROM diallist';
	else
		$query = 'SELECT COUNT(*) FROM diallist WHERE assign = "'.$extension.'"';

	$res =& $db->getOne($query);

	if ($res == 0 || $res == "0"){
		$objResponse->addAssign("divDialList", "innerHTML", $locate->Translate("no_dial_list"));
	} else{
		// add div
		$objResponse->addRemove("spanDialListRecords");
		$objResponse->addRemove("btnGetAPhoneNumber");

		$objResponse->addCreate("divDialList", "div", "spanDialListRecords");
		$objResponse->addAssign("spanDialListRecords", "innerHTML", $locate->Translate("records_in_dial_list_table").$res);

		// add start campaign button
		$objResponse->addCreateInput("divDialList", "button", "btnGetAPhoneNumber", "btnGetAPhoneNumber");
		$objResponse->addAssign("btnGetAPhoneNumber", "value", $locate->Translate("get_a_phone_number"));
		$objResponse->addEvent("btnGetAPhoneNumber", "onclick", "btnGetAPhoneNumberOnClick();");
	}

	return $objResponse;
}

function init(){
	global $locate,$config,$db;

	$objResponse = new xajaxResponse();
	
	$html = $locate->Translate("welcome").':'.$_SESSION['curuser']['username'].',';
	$html .= $locate->Translate("extension").$_SESSION['curuser']['extension'];
	$objResponse->addAssign("divUserMsg","innerHTML", $html );
	$objResponse->addAssign("username","value", $_SESSION['curuser']['username'] );
	$objResponse->addAssign("extension","value", $_SESSION['curuser']['extension'] );
	$objResponse->addAssign("myevents","innerHTML", $locate->Translate("waiting") );
//	$objResponse->addAssign("status","innerHTML", $locate->Translate("listening") );
	$objResponse->addAssign("extensionStatus","value", 'idle');
	$objResponse->addAssign("btnDial","value", $locate->Translate("dial") );
	$objResponse->addAssign("btnHangup","value", $locate->Translate("hangup") );
	$objResponse->addAssign("processingMessage","innerHTML", $locate->Translate("processing_please_wait") );
	$objResponse->addAssign("spanMonitorSetting","innerText", $locate->Translate("always_record_when_connected") );
	$objResponse->addAssign("spanMonitor","innerText", $locate->Translate("monitor") );

	$objResponse->addAssign("spanMonitorStatus","innerText", $locate->Translate("idle") );
	$objResponse->addAssign("btnMonitorStatus","value", "idle" );
	$objResponse->addAssign("btnMonitor","value", $locate->Translate("start_record") );
	$objResponse->addAssign("btnMonitor","disabled", true );
	$objResponse->addAssign("divCopyright","innerHTML",common::generateCopyright($skin));

	$objResponse->loadXML(getPrivateDialListNumber($_SESSION['curuser']['extension']));

//	echo $_SESSION['curuser']['usertype'];
//	exit;

	if ($_SESSION['curuser']['usertype'] == "admin"){
		$panelHTML = '<a href=# onclick="this.href=\'managerportal.php\'">'.$locate->Translate("manager").'</a>&nbsp;';
	}

	$panelHTML .="<a href='login.php'>".$locate->Translate("logout")."</a>";
	$objResponse->addAssign("divPanel","innerHTML", $panelHTML);

	if ($config['system']['enable_external_crm'] == false){	//use internal crm
		$objResponse->addIncludeScript("js/astercrm.js");
		$objResponse->addIncludeScript("js/ajax.js");
		$objResponse->addIncludeScript("js/ajax-dynamic-list.js");
		$objResponse->addScript("xajax_showGrid(0,".ROWSXPAGE.",'','','')");
	} else {
		$objResponse->addIncludeScript("js/extercrm.js");
		if ($config['system']['open_new_window'] == false){
			$mycrm = '<iframe id="mycrm" name="mycrm" src="'.$config['system']['external_crm_default_url'].'" width="100%"  frameBorder=0 scrolling=auto height="100%"></iframe>';
			$objResponse->addAssign("divCrm","innerHTML", $mycrm );
		}else{
			$javascript = "openwindow('".$config['system']['external_crm_default_url']."')";
			$objResponse->addScript($javascript);
		}
	}

	return $objResponse;
}

function listenCalls($aFormValues){
	if ($aFormValues['uniqueid'] == ''){
		return waitingCalls($aFormValues);
	} else{
		return incomingCalls($aFormValues);
	}
}

//transfer
function transfer($aFormValues){
	global $config;
	$myAsterisk = new Asterisk();
	$myAsterisk->config['asmanager'] = $config['asterisk'];
	$res = $myAsterisk->connect();
	$objResponse = new xajaxResponse();

	if ($aFormValues['direction'] == 'in')		
		$myAsterisk->Redirect($aFormValues['callerChannel'],'',$aFormValues['sltExten'],$config['system']['outcontext'],1);
	else
		$myAsterisk->Redirect($aFormValues['calleeChannel'],'',$aFormValues['sltExten'],$config['system']['outcontext'],1);
	$myAsterisk->disconnect();
	return $objResponse;
}

//check if call (uniqueid) hangup
function incomingCalls($myValue){
	global $db,$locate;
	$objResponse = new xajaxResponse();

	if ($myValue['direction'] != ''){

		$call = asterEvent::checkCallStatus($myValue['curid'],$myValue['uniqueid']);

		if ($call['status'] ==''){
			return $objResponse;
		} elseif ($call['status'] =='link'){
			if ($myValue['extensionStatus'] == 'link')
				return $objResponse;
//			if ($call['callerChannel'] == '' or $call['calleeChannel'] == '')
//				return $objResponse;
			$status	= "link";
			$info	= $locate->Translate("talking_to").$myValue['callerid'];
			$objResponse->addAssign("callerChannel","value", $call['callerChannel'] );
			$objResponse->addAssign("calleeChannel","value", $call['calleeChannel'] );
//			if  ($call['callerChannel'] != '' and $call['calleeChannel']!=''){
				//enable monitor
				$objResponse->addAssign("btnMonitor","disabled", false );
				$objResponse->addAssign("btnMonitor","value", $locate->Translate("start_record") );
				if ($myValue['chkMonitor'] == 'on') //always recording
					$objResponse->addScript("monitor();");
//			}
			
			$objResponse->addAssign("btnHangup","disabled", false );

			if ($myValue['sltExten'] == ''){
				$transfer = '
							<SELECT id="sltExten" name="sltExten">
							';
				foreach ($_SESSION['curuser']['extensions'] as $extension){
					$transfer .= '
									<option value="'.trim($extension).'">'.trim($extension).'</option>
								';
				}

				$transfer .= '
							</SELECT>
							<INPUT type="BUTTON" value="'.$locate->Translate("transfer").'" onclick="xajax_transfer(xajax.getFormValues(\'myForm\'));return false;">
							';
				$objResponse->addAssign(spanTransfer,"innerHTML", $transfer );
			}

		} elseif ($call['status'] =='hangup'){
			$status	= 'hang up';
			$info	= "Hang up call from " . $myValue['callerid'];
//			$objResponse->addScript('document.title=\'asterCrm\';');
			$objResponse->addAssign("uniqueid","value", "" );
			$objResponse->addAssign("callerid","value", "" );
			$objResponse->addAssign("callerChannel","value", '');
			$objResponse->addAssign("calleeChannel","value", '');
			$objResponse->addAssign(spanTransfer,"innerHTML", '');

			//disable monitor
			$objResponse->addAssign("btnMonitor","disabled", true );
			$objResponse->addAssign("spanMonitorStatus","innerHTML", $locate->Translate("idle") );

			//disable hangup button
			$objResponse->addAssign("btnHangup","disabled", true );

		}
//		$objResponse->addAssign("status","innerHTML", $status );
		$objResponse->addAssign("extensionStatus","value", $status );
		$objResponse->addAssign("myevents","innerHTML", $info );
	}

	return $objResponse;
}



function waitingCalls($myValue){
	global $db,$config,$locate;
	$objResponse = new xajaxResponse();
	$curid = trim($myValue['curid']);

	$phone_html = asterEvent::checkExtensionStatus($curid);
	
	$objResponse->addAssign("divExtension","innerHTML", $phone_html );

	//	modified 2007/10/30 by solo
	//  start
	if ($_SESSION['curuser']['channel'] == '')
		$call = asterEvent::checkNewCall($curid,$_SESSION['curuser']['extension']);
	else
		$call = asterEvent::checkNewCall($curid,$_SESSION['curuser']['channel']);
	//  end

	if ($call['status'] == ''){
		$title	= $locate->Translate("waiting");
		$status	= 'idle';
		$call['curid'] = $curid;
		$direction	= '';
		$info	= $locate->Translate("stand_by");
	} elseif ($call['status'] == 'incoming'){	//incoming calls here
		$title	= $call['callerid'];
		$stauts	= 'ringing';
		$direction	= 'in';
		$info	= $locate->Translate("incoming"). ' ' . $call['callerid'];

		$objResponse->addAssign("btnHangup","disabled", false );

		if ($config['system']['pop_up_when_dial_in']){
			if (strlen($call['callerid']) > $config['system']['phone_number_length'] && $call['callerid'] != '<unknown>'){
				if ($myValue['popup'] == 'yes'){
					if ($config['system']['enable_external_crm'] == false){
							$objResponse->loadXML(getContact($call['callerid']));
							if ( $config['system']['maximize_when_pop_up'] == true ){
								$objResponse->addScript('maximizeWin();');
							}
					}else{
						//use external link
						$myurl = $config['system']['external_crm_url'];
						$myurl = preg_replace("/\%method/","dial_in",$myurl);
						$myurl = preg_replace("/\%callerid/",$call['callerid'],$myurl);
						$myurl = preg_replace("/\%calleeid/",$_SESSION['curuser']['extension'],$myurl);

						if ($config['system']['open_new_window'] == false){
								$mycrm = '<iframe id="mycrm" name="mycrm" src="'.$myurl.'" width="100%"  frameBorder=0 scrolling=auto height="100%"></iframe>';
								$objResponse->addAssign("divCrm","innerHTML", $mycrm );
						}else{
							$javascript = "openwindow('".$myurl."')";
							$objResponse->addScript($javascript);
						}
					}
				}
			}else{

			}
		}
	} elseif ($call['status'] == 'dialout'){	//dailing out here
		$title	= $call['callerid'];
		$status	= 'dialing';
		$direction	= 'out';
		$info	= $locate->Translate("dial_out"). ' '. $call['callerid'];

		$objResponse->addAssign("btnHangup","disabled", false );


		if ($config['system']['pop_up_when_dial_out']){
			if (strlen($call['callerid']) > $config['system']['phone_number_length']){
				if ($myValue['popup'] == 'yes'){
					if ($config['system']['enable_external_crm'] == false ){
							$objResponse->loadXML(getContact($call['callerid']));
							if ( $config['system']['maximize_when_pop_up'] == true ){
								$objResponse->addScript('maximizeWin();');
							}
					}else{
						//use external link
						$myurl = $config['system']['external_crm_url'];
						$myurl = preg_replace("/\%method/","dial_out",$myurl);
						$myurl = preg_replace("/\%callerid/",$_SESSION['curuser']['extension'],$myurl);
						$myurl = preg_replace("/\%calleeid/",$call['callerid'],$myurl);
						if ($config['system']['open_new_window'] == false){
							$mycrm = '<iframe id="mycrm" name="mycrm" src="'.$myurl.'" width="100%"  frameBorder=0 scrolling=auto height="100%"></iframe>';
							$objResponse->addAssign("divCrm","innerHTML", $mycrm );
						} else {
							$javascript = "openwindow('".$myurl."')";
							$objResponse->addScript($javascript);
						}
					}
				}
			}
		}
	}

//	$objResponse->addScript('document.title='.$title.';');
//	$objResponse->addAssign("status","innerHTML", $stauts );
	$objResponse->addAssign("extensionStatus","value", $stauts );
	$objResponse->addAssign("uniqueid","value", $call['uniqueid'] );
	$objResponse->addAssign("callerid","value", $call['callerid'] );

	$objResponse->addAssign("callerChannel","value", $call['callerChannel'] );
	$objResponse->addAssign("calleeChannel","value", $call['calleeChannel'] );

	$objResponse->addAssign("curid","value", $call['curid'] );
	$objResponse->addAssign("direction","value", $direction );
	$objResponse->addAssign("myevents","innerHTML", $info);

	return $objResponse;
}

//	create grid
function createGrid($start = 0, $limit = 1, $filter = null, $content = null, $order = null, $divName = "grid", $ordering = ""){
	global $locate,$config;

	$_SESSION['ordering'] = $ordering;

	if($filter == null or $content == null){
		$numRows =& Customer::getNumRows();
		$arreglo =& Customer::getAllRecords($start,$limit,$order);
	}else{
		foreach($content as $value){
			if(trim($value) != ""){  //搜索内容有值
				$flag = "1";
				break;
			}
		}
		foreach($filter as $value){
			if(trim($value) != ""){  //搜索条件有值
				$flag2 = "1";
				break;
			}
		}
		if($flag != "1" || $flag2 != "1"){  //无值
			$order = null;
			$numRows =& Customer::getNumRows();
			$arreglo =& Customer::getAllRecords($start,$limit,$order);
		}else{
			$order = "id";
			$numRows =& Customer::getNumRowsMore($filter, $content,"customer");
			$arreglo =& Customer::getRecordsFilteredMore($start, $limit, $filter, $content, $order,"customer");
		}
	}

	// Editable zone

	// Databse Table: fields
	$fields = array();
	$fields[] = 'customer';
	$fields[] = 'category';
	$fields[] = 'contact';
	$fields[] = 'note';
	$fields[] = 'attitude';   //face
	$fields[] = 'cretime';
	$fields[] = 'creby';
	$fields[] = 'priority';

	// HTML table: Headers showed
	$headers = array();
	$headers[] = $locate->Translate("customer_name")."<BR>";//"Customer Name";
	$headers[] = $locate->Translate("category")."<BR>";//"Category";
	$headers[] = $locate->Translate("contact")."<BR>";//"Contact";
	$headers[] = $locate->Translate("note")."<BR>";//"Note";
	$headers[] = $locate->Translate("attitude")."<BR>";//"face";
	$headers[] = $locate->Translate("create_time")."<BR>";//"Create Time";
//	$headers[] = $locate->Translate("create_by")."<BR>";//"Create By";
	$headers[] = "P<BR>";
//	$headers[] = "D";

	// HTML table: hearders attributes
	$attribsHeader = array();
	$attribsHeader[] = 'width="20%" nowrap';
	$attribsHeader[] = 'width="10%" nowrap';
	$attribsHeader[] = 'width="8%" nowrap';
	$attribsHeader[] = 'width="36%" nowrap';//note
	$attribsHeader[] = 'width="8%" nowrap'; //face
	$attribsHeader[] = 'width="10% nowrap"';
//	$attribsHeader[] = 'width="10%"';
//	$attribsHeader[] = 'width="7%"';
	$attribsHeader[] = 'width="8%" nowrap';

	// HTML Table: columns attributes
	$attribsCols = array();
	$attribsCols[] = 'style="text-align: left"';
	$attribsCols[] = 'style="text-align: left"';
	$attribsCols[] = 'style="text-align: left"';
	$attribsCols[] = 'style="text-align: left;textarea-layout:fixed;word-break:break-all;"';
	$attribsCols[] = 'style="text-align: left"';
//	$attribsCols[] = 'nowrap style="text-align: left"';
	$attribsCols[] = 'style="text-align: left;"';


	// HTML Table: If you want ascendent and descendent ordering, set the Header Events.
	$eventHeader = array();
	$eventHeader[]= 'onClick=\'xajax_showGrid(0,'.$limit.',"'.$filter.'","'.$content.'","customer","'.$divName.'","ORDERING");return false;\'';
	$eventHeader[]= 'onClick=\'xajax_showGrid(0,'.$limit.',"'.$filter.'","'.$content.'","category","'.$divName.'","ORDERING");return false;\'';
	$eventHeader[]= 'onClick=\'xajax_showGrid(0,'.$limit.',"'.$filter.'","'.$content.'","contact","'.$divName.'","ORDERING");return false;\'';
	$eventHeader[]= 'onClick=\'xajax_showGrid(0,'.$limit.',"'.$filter.'","'.$content.'","note","'.$divName.'","ORDERING");return false;\'';
	$eventHeader[]= 'onClick=\'xajax_showGrid(0,'.$limit.',"'.$filter.'","'.$content.'","attitude","'.$divName.'","ORDERING");return false;\'';  //face
	$eventHeader[]= 'onClick=\'xajax_showGrid(0,'.$limit.',"'.$filter.'","'.$content.'","cretime","'.$divName.'","ORDERING");return false;\'';
//	$eventHeader[]= 'onClick=\'xajax_showGrid(0,'.$limit.',"'.$filter.'","'.$content.'","creby","'.$divName.'","ORDERING");return false;\'';
	$eventHeader[]= 'onClick=\'xajax_showGrid(0,'.$limit.',"'.$filter.'","'.$content.'","priority","'.$divName.'","ORDERING");return false;\'';

	// Select Box: fields table.
	$fieldsFromSearch = array();
	if ($config['system']['portal_display_type'] == "note"){
		$fieldsFromSearch[] = 'customer';
		$fieldsFromSearch[] = 'category';
		$fieldsFromSearch[] = 'contact.contact';
		$fieldsFromSearch[] = 'note';
		$fieldsFromSearch[] = 'attitude';  //face
		$fieldsFromSearch[] = 'priority';
		$fieldsFromSearch[] = 'note.cretime';
	}else{
		$fieldsFromSearch[] = 'customer.customer';
		$fieldsFromSearch[] = 'customer.category';
		$fieldsFromSearch[] = 'customer.contact';
		$fieldsFromSearch[] = 'note.note';
		$fieldsFromSearch[] = 'note.attitude';  //face
		$fieldsFromSearch[] = 'note.priority';
		$fieldsFromSearch[] = 'customer.cretime';
	}

	// Selecct Box: Labels showed on search select box.
	$fieldsFromSearchShowAs = array();
	$fieldsFromSearchShowAs[] = $locate->Translate("customer_name");
	$fieldsFromSearchShowAs[] = $locate->Translate("category");
	$fieldsFromSearchShowAs[] = $locate->Translate("contact");
	$fieldsFromSearchShowAs[] = $locate->Translate("note");
	$fieldsFromSearchShowAs[] = $locate->Translate("attitude"); //face
	$fieldsFromSearchShowAs[] = $locate->Translate("priority");
	$fieldsFromSearchShowAs[] = $locate->Translate("create_time");


	// Create object whit 5 cols and all data arrays set before.
	$table = new ScrollTable(6,$start,$limit,$filter,$numRows,$content,$order);
	$table->setHeader('title',$headers,$attribsHeader,$eventHeader);
	$table->setAttribsCols($attribsCols);
	//$table->addRowSearch("note",$fieldsFromSearch,$fieldsFromSearchShowAs);
	if ($config['system']['portal_display_type'] == "note"){
		$table->addRowSearchMore("note",$fieldsFromSearch,$fieldsFromSearchShowAs,$filter,$content);
	}else{
		$table->addRowSearchMore("customer",$fieldsFromSearch,$fieldsFromSearchShowAs,$filter,$content);
	}

	//print_r($arreglo);
	while ($arreglo->fetchInto($row)) {
	// Change here by the name of fields of its database table
		$rowc = array();
		$rowc[] = $row['id'];

		$rowc[] = "<a href=? onclick=\"xajax_showCustomer('".$row['customerid']."');return false;\"
		>".$row['customer']."</a>";

		$rowc[] = $row['category'];

		$rowc[] = "<a href=? onclick=\"xajax_showContact('".$row['contactid']."');return false;\"
		>".$row['contact']."</a>";

		//$rowc[] = '<textarea readonly="true" style="overflow:auto;width: 240px;height:50px;" wrap="soft">'.str_replace('<br>',chr(13),$row['note']).'</textarea>';

		$rowc[] = ''.$row['note'].'';

		if ($row['attitude'] != '')
			$rowc[] = '<img src="skin/default/images/'.$row['attitude'].'.gif" width="25px" height="25px" border="0" />';
		else 
			$rowc[] = '';

		$rowc[] =  str_replace(" ","<br>",$row['cretime']);
//		$rowc[] = $row['creby'];
		$rowc[] = $row['priority'];
//		$rowc[] = 'Detail';

		$table->addRow("note",$rowc,1,1,1,$divName,$fields);
 	}
 	
 	// End Editable Zone
 	
 	$html = $table->render();
 	
 	return $html;
}

function addWithPhoneNumber(){
	$objResponse = new xajaxResponse();
	global $db;
	//get a phone number from database
	
	$query = '
		SELECT id,dialnumber 
		FROM diallist
		WHERE assign = '.$_SESSION['curuser']['extension'].'
		ORDER BY id DESC
		LIMIT 0,1 
		 ' ;
	
	$res = $db->query($query);
	if ($res->numRows() == 0){

	} else {
		$res->fetchInto($list);
		$phoneNum = $list['dialnumber'];
		$objResponse->loadXML(getContact($phoneNum));
		$id = $list['id'];
		$query = 'DELETE FROM diallist WHERE id ='.$id ;
		$res = $db->query($query);

		$query = 'INSERT INTO dialedlist (dialnumber,dialedby,dialedtime) VALUES ("'.$phoneNum.'","'.$_SESSION['curuser']['extension'].'",now())';
		$res = $db->query($query);
	}

	$objResponse->loadXML(getPrivateDialListNumber($_SESSION['curuser']['extension']));

	return $objResponse;
}

# click to dial
# $phoneNum	phone to call
# $first	which phone will ring first, caller or callee

function dial($phoneNum,$first = ''){
	global $config;
	$myAsterisk = new Asterisk();
	$objResponse = new xajaxResponse();
	if ($first == ''){
		$first = $config['system']['firstring'];
	}

	$myAsterisk->config['asmanager'] = $config['asterisk'];
	$res = $myAsterisk->connect();
	if (!$res)
		$objResponse->addAssign("mobileStatus", "innerText", "Failed");


	if ($first == 'caller'){	//caller will ring first
		$strChannel = "Local/".$_SESSION['curuser']['extension']."@".$config['system']['incontext']."/n";

		if ($config['system']['allow_dropcall'] == true){
			$myAsterisk->dropCall($sid,array('Channel'=>"$strChannel",
								'WaitTime'=>30,
								'Exten'=>$phoneNum,
								'Context'=>$config['system']['outcontext'],
								'Variable'=>"$strVariable",
								'Priority'=>1,
								'MaxRetries'=>0,
								'CallerID'=>$phoneNum));
		}else{
			$myAsterisk->sendCall($strChannel,$phoneNum,$config['system']['outcontext'],1,NULL,NULL,30,$phoneNum,NULL,NULL);
		}
	}else{
		$strChannel = "Local/".$phoneNum."@".$config['system']['outcontext']."/n";

		if ($config['system']['allow_dropcall'] == true){

			$myAsterisk->dropCall($sid,array('Channel'=>"$strChannel",
								'WaitTime'=>30,
								'Exten'=>$_SESSION['curuser']['extension'],
								'Context'=>$config['system']['incontext'],
								'Variable'=>"$strVariable",
								'Priority'=>1,
								'MaxRetries'=>0,
								'CallerID'=>$_SESSION['curuser']['extension']));
		}else{
			$myAsterisk->sendCall($strChannel,$_SESSION['curuser']['extension'],$config['system']['incotext'],1,NULL,NULL,30,$_SESSION['curuser']['extension'],NULL,NULL);
		}
	}
	//$myAsterisk->disconnect();
	return $objResponse->getXML();
}

/**
*  Originate src and dest extension
*  @param	src			string			extension
*  @param	dest		string			extension
*  @return	object						xajax response object
*/

function invite($src,$dest){
	global $config;
	$src = trim($src);
	$dest = trim($dest);
	$myAsterisk = new Asterisk();
	$objResponse = new xajaxResponse();
	
/*
	if ($src == '' and $dest == '')
		return $objResponse;

	if ($src == '')
		$src = $_SESSION['curuser']['extension'];

	if ($dest == '')
		$dest = $_SESSION['curuser']['extension'];
*/

	$myAsterisk->config['asmanager'] = $config['asterisk'];
	$res = $myAsterisk->connect();
	if (!$res)
		$objResponse->addAssign("mobileStatus", "innerText", "Failed");


		$strChannel = "Local/".$src."@".$config['system']['incontext']."/n";

	if ($config['system']['allow_dropcall'] == true){
		$myAsterisk->dropCall($sid,array('Channel'=>"$strChannel",
							'WaitTime'=>30,
							'Exten'=>$dest,
							'Context'=>$config['system']['outcontext'],
							'Variable'=>"$strVariable",
							'Priority'=>1,
							'MaxRetries'=>0,
							'CallerID'=>$dest));
	}else{
		$myAsterisk->sendCall($strChannel,$dest,$config['system']['outcontext'],1,NULL,NULL,30,$dest,NULL,NULL);
	}

	//$myAsterisk->disconnect();	// comment by solo 2007-11-1
									// if we use disconnect, it would need time waiting for asterisk return handle
	return $objResponse->getXML();
}

/**
*  hangup a channel
*  @param	channel			string		channel name
*  @return	object						xajax response object
*/


function hangup($channel){
	global $config,$locate;
	$myAsterisk = new Asterisk();
	$objResponse = new xajaxResponse();

	$myAsterisk->config['asmanager'] = $config['asterisk'];
	$res = $myAsterisk->connect();
	if (!$res){
		return;
	}
	$myAsterisk->Hangup($channel);
	//$objResponse->addAssign("btnHangup", "disabled", true);
	return $objResponse;
}

function getContact($callerid){
	global $db,$locate,$config;	
	$mycallerid = $callerid;
	$objResponse = new xajaxResponse();

	if ( $config['system']['trim_prefix'] != ''){
		$prefix = split(",",$config['system']['trim_prefix']);
		foreach ($prefix as $myprefix ) {
			if (substr($mycallerid,0,1) == $myprefix){
				$mycallerid = substr($mycallerid,1);
				break;
			}
		}
	}
			

	//check contact table first
	$query = '
			SELECT id,customerid 
			FROM contact
			WHERE phone LIKE \'%'. $mycallerid . '\'
			OR phone1 LIKE \'%'. $mycallerid . '\'
			OR phone2 LIKE \'%'. $mycallerid . '\'
			OR mobile LIKE \'%'. $mycallerid . '\'
			 ' ;
//	print $query;
	$res = $db->query($query);

	if ($res->numRows() == 0){	//no match
//	print 'no match in contact list';

		//try get customer
		$customerid = Customer::getCustomerByCallerid($mycallerid);

		if ($customerid == ''){
			$objResponse->addScript('xajax_add(\'' . $callerid . '\');');
		}else{
			$html = Table::Top($locate->Translate("add_record"),"formDiv");  // <-- Set the title for your form.
			$html .= Customer::formAdd($callerid,$customerid);  // <-- Change by your method
			$html .= Table::Footer();
			$objResponse->addAssign("formDiv", "style.visibility", "visible");
			$objResponse->addAssign("formDiv", "innerHTML", $html);
			$objResponse->addScript('xajax_showCustomer(\''.$customerid.'\');');
		}
	} elseif ($res->numRows() == 1) { // one match

		$res->fetchInto($list);
		$customerid = $list['customerid'];
		$contactid = $list['id'];
		
		$html = Table::Top($locate->Translate("add_record"),"formDiv");  // <-- Set the title for your form.
		$html .= Customer::formAdd($callerid,$customerid,$contactid);  // <-- Change by your method
		$html .= Table::Footer();
		$objResponse->addAssign("formDiv", "style.visibility", "visible");
		$objResponse->addAssign("formDiv", "innerHTML", $html);

		$objResponse->addScript('xajax_showContact(\''.$contactid.'\');');
		if ($customerid != 0)
			$objResponse->addScript('xajax_showCustomer(\''.$customerid.'\');');

	}else {	//match a lot records... [only display the first one for now]
		$res->fetchInto($list);
		$customerid = $list['customerid'];
		$contactid = $list['id'];
		
		$html = Table::Top($locate->Translate("add_record"),"formDiv");  // <-- Set the title for your form.
		$html .= Customer::formAdd($callerid,$customerid,$contactid);  // <-- Change by your method
		$html .= Table::Footer();
		$objResponse->addAssign("formDiv", "style.visibility", "visible");
		$objResponse->addAssign("formDiv", "innerHTML", $html);

		$objResponse->addScript('xajax_showContact(\''.$contactid.'\');');
		$objResponse->addScript('xajax_showCustomer(\''.$customerid.'\');');
	}

	return $objResponse;
}


function chanspy($exten,$spyexten){
	global $config,$locate;
	$myAsterisk = new Asterisk();
	$objResponse = new xajaxResponse();

	$myAsterisk->config['asmanager'] = $config['asterisk'];
	$res = $myAsterisk->connect();
	if (!$res){
		return;
	}
	$myAsterisk->chanSpy($exten,$spyexten);
	//$objResponse->addAlert($exten);
	//$objResponse->addAlert($spyexten);
	return $objResponse;

}

function searchFormSubmit($searchFormValue,$numRows,$limit){
	global $locate,$db;
	$objResponse = new xajaxResponse();
	$searchField = array();
	$searchContent = array();
	$searchContent = $searchFormValue['searchContent'];  //搜索内容 数组
	$searchField = $searchFormValue['searchField'];      //搜索条件 数组
	
	//$limit = $searchFormValue['limit'];  // limit 
	//$numRowsToShow = $searchFormValue['numRowsToShow']; // start

	$divName = "grid";
	$html = createGrid($numRows, $limit,$searchField, $searchContent, $searchField, $divName, "");
	$objResponse = new xajaxResponse();
	$objResponse->addClear("msgZone", "innerHTML");
	$objResponse->addAssign($divName, "innerHTML", $html);
	$objResponse->addAssign($divName, "innerHTML", $html);
	return $objResponse->getXML();
}

$xajax->processRequests();

?>