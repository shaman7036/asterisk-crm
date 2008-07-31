<?
/*******************************************************************************
* astercrm.server.common.php
* xajax.Grid类的共用函数, 适用于包含customer,contact,note信息的界面
*							customer.*
*							contact.*
*							note.*
*							survey.*
* astercrm

* Functions List

			noteAdd					显示增加note的表单
			saveNote				保存note
			surveyAdd				显示增加survey result的表单
			saveSurvey				保存survey result结果
			showCustomer			显示详细customer信息的表单
			showContact				显示详细contact信息的表单
			
			showNote				显示详细note信息的表单
			save					主保存函数
									可用于插入customer, contact, survey result 和 note
			update					主更新函数, 可用于更新customer, contact 和 note
			updateField				更新某一域的函数
			updateField				将表格对象更改为可修改记录的inputbox对象
			add						主显示函数
									显示同时增加customer, contact, survey result 和 note
			showGrid				显示grid表格
			delete					从数据库中删除一条记录
			edit
			confirmCustomer
			confirmContact
			-----------2008-6 by donnie----------------------------------------
			showCdr					显示customer对应的CDR列表页
			showDiallist			显示customer对应的Diallist列表页(条件:diallist.assign																	==agent.exten)
			showRecords				显示customer对应的录音列表页
			addDiallist				显示增加customer对应的diallist的表单
			searchCdrFormSubmit		customer对应的CDR搜索
			searchDiallistFormSubmit	 customer对应的Diallist搜索
			saveDiallist			保存新增和修改的diallist记录
			-------------------------------------------------------------------
* Revision 0.0455  2007/10/25 15:21:00  last modified by solo
* Desc: add confirmCustomer,confirmContact

*/

// 判断是否存在$customerName, 如果存在就显示
function confirmCustomer($customerName,$callerID = null,$contactID){
	global $locate;
	$objResponse = new xajaxResponse();
	if (trim($customerName) == '')
		return $objResponse;

	$customerID = Customer::checkValues("customer","customer",$customerName); 
	if ($customerID && $customerID !=0){//存在
		$html = Table::Top($locate->Translate("add_record"),"formDiv");
		$html .= Customer::formAdd($callerID,$customerID,$contactID);
		$html .= Table::Footer();
		$objResponse->addAssign("formDiv", "style.visibility", "visible");
		$objResponse->addAssign("formDiv", "innerHTML", $html);
		$objResponse->addScript("xajax_showCustomer($customerID)");
	} //else
	//		$objResponse->addAlert("不存在" );

	return $objResponse;
}

//判断是否存在$contactName
function confirmContact($contactName,$customerID,$callerID){
	global $locate;

	$objResponse = new xajaxResponse();
	$contactID = Customer::checkValues("contact","contact",$contactName,"string","customerid",$customerID,"int"); 
	if ($contactID){//存在

		$html = Table::Top($locate->Translate("add_record"),"formDiv"); 
		$html .= Customer::formAdd($callerID,$customerID,$contactID);
		$html .= Table::Footer();
		$objResponse->addAssign("formDiv", "style.visibility", "visible");
		$objResponse->addAssign("formDiv", "innerHTML", $html);
		//显示customer信息
		if ($customerID !=0)
			$objResponse->addScript("xajax_showCustomer($customerID)");

		//显示contact信息
		$objResponse->addScript("xajax_showContact($contactID)");

	} 

	return $objResponse;
}

function noteAdd($customerid,$contactid){
	global $locate;
	$html = Table::Top($locate->Translate("add_note"),"formNoteInfo"); 			
	$html .= Customer::noteAdd($customerid,$contactid); 		
	$html .= Table::Footer();
	$objResponse = new xajaxResponse();
	$objResponse->addAssign("formNoteInfo", "style.visibility", "visible");
	$objResponse->addAssign("formNoteInfo", "innerHTML", $html);	
	return $objResponse->getXML();
}

