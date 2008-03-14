<?
/*******************************************************************************
* astercc.class.php
* asterisk events class, read datas from table: curcdr
**/

class astercc extends PEAR
{
	function generatePeers(){
		global $db,$config;
		$query = "SELECT * FROM clid ORDER BY clid ASC";
		$clid_list = $db->query($query);
		$content = '';
		while	($clid_list->fetchInto($row)){
			$content .= "[".$row['clid']."]\n";
			foreach ($config['sipbuddy'] as  $key=>$value){
				if ($key != '' && $value != '')
					$content .= "$key = $value\n";
			}
			$content .= "secret = ".$row['pin']."\n\n";
		}

		$filename = $config['system']['sipfile'];

		$fp=fopen($filename,"w");
		if (!$fp){
			print "file: $filename open failed, please check the file permission";
			exit;
		}
		fwrite($fp,$content);
	}

	function checkPeerStatus($groupid){
		$curChans =& astercc::getCurChan($groupid);
		$status =  array();
		while ($curChans->fetchInto($list)) {
			$status[$list['src']] = $list;
		}
		return $status;
	}

	function creditDigits($credit){
		return number_format($credit,2);
	}

	function setCreditLimit($channel,$creditlimit){
		global $db;
		$query = "UPDATE curcdr SET creditlimit = $creditlimit WHERE srcchan = '$channel'";
		astercc::events($query);
		$res = $db->query($query);
		return $db->affectedRows();
	}

	function setStatus($clid,$status){
		global $db;
		$query = "UPDATE clid SET status = $status, addtime = now()  WHERE clid = '$clid'";
		astercc::events($query);
		$res = $db->query($query);
		//print_r($res);
		//print $query;
		//exit;
		return $db->affectedRows();
	}

	function getCallback($groupid){
		global $db;
		$query = "SELECT * FROM curcdr WHERE groupid = $groupid AND LEFT(srcchan,6) = 'Local/'";
		astercc::events($query);
		$res = $db->query($query);
		return $res;
	}

	function insertNewCallback($f){
		global $db;
		$sql= "INSERT INTO callback SET "
				."lega='".$f['lega']."', "
				."legb='".$f['legb']."', "
				."credit='".$f['credit']."',"
				."groupid='".$f['groupid']."', "
				."addtime= now() ";
		$res =& $db->query($sql);
		astercc::events($query);
		return $res;
	}

	function getCurChan($groupid){
		global $db;
		$query = "SELECT * FROM curcdr WHERE groupid = $groupid";
		#$condition = '';
		#foreach ($peers as $peer){
		#	$condition .= " src = '".$peer."' OR";
		#}
		#$query .= substr($condition,0,-2); // delete the last "AND"
		astercc::events($query);
		$res = $db->query($query);
		return $res;
	}

	function getAll($table,$field = '', $value = ''){
		global $db;
		if (trim($field) != '' && trim($value) != ''){
			$query = "SELECT * FROM $table WHERE $field = '$value' ";
		}else{
			$query = "SELECT * FROM $table ";
		}
		astercc::events($query);
		$res = $db->query($query);
		return $res;
	}

	function getCurLocalChan($chan, $groupid){
		global $db;
		$query = "SELECT * FROM curcdr WHERE srcchan LIKE '$chan%' AND groupid = $groupid ORDER BY starttime ASC";
//		print $query;
//		exit;
		astercc::events($query);
		$res = $db->query($query);
		return $res;
	}

	function events($event = null){
		if(LOG_ENABLED){
			$now = date("Y-M-d H:i:s");
   		$fd = fopen (FILE_LOG,'a');
			$log = $now." ".$_SERVER["REMOTE_ADDR"] ." - $event \n";
	   	fwrite($fd,$log);
   		fclose($fd);
		}
	}

