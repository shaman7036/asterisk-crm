<?php

session_start();
if (!isset($_SESSION['curuser'])) header("Location: login.php");
if (!isset($_SESSION['curid']) && $_SESSION['curid'] =='' ) $_SESSION['curid']=0;

require_once ("include/xajax.inc.php");
require_once ('include/Localization.php');

define(LOG_ENABLED, 1); // Enable debuggin
define(FILE_LOG, "/tmp/xajaxDebug.log");  // File to debug.
define(ROWSXPAGE, 5); // Number of rows show it per page.
define(MAXROWSXPAGE, 25);  // Total number of rows show it when click on "Show All" button.

$_SESSION['curuser']['country'] = 'en';
$_SESSION['curuser']['language'] = 'US';

$GLOBALS['locate']=new Localization($_SESSION['curuser']['country'],$_SESSION['curuser']['language'],'portal');

$xajax = new xajax("portal.server.php");
//$xajax->debugOn();
$xajax->registerFunction("myEvents");
$xajax->registerFunction("listenCalls");
$xajax->registerFunction("showGrid");
$xajax->registerFunction("add");
$xajax->registerFunction("edit");
$xajax->registerFunction("delete");
$xajax->registerFunction("save");
$xajax->registerFunction("update");
$xajax->registerFunction("editField");
$xajax->registerFunction("updateField");
$xajax->registerFunction("confirmCustomer");
$xajax->registerFunction("confirmContact");
$xajax->registerFunction("showCustomer");
$xajax->registerFunction("showContact");
$xajax->registerFunction("showNote");
$xajax->registerFunction("dial");
$xajax->registerFunction("transfer");
$xajax->registerFunction("init");

//$xajax->processRequests();

//$xajax->processRequests();
?>