function surveyAdd($customerid,$contactid){
	global $locate;

	$html = Table::Top($locate->Translate("add_survey"),"formNoteInfo"); 			
	$html .= Customer::surveyAdd($customerid,$contactid); 		
	$html .= Table::Footer();
	$objResponse = new xajaxResponse();
	$objResponse->addAssign("formNoteInfo", "style.visibility", "visible");
	$objResponse->addAssign("formNoteInfo", "innerHTML", $html);	
	return $objResponse->getXML();
}

function showNote($id = '', $type="customer"){
	global $locate;
	if($id != ''){
		$html = Table::Top($locate->Translate("note_detail"),"formNoteInfo"); 			
		$html .= Customer::showNoteList($id,$type); 		
		$html .= Table::Footer();
		$objResponse = new xajaxResponse();
		$objResponse->addAssign("formNoteInfo", "style.visibility", "visible");
		$objResponse->addAssign("formNoteInfo", "innerHTML", $html);	
		return $objResponse->getXML();
	}
}

function showCustomer($id = 0, $type="customer"){
	global $locate;
	$objResponse = new xajaxResponse();
	if($id != 0 && $id != null ){
		$html = Table::Top($locate->Translate("customer_detail"),"formCustomerInfo"); 			
		$html .= Customer::showCustomerRecord($id,$type); 		
		$html .= Table::Footer();
		$objResponse->addAssign("formCustomerInfo", "style.visibility", "visible");
		$objResponse->addAssign("formCustomerInfo", "innerHTML", $html);	
		return $objResponse->getXML();
	}else
		return $objResponse->getXML();
}

function showContact($id = null, $type="contact"){
	global $locate;
	$objResponse = new xajaxResponse();

	if($id != null ){
		$html = Table::Top($locate->Translate("contact_detail"),"formContactInfo"); 
		$contactHTML .= Customer::showContactRecord($id,$type);

		if ($contactHTML == '')
			return $objResponse->getXML();
		else
			$html .= $contactHTML;

		$html .= Table::Footer();
		$objResponse->addAssign("formContactInfo", "style.visibility", "visible");
		$objResponse->addAssign("formContactInfo", "innerHTML", $html);	
		return $objResponse->getXML();
	}
}

function saveNote($f){
	$objResponse = new xajaxResponse();
	global $locate;
	$respOk = Customer::insertNewNote($f,$f['customerid'],$f['contactid']);
	if ($respOk){
		$objResponse->addAssign("formNoteInfo", "style.visibility", "hidden");
		$objResponse->addClear("formNoteInfo", "innerHTML");	

		$html = createGrid(0,ROWSXPAGE);
		$objResponse->addAssign("grid", "innerHTML", $html);
		$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("a_new_note_added"));

	}else
		$objResponse->addAlert('can not add note');

	return $objResponse;
}

function saveSurvey($f){
	$objResponse = new xajaxResponse();
	global $locate;
	if ($f['surveyoption'] != '' || $f['surveynote'] != ''){
		$respOk = Customer::insertNewSurveyResult($f['surveyid'],$f['surveyoption'],$f['surveynote'],$f['customerid'],$f['contactid']); 
		if ($respOk){
			$objResponse->addAlert('add a new survey');
			$objResponse->addAssign("formNoteInfo", "style.visibility", "hidden");
			$objResponse->addClear("formNoteInfo", "innerHTML");	
		}else
			$objResponse->addAlert('can not add survey');
	}
	return $objResponse;
}