	function readRate($dst,$groupid, $tbl = 'myrate', $secondGroupid = null){
		global $db;
		$dst = trim($dst);
		if ($secondGroupid != null){
			$sql = "SELECT * FROM $tbl WHERE groupid = $secondGroupid";
		}else{
			$sql = "SELECT * FROM $tbl WHERE groupid = $groupid";
		}
		
		astercc::events($sql);
		$rates = & $db->query($sql);

		$maxprefix = '';
		$myrate = array();
		$default = '';

		while ($rates->fetchInto($list)) {
			if ($list['dialprefix'] == 'default'){
				$default = $list;
				continue;
			}

			$prefixlength = strlen($list['dialprefix']);
			if (substr($dst,0,$prefixlength) == $list['dialprefix']){
				if ($prefixlength > strlen($maxprefix)){
					$myrate = $list;
					$maxprefix = $list['dialprefix'];
				}
			}
		}
		

		if ($secondGroupid != null && $maxprefix == '' && $default ==''){ // did get rate from group
			return astercc::readRate($dst,$groupid, $tbl);
		}

		if ($maxprefix == ''){
			return $default;
		}else{
			return $myrate;
		}
	}

	function setBilled($id){
		global $db;
		$sql ="UPDATE mycdr SET userfield ='billed' WHERE id =$id ";
		astercc::events($sql);
		$res = $db->query($sql);
		return $res;
	}

	function readUnbilled($peer,$leg = null,$groupid){
		global $db;

		if ($leg == null){
			$query = "SELECT * FROM mycdr WHERE src = '$peer' AND userfield = 'UNBILLED' AND groupid = $groupid ORDER BY calldate";
		}else{
			/*
			$query = 'SELECT * FROM cdr WHERE 
				src = "'.$peer.' AND dst="'.$leg.'"" 
				AND userfield = "UNBILLED" ORDER BY calldate';
				*/
			$query = "SELECT * FROM mycdr WHERE channel LIKE 'Local/$peer%' AND src = '$leg' AND userfield = 'UNBILLED' AND groupid = $groupid ORDER BY calldate";
			//	print $query;
			//	exit;
		}
		astercc::events($query);
		$res = $db->query($query);
		return $res;
	}

function readAll($resellerid, $groupid, $peer, $sdate = null , $edate = null){
	global $db;
	/*
	if ($peer == 'callback'){
		if ($_SESSION['curuser']['usertype'] == 'admin')
			$query = "SELECT * FROM mycdr WHERE LEFT(channel,6) = 'Local/' ";
		else if ( $_SESSION['curuser']['usertype'] == 'reseller' )
			$query = "SELECT * FROM mycdr WHERE resellerid = ".$_SESSION['curuser']['resellerid']." AND LEFT(channel,6) = 'Local/' ";
		else
			$query = "SELECT * FROM mycdr WHERE groupid = ".$_SESSION['curuser']['groupid']." AND LEFT(channel,6) = 'Local/' ";
	}else{
		if ($_SESSION['curuser']['usertype'] == 'admin')
			$query = "SELECT * FROM mycdr WHERE src LIKE '$peer%' ";
		else if ( $_SESSION['curuser']['usertype'] == 'reseller' )
			$query = "SELECT * FROM mycdr WHERE src LIKE '$peer%' AND resellerid = ".$_SESSION['curuser']['resellerid']." ";
		else
			$query = "SELECT * FROM mycdr WHERE src LIKE '$peer%' AND groupid = ".$_SESSION['curuser']['groupid']." ";
	}
	*/
	
	$query = "SELECT * FROM mycdr WHERE 1 ";

		if ($resellerid != '' and $resellerid != '0'){
				$query .= " AND resellerid = $resellerid ";
		}

		if ($groupid != '' and $groupid != '0'){
				$query .= " AND groupid = $groupid ";
		}

		if ($peer != '' and $peer != '0'){
			if ($peer == 'callback'){
				$query .= " AND LEFT(channel,6) = 'Local/' ";
			}else{
				$query .= " AND src LIKE '$peer%' ";
			}
		}

		if ($sdate != null){
			$query .= " AND calldate >= '$sdate' ";
		}

	if ($edate != null){
		$query .= " AND calldate <= '$edate' ";
	}

	$query .= " ORDER BY calldate";
	astercc::events($query);
	$res = $db->query($query);
	return $res;
}

