<?php
/*******************************************************************************
* manager.common.php
* manager.customer参数信息文件
* manaer.customer parameter file
* 功能描述
* Function Desc

* Revision 0.0442  2007/09/14 15:25:00  modified by solo
* Desc: page create
* 描述: 页面建立

********************************************************************************/

header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: post-check=0, pre-check=0',false);
header('Pragma: no-cache');
session_cache_limiter('public, no-store');

session_set_cookie_params(0);
if (!session_id()) session_start();
setcookie('PHPSESSID', session_id());


if ($_SESSION['curuser']['extension'] == '') 
	header("Location: login.php");


define(LOG_ENABLED, 1); // Enable debuggin
define(FILE_LOG, "/tmp/xajaxDebug.log");  // File to debug.
define(ROWSXPAGE, 10); // Number of rows show it per page.
define(MAXROWSXPAGE, 25);  // Total number of rows show it when click on "Show All" button.
require_once ("include/xajax.inc.php");
require_once ('include/Localization.php');

$GLOBALS['locate']=new Localization($_SESSION['curuser']['country'],$_SESSION['curuser']['language'],'manager');

$xajax = new xajax("manager.customer.server.php");
//print_r($_SESSION['curuser']['extensions']);

$xajax->registerFunction("init");
?>