function save($f){
	$objResponse = new xajaxResponse();
	global $locate,$config;

	$f['customer'] = trim($f['customer']);
	$f['contact'] = trim($f['contact']);

	if (empty($f['customer']) && empty($f['contact']))
		return $objResponse;
	
	if(empty($f['customer'])) {
		$customerID = 0;
	} else{
	

		if ($f['customerid'] == '' || $f['customerid'] == 0){
			if ($config['system']['allow_same_data'] == false){
				//检查是否有完全匹配的customer记录
				$customer = Customer::checkValues("customer","customer",$f['customer']);
			}else{
				$customer = '';
			}

			//有完全匹配的话就取这个customerid
			if ($customer != ''){
				$respOk = $customer;
				$objResponse->addAlert($locate->Translate("found_customer_replaced"));
			}else{
				$respOk = Customer::insertNewCustomer($f); // insert a new customer record
				if (!$respOk){
					$objResponse->addAlert($locate->Translate("customer_add_error"));
					return $objResponse;
				}
				$objResponse->addAlert($locate->Translate("a_new_customer_added"));
			}
		} else{
			$respOk = $f['customerid'];
		}
		$customerID = $respOk;
	}

	if(empty($f['contact'])) {
		$contactID = 0;
	} else{
		if ($f['contactid'] == ''){

			if ($config['system']['allow_same_data'] == false){
				//检查是否有完全匹配的contact记录
				$contact = Customer::checkValues("contact","contact",$f['contact'],"string","customerid",$customerID,"int");
			}else{
				$contact = '';
			}

			//有完全匹配的话就取这个contactid
			if ($contact != ''){
				$respOk = $contact;
				$objResponse->addAlert($locate->Translate("found_contact_replaced"));
			}else{
				$respOk = Customer::insertNewContact($f,$customerID); // insert a new contact record
				if (!$respOk){
					$objResponse->addAlert($locate->Translate("contact_add_error"));
					return $objResponse;
				}
				$objResponse->addAlert($locate->Translate("a_new_contact_added"));
			}
		}else{
			$respOk = $f['contactid'];

			$res =& Customer::getContactByID($respOk);
			if ($res){
				$contactCustomerID = $res['customerid'];
				if ($contactCustomerID == 0 && $customerID ==0)
				{
				}else{
					$res =& Customer::updateField('contact','customerid',$customerID,$f['contactid']);
					if ($res){
						$objResponse->addAlert($locate->Translate("a_contact_binding"));
					}
				}
			}
		}
		$contactID = $respOk;
	}

	if ($f['surveyoption'] != '' || $f['surveynote'] != ''){
		$respOk = Customer::insertNewSurveyResult($f['surveyid'],$f['surveyoption'],$f['surveynote'],$customerID,$contactID); 
		$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("survey_added"));
	}
	
	if ($respOk)

	if(empty($f['note'])) {

	} else{

		$respOk = Customer::insertNewNote($f,$customerID,$contactID); // add a new Note
		if ($respOk){
			$html = createGrid(0,ROWSXPAGE);
			$objResponse->addAssign("grid", "innerHTML", $html);
			$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("a_new_note_added"));
		}else{
			$objResponse->addAlert($locate->Translate("note_add_error"));
			return $objResponse;
		}
	}


	$objResponse->addAssign("formDiv", "style.visibility", "hidden");
	$objResponse->addAssign("formCustomerInfo", "style.visibility", "hidden");
	$objResponse->addAssign("formContactInfo", "style.visibility", "hidden");

	$objResponse->addClear("formDiv", "innerHTML");

	$objResponse->addClear("formCustomerInfo", "innerHTML");
	$objResponse->addClear("formContactInfo", "innerHTML");

	return $objResponse->getXML();
}

function delete($id = null, $table_DB = null){
	global $locate;
	Customer::deleteRecord($id,$table_DB);
	$html = createGrid(0,ROWSXPAGE);
	$objResponse = new xajaxResponse();
	$objResponse->addAssign("grid", "innerHTML", $html);
	$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("record_deleted")); 
	return $objResponse->getXML();
}

function update($f, $type){
	$objResponse = new xajaxResponse();

	if ($type == 'note'){
		$respOk = Customer::updateNoteRecord($f,"append");
	}elseif ($type == 'customer'){
		if (empty($f['customer']))
			$message = "The field Customer does not have to be null";
		else{
			$respOk = Customer::updateCustomerRecord($f);
			if (!$respOk)
				$message = 'update customer table failed';
		}
	}elseif ($type == 'contact'){
		if (empty($f['contact']))
			$message = "The field Contact does not have to be null";
		else
			$respOk = Customer::updateContactRecord($f);
	}else{
		$message = 'error: no current type set';
	}

	if(!$message){
		if($respOk){
			$html = createGrid(0,ROWSXPAGE);
			$objResponse->addAssign("grid", "innerHTML", $html);
			$objResponse->addAssign("msgZone", "innerHTML", "A record has been updated");
			$objResponse->addAssign("formEditInfo", "style.visibility", "hidden");
		}else{
			$objResponse->addAssign("msgZone", "innerHTML", "The record could not be updated");
		}
	}else{
		$objResponse->addAlert($message);
	}
	
	return $objResponse->getXML();
}