	function readAmount($id,$peer = null, $sdate = null, $edate = null, $field = 'credit'){
		global $db;
		$curYear = Date("Y");
		$curMonth = Date("m");

		if ($sdate == null){
			//$sdate = "$curYear-$curMonth-01 00:00:00";
		}

		if ($edate == null){
			//$edate = "$curYear-$curMonth-31 23:59:59";
		}

		if ($field == 'resellercredit'){
			if ($peer == null)
				$query = "SELECT SUM($field) FROM mycdr WHERE resellerid = $id ";
			else
				$query = "SELECT SUM($field) FROM mycdr WHERE resellerid = $id ";
		}else{
			if ($peer == null)
				$query = "SELECT SUM($field) FROM mycdr WHERE groupid = $id ";
			else
				$query = "SELECT SUM($field) FROM mycdr WHERE groupid = $id ";
		}

		if ($sdate != null)
			$query .= " AND calldate >= '$sdate' ";

		if ($edate != null)
			$query .= " AND calldate <= '$edate' ";

		astercc::events($query);
		$one = $db->getOne($query);
		return $one;
	}


	function readRateDesc($rate){

		if ($rate['initblock'] != 0){
			$desc .= floor($rate['connectcharge']*100)/100 . ' for first ' . $rate['initblock'] . ' seconds <br/>';
		}
		if ($rate['billingblock'] != 0){
			$desc .= floor(($rate['billingblock'] * $rate['rateinitial'] / 60)*100)/100 . ' per ' . $rate['billingblock'] . ' seconds';
		}
		$desc .= "(".$rate['destination'].")";
		return $desc;
	}

	function readField($table,$field,$identity,$value){
		global $db;
		if (is_numeric($value))
			$query = "SELECT $field FROM $table WHERE $identity = $value";
		else
			$query = "SELECT $field FROM $table WHERE $identity = '$value'";
		$one = $db->getOne($query);
		return $one;
	}

	function readRecord($table,$identity,$value){
		global $db;
		if (is_numeric($value))
			$query = "SELECT * FROM $table WHERE $identity = $value";
		else
			$query = "SELECT * FROM $table WHERE $identity = '$value'";
		$row = $db->getRow($query);
		return $row;
	}

	function calculatePrice($billsec,$rate){

		$destination = $rate['destination'];
		$rateinitial = $rate['rateinitial'];
		$initblock	 = $rate['initblock'];
		$billingblock = $rate['billingblock'];
		
		if ($billsec > 0 ){
	
			$price += $rate['connectcharge'];
			$billsec -= $rate['initblock'];
	

			if ($billsec > 0){
				if ($rate['billingblock'] != 0){
					if ($billsec % $rate['billingblock'] != 0 )
						$billblock = intval($billsec / $rate['billingblock']) + 1;
					else
						$billblock = intval($billsec / $rate['billingblock']);
				}else{
				}
					
				$price += $billblock * ($rate['billingblock'] * $rate['rateinitial']/60);
			}
		}

		return $price;
	}

	function calculateLimitSec($creditLimit,$rate){

		$destination = $rate['destination'];
		$rateinitial = $rate['rateinitial'];
		$initblock	 = $rate['initblock'];
		$billingblock = $rate['billingblock'];
		
		if ($billsec > 0 ){
	
			$price += $rate['connectcharge'];
			$billsec -= $rate['initblock'];
	

			if ($billsec > 0){
				if ($rate['billingblock'] != 0){
					if ($billsec % $rate['billingblock'] != 0 )
						$billblock = intval($billsec / $rate['billingblock']) + 1;
					else
						$billblock = intval($billsec / $rate['billingblock']);
				}else{
				}
					
				$price += $billblock * ($rate['billingblock'] * $rate['rateinitial']/60);
			}
		}

		return $price;
	}
}
?>