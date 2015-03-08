<?php

/*
 * market-cpa-api dealer-with
 * v. 0.2 | https://github.com/Eternity-Yarr/market-cpa-api/
 *
 * CC0 1.0 license.
 */

require_once dirname(__FILE__).'/logging-log4php/src/main/php/Logger.php';
Logger::configure(dirname(__FILE__).'/log4php.xml');
$log = Logger::getLogger("root");
$requestsLog = Logger::getLogger("requests");

spl_autoload_register(
  function ($class) {
  	$baseDir = dirname(__FILE__).'/classes/';
	if(file_exists($baseDir.'geo/' . $class . '.class.php')) {
    	include $baseDir.'geo/' . $class . '.class.php'; 
	} else if(file_exists($baseDir.'model/' . $class . '.class.php')) {
		include $baseDir.'model/' . $class . '.class.php'; 
	} else {
		throw new Exception("Class ".$class." loading failed!");
	}
  }
);

include(dirname(__FILE__).'/config.inc.php');  // Organization specific stuff
include(dirname(__FILE__).'/dbconn.php');  // Host specific thingies

include(dirname(__FILE__).'/classes/dbo.class.php');
include(dirname(__FILE__).'/classes/ems.class.php');

// Bitrix implementation of abstract class dbo
include(dirname(__FILE__).'/classes/bitrix.class.php');

include(dirname(__FILE__).'/lang/api.lang.php'); 
// Translation trait

include(dirname(__FILE__).'/classes/market.class.php');

// Bounding polygon for geocoding of delivery zone
// extracted from yandex maps with this call : ymaps.map.instance.geoObjects.each(function(data){console.log(JSON.stringify(data.geometry.getCoordinates()))});
$poly = "[[37.53802012109376,55.95921043024463],[37.665736185546876,55.94842376358383],[37.804438578125,55.88827135321109],[37.93902109765624,55.787808910221806],[37.94863413476557,55.71733001256884],[37.91155527734376,55.66458094292555],[37.91155527734376,55.640509849291334],[37.79070566796875,55.54952692367767],[37.698695169921876,55.529279714598566],[37.60805796289062,55.515256281541234],[37.45424936914061,55.56353808917801],[37.38421152734375,55.614869448085756],[37.33202646874999,55.69484282632032],[37.321040140625,55.75607051048049],[37.33065317773436,55.80250815028393],[37.35811899804687,55.858932367240634],[37.415797220703126,55.91450315891147],[37.48583506249999,55.94996490075033],[37.53802012109376,55.95921043024463]]";

$api = new Market_API_v2($cc_key, $cc_secret, $token, $campaignId, $login, $auth, new GeoCode(), new Polygon($poly));

// Some bitrix specific API thingies
// those credentials variables are system-wide-set in dbconn.php include

$db = new dbo_bitrix($DBLogin,$DBPassword, $DBName);

$route = isset($_GET['route']) ? $_GET['route'] : '';
if (strpos($route,"/page/")) {
    $api->page = (int)substr($route,strpos($route,"/page/")+6);
    $route = substr($route,0,strpos($route,"/page"));
}

if($HTTP_RAW_POST_DATA) {
	$requestsLog->info("--- INBOUND ---".PHP_EOL.$HTTP_RAW_POST_DATA.PHP_EOL."---");
}

switch ($route) {

    case 'cart':
	$api->validate_auth();  // validating Authorization token in headers
	$data = json_decode($HTTP_RAW_POST_DATA);
	if ($data === NULL) { $api->error_400($db); }  // If no data recieved - blame yandex.
	$output = $api->POST_cart($db, $data);
	$api->ok_200($output);
	break;

    case 'order/accept':
	$api->validate_auth();  // validating Authorization token in headers
	$data = json_decode($HTTP_RAW_POST_DATA);
	if ($data === NULL) { $api->error_400($db); }  // If no data recieved - blame yandex.
	$output = $api->POST_OrderAccept($db,$data);
	if ($output) $api->ok_200($output); else $api->error_500($db);
	break;

    case 'order/status':
	$api->validate_auth();  // validating Authorization token in headers
	$data = json_decode($HTTP_RAW_POST_DATA);
	if ($data === NULL) { $api->error_400($db); }  // If no data recieved - blame yandex.
	if ($output = $api->POST_OrderStatus($db,$data)) $api->ok_200(""); else $api->error_500($db);
	break;

    case 'orders':
	$str = $api->GET_Orders();
	$response = json_decode($str);
	if ($response)  {
	$orders = $response->orders;
	include('market.tpl.php');
	} else {
	 trigger_error("No can parse response:".$str, E_USER_ERROR);
	}
	break;

    case 'put/status':
	if (((in_array($_POST['new_status'], array_keys($api->STATUS))) AND ($_POST['new_status'] != 'CANCELLED')) 
	    OR
	    (($_POST['new_status']=='CANCELLED') AND (in_array($_POST['substatus'], array_keys($api->SUBSTATUS)))))
	{
	$api->PUT_OrderStatus($db,(int)$_POST['order_id'], $_POST['new_status'], $_POST['substatus'], $baseurl);
	} else $api->error_500($db);
        break;

    default:
        // If method is not recognized - blame yandex.
        $api->error_400($db);
        break;
}

$db->close();
?>