function updateField($table, $field, $cell, $value, $id){
	$objResponse = new xajaxResponse();
	$objResponse->addAssign($cell, "innerHTML", $value);

	Customer::updateField($table,$field,$value,$id);
	return $objResponse->getXML();
}

function editField($table, $field, $cell, $value, $id){
	$objResponse = new xajaxResponse();
	
	$html =' <input type="text" id="input'.$cell.'" value="'.$value.'" size="'.(strlen($value)+5).'"'
			.' onBlur="xajax_updateField(\''.$table.'\',\''.$field.'\',\''.$cell.'\',document.getElementById(\'input'.$cell.'\').value,\''.$id.'\');"'
			.' style="background-color: #CCCCCC; border: 1px solid #666666;">';
	$objResponse->addAssign($cell, "innerHTML", $html);
	$objResponse->addScript("document.getElementById('input$cell').focus();");
	return $objResponse->getXML();
}

function add($callerid = null,$customerid = null,$contactid = null){
	global $locate;
	$objResponse = new xajaxResponse();
//	return $objResponse;

	$html = Table::Top($locate->Translate("add_record"),"formDiv");  // <-- Set the title for your form.
//	$html .= Customer::formAdd($callerid,$customerid,$contactid);  // <-- Change by your method
	$html .= Customer::formAdd($callerid,$customerid,$contactid);
//	$objResponse->addAlert($callerid);
	// End edit zone
	$html .= Table::Footer();
	$objResponse->addAssign("formDiv", "style.visibility", "visible");
	$objResponse->addAssign("formDiv", "innerHTML", $html);
	
	return $objResponse->getXML();
}

function addDiallist($userexten,$customerid){
	global $locate;
	$objResponse = new xajaxResponse();

	$html = Table::Top($locate->Translate("add_diallist"),"formaddDiallistInfo");  
	$html .= Customer::formDiallistAdd($userexten,$customerid);
	$html .= Table::Footer();	
	$objResponse->addAssign("formaddDiallistInfo", "innerHTML", $html);
	$objResponse->addAssign("formaddDiallistInfo", "style.visibility", "visible");
	//增加读取campaign的js函数
	$objResponse->addScript("setCampaign();");

	return $objResponse->getXML();
}

function showGrid($start = 0, $limit = 1,$filter = null, $content = null, $order = null, $divName = "grid", $ordering = ""){
	
	$html = createGrid($start, $limit,$filter, $content, $order, $divName, $ordering);
	$objResponse = new xajaxResponse();
	$objResponse->addClear("msgZone", "innerHTML");
	$objResponse->addAssign($divName, "innerHTML", $html);
	
	return $objResponse->getXML();
}

/**
*  show edit form
*  @param	id			int			id
*  @param	type		sting		customer/contact/note
*  @return	objResponse	object		xajax response object
*/

function edit($id = null, $type = "note"){
	global $locate;
	// Edit zone
	if ($type == "diallist") {
		$formdiv = 'formeditDiallistInfo';
	}else {
		$formdiv = 'formEditInfo';
	}
	$html = Table::Top($locate->Translate("edit_record"),$formdiv);
	$html .= Customer::formEdit($id, $type);
	$html .= Table::Footer();
   	// End edit zone

	$objResponse = new xajaxResponse();
	$objResponse->addAssign($formdiv, "style.visibility", "visible");
	$objResponse->addAssign($formdiv, "innerHTML", $html);
	return $objResponse->getXML();
}

function showCdr($id,$cdrtype,$start = 0, $limit = 5,$filter = null, $content = null, $order = null, $divName = "formCdr", $ordering = "",$stype = null){
	global $locate;

	if($id != ''){
		$html = Table::Top($locate->Translate("cdr"),"formCdr"); 			
		$html .= Customer::createCdrGrid($id,$cdrtype,$start, $limit,$filter, $content, $stype, $order, $divName, $ordering);	
		$html .= Table::Footer();
		$objResponse = new xajaxResponse();
		$objResponse->addAssign("formCdr", "style.visibility", "visible");
		$objResponse->addAssign("formCdr", "innerHTML", $html);	
		return $objResponse->getXML();
	}
}

function showRecentCdr($id='',$cdrtype,$start = 0, $limit = 5,$filter = null, $content = null, $order = null, $divName = "formRecentCdr", $ordering = "",$stype = null){
	global $locate;
	$html = Table::Top($locate->Translate("RecentCdr"),"formRecentCdr"); 			
	$html .= Customer::createCdrGrid($id,$cdrtype,$start, $limit,$filter, $content, $stype, $order, $divName, $ordering);	
	$html .= Table::Footer();
	$objResponse = new xajaxResponse();
	$objResponse->addAssign("formRecentCdr", "style.visibility", "visible");
	$objResponse->addAssign("formRecentCdr", "innerHTML", $html);	
	return $objResponse->getXML();
}

function showDiallist($userexten,$customerid,$start = 0, $limit = 5,$filter = null, $content = null, $order = null, $divName = "formDiallist", $ordering = "",$stype = null){
	global $locate;
	if($userexten != ''){
		$html = Table::Top($locate->Translate("diallist"),"formDiallist"); 			
		$html .= Customer::createDiallistGrid($userexten,$customerid,$start, $limit,$filter, $content, $stype, $order, $divName, $ordering);	
		$html .= Table::Footer();
		$objResponse = new xajaxResponse();
		$objResponse->addAssign("formDiallist", "style.visibility", "visible");
		$objResponse->addAssign("formDiallist", "innerHTML", $html);	
		return $objResponse->getXML();
	}
}

function showRecords($id,$start = 0, $limit = 5,$filter = null, $content = null, $order = null, $divName = "formRecords", $ordering = "",$stype = null){
	global $locate;

	if($id != ''){
		$html = Table::Top($locate->Translate("Monitors"),"formRecords"); 			
		$html .= Customer::createRecordsGrid($id,$start, $limit,$filter, $content, $order, $divName, $ordering);	
		$html .= Table::Footer();
		$objResponse = new xajaxResponse();
		$objResponse->addAssign("formRecords", "style.visibility", "visible");
		$objResponse->addAssign("formRecords", "innerHTML", $html);	
		return $objResponse->getXML();
	}
}

function searchCdrFormSubmit($searchFormValue='',$numRows,$limit,$id='',$type=''){
		global $locate,$db;
		$objResponse = new xajaxResponse();
		if($searchFormValue == 'recent'){
			$cdrtype = 'recent';
			$divName = "formRecentCdr";
			$html = Table::Top($locate->Translate("RecentCdr"),"formRecentCdr");
		}else{
			$searchField = array();
			$searchContent = array();
			$searchType = array();
			$customerid = $searchFormValue['customerid'];
			$cdrtype = $searchFormValue['cdrtype'];
			$searchContent = $searchFormValue['searchContent'];  //搜索内容 数组
			$searchField = $searchFormValue['searchField'];      //搜索条件 数组
			$searchType =  $searchFormValue['searchType'];		//搜索方式 数组
			$divName = "formCdr";
			$html = Table::Top($locate->Translate("cdr"),"formCdr");
		}
		if($type == "delete"){
			$res = Customer::deleteRecord($id,'account');
			if ($res){
				$html = Customer::createCdrGrid($customerid,$cdrtype,$searchFormValue['numRows'], $searchFormValue['limit'],$searchField, $searchContent, $searchField, $divName, "");
				$objResponse = new xajaxResponse();
				$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("delete_rec")); 
			}else{
				$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("rec_cannot_delete")); 
			}
		}else{
			//if($cdrtype == 'recent')
			//	$html .= Customer::createCdrGrid('',$cdrtype,$numRows, $limit,'', '', '', $searchField[count($searchField)-1], $divName, "",true);
			//else
				$html .= Customer::createCdrGrid($customerid,$cdrtype,$numRows, $limit,$searchField, $searchContent, $searchType, $searchField[count($searchField)-1], $divName, "",true);
		}
		$html .= Table::Footer();
		$objResponse->addClear("msgZone", "innerHTML");
		$objResponse->addAssign($divName, "innerHTML", $html);
		return $objResponse->getXML();
}

function searchDiallistFormSubmit($searchFormValue,$numRows,$limit,$id='',$type=''){
		global $locate,$db;
		$objResponse = new xajaxResponse();
		$searchField = array();
		$searchContent = array();
		$searchType = array();
		$customerid = $searchFormValue['customerid'];
		$userexten = $searchFormValue['userexten'];
		$searchContent = $searchFormValue['searchContent'];  //搜索内容 数组
		$searchField = $searchFormValue['searchField'];      //搜索条件 数组
		$searchType =  $searchFormValue['searchType'];			//搜索方式 数组
		$divName = "formDiallist";
		$html = Table::Top($locate->Translate("diallist"),"formDiallist");
		if($type == "delete"){
			$res = Customer::deleteRecord($id,'diallist');
			if ($res){
				$html .= Customer::createDiallistGrid($userexten,$customerid,$searchFormValue['numRows'], $searchFormValue['limit'],$searchField, $searchContent, $searchType, $searchField, $divName, "");
				$objResponse = new xajaxResponse();
				$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("delete_rec")); 
			}else{
				$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("rec_cannot_delete")); 
			}
		}else{
			$html .= Customer::createDiallistGrid($userexten,$customerid,$numRows, $limit,$searchField, $searchContent, $searchType, $searchField[count($searchField)-1], $divName, "");
		}
		$html .= Table::Footer();
		$objResponse->addClear("msgZone", "innerHTML");
		$objResponse->addAssign($divName, "innerHTML", $html);
		return $objResponse->getXML();
}

function setCampaign($groupid){
	$objResponse = new xajaxResponse();
	$res = Customer::getRecordsByGroupid($groupid,"campaign");
	//添加option
	while ($res->fetchInto($row)) {
		$objResponse->addScript("addOption('campaignid','".$row['id']."','".$row['campaignname']."');");
	}
	return $objResponse;
}

function saveDiallist($f,$userexten = '',$customerid = ''){
	global $locate;
	$objResponse = new xajaxResponse();
	if($f['campaignid'] == ''){
		$objResponse->addAlert($locate->Translate("Must select a campaign"));
		return $objResponse->getXML();
	}

	// check if the assign number belong to this group
	if ($_SESSION['curuser']['usertype'] != 'admin'){
		$flag = false;
		if($_SESSION['curuser']['usertype'] == 'groupadmin'){
			foreach ($_SESSION['curuser']['memberExtens'] as $extension){
				if ($extension == $f['assign']){
					$flag = true; 
					break;
				}
			}
		}else{
			if($_SESSION['curuser']['extension'] == $f['assign']){
				$flag = true;
			}
		}

		if (!$flag){
			$objResponse->addAlert('"'.$locate->Translate("Cant insert, please confirm the assign number is in your group").'"');
		}
	}
	if ($userexten != ''){
		$id = Customer::insertNewDiallist($f);
		$html = Table::Top($locate->Translate("diallist"),"formDiallist");
		$html .= Customer::createDiallistGrid($userexten,$customerid,0,ROWSXPAGE);
		$html .= Table::Footer();
		$objResponse->addAssign("formDiallist", "innerHTML", $html);
		$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("diallist_added"));
		$objResponse->addAssign("formaddDiallistInfo", "style.visibility", "hidden");
		$objResponse->addClear("formaddDiallistInfo", "innerHTML");
	}else {
		$id = Customer::updateDiallistRecord($f);
		$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("diallist_updated"));
		$objResponse->addAssign("formeditDiallistInfo", "style.visibility", "hidden");
		$objResponse->addClear("formeditDiallistInfo", "innerHTML");
	}
	return $objResponse->getXML();
}

function searchRecordsFormSubmit($searchFormValue,$numRows,$limit,$id='',$type=''){
	global $locate,$db;
	$objResponse = new xajaxResponse();
	$searchField = array();
	$searchContent = array();
	$searchType = array();
	$customerid = $searchFormValue['customerid'];
	$searchContent = $searchFormValue['searchContent'];  //搜索内容 数组
	$searchField = $searchFormValue['searchField'];      //搜索条件 数组
	$searchType =  $searchFormValue['searchType'];			//搜索方式 数组
	$divName = "formRecords";
	$html = Table::Top($locate->Translate("Monitors"),"formRecords");
	if($type == "delete"){
		$res = Customer::deleteRecord($id,'account');
		if ($res){
			$html = Customer::createRecordsGrid($customerid,$searchFormValue['numRows'], $searchFormValue['limit'],$searchField, $searchContent, $searchField, $divName, "");
			$objResponse = new xajaxResponse();
			$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("delete_rec")); 
		}else{
			$objResponse->addAssign("msgZone", "innerHTML", $locate->Translate("rec_cannot_delete")); 
		}
	}else{
		$html .= Customer::createRecordsGrid($customerid,$numRows, $limit,$searchField, $searchContent, $searchType, $searchField[count($searchField)-1], $divName, "",true);
	}
	$html .= Table::Footer();
	$objResponse->addClear("msgZone", "innerHTML");
	$objResponse->addAssign($divName, "innerHTML", $html);
	return $objResponse->getXML();
}

function playmonitor($id){
	global $config,$locate;
	$objResponse = new xajaxResponse();
	$res = Customer::getRecordByID($id,'monitorrecord');
	$path = "./monitor/".str_replace($config['asterisk']['monitorpath'],'',$res['filename']).".wav";
	$html = Table::Top($locate->Translate("playmonitor"),"formplaymonitor");
	$html .= '<embed src="'.$path.'" autostart="true" width="300" height="40" name="sound" id="sound" enablejavascript="true">';
	$html .= Table::Footer();
	$objResponse->addAssign("formplaymonitor", "style.visibility", "visible");
	$objResponse->addAssign("formplaymonitor", "innerHTML", $html);	
	return $objResponse->getXML();
}

function showWorkoff(){
	global $locate;
		$html = Table::Top($locate->Translate("Work Off"),"formWorkoff"); 			
		$html .= '
			<!-- No edit the next line -->
			<form method="post" name="workoff" id="workoff">			
			<table border="1" width="100%" class="adminlist">
			<tr>
				<td nowrap align="right">'.$locate->Translate("User Name").'&nbsp;&nbsp;</td>
				<td nowrap align="left"><input type="text" id="adminname" name="adminname" size="25" maxlength="25"> </td>
			</tr>
			<tr>
				<td nowrap align="right">'.$locate->Translate("Password").'&nbsp;&nbsp;</td>
				<td nowrap align="left"><input type="password" id="Workoffpwd" name="Workoffpwd" size="25" maxlength="25"> </td>
			</tr>			
			<tr><td colspan="2" align="center"><input type="button" id="btnAddDiallist" name="btnAddDiallist" value="'.$locate->Translate("continue").'" onclick="xajax_workoffcheck(xajax.getFormValues(\'workoff\'));return false;"></td></tr>
			</table></form>';
		$html .= Table::Footer();
		$objResponse = new xajaxResponse();
		$objResponse->addAssign("formWorkoff", "style.visibility", "visible");
		$objResponse->addAssign("formWorkoff", "innerHTML", $html);	
		return $objResponse->getXML();
}
?>