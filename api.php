<?php

/*
 * market-cpa-api dealer-with
 * v. 0.2 | https://github.com/Eternity-Yarr/market-cpa-api/
 *
 * CC0 1.0 license.
 */

require_once dirname(__FILE__).'/logging-log4php/src/main/php/Logger.php';
Logger::configure(dirname(__FILE__).'/log4php.xml');
$log = Logger::getLogger("api");

include(dirname(__FILE__).'/config.inc.php');  // Organization specific stuff
include(dirname(__FILE__).'/dbconn.php');  // Host specific thingies

include(dirname(__FILE__).'/classes/dbo.class.php');
include(dirname(__FILE__).'/classes/ems.class.php');

// Bitrix implementation of abstract class dbo
include(dirname(__FILE__).'/classes/bitrix.class.php');

include(dirname(__FILE__).'/lang/api.lang.php'); 
// Translation trait

spl_autoload_register(function ($class) {
    include dirname(__FILE__).'/classes/geo/' . $class . '.class.php';
});

// Bounding polygon for geocoding of delivery zone
// extracted from yandex maps with this call : ymaps.map.instance.geoObjects.each(function(data){console.log(JSON.stringify(data.geometry.getCoordinates()))});
$poly = "[[37.53802012109376,55.95921043024463],[37.665736185546876,55.94842376358383],[37.804438578125,55.88827135321109],[37.93902109765624,55.787808910221806],[37.94863413476557,55.71733001256884],[37.91155527734376,55.66458094292555],[37.91155527734376,55.640509849291334],[37.79070566796875,55.54952692367767],[37.698695169921876,55.529279714598566],[37.60805796289062,55.515256281541234],[37.45424936914061,55.56353808917801],[37.38421152734375,55.614869448085756],[37.33202646874999,55.69484282632032],[37.321040140625,55.75607051048049],[37.33065317773436,55.80250815028393],[37.35811899804687,55.858932367240634],[37.415797220703126,55.91450315891147],[37.48583506249999,55.94996490075033],[37.53802012109376,55.95921043024463]]";
$polygon = new Polygon($poly);
$geoCoder = new GeoCode();


class Market_API_v2 {
    use Market_API_v2_russian;

    private $baseurl = 'https://api.partner.market.yandex.ru/v2/';

    // Override this nonsense values with your own
    private $campaignId = 'my_campaign';
    private $cc_key = 'my_key';
    private $cc_secret = 'my_secret';
    private $token ='my_token';
    private $login ='mylogin';
    private $auth_token = 'token from partner interface';

    private $initial_status = 'PROCESSING';

    // hardcoded payment methods for current implementation
    private $paymentMethods = array(array("CASH_ON_DELIVERY", "SHOP_PREPAID", "YANDEX"), array("SHOP_PREPAID", "YANDEX"));

    // limit cash_on_delivery method for orders with total price less than this threshold
    private $payment_threshold = 90000;

    // Delivery prices. We assume that customer buys items w expensive delivery, 500 RUR.
    private $std_delivery = 190;
    // If there's any items are present in cart, that delivered w special price - falling back to this option;
    private $special_delivery = 190;
    private $expensive_delivery = 190;
    public $page = 1;


    function __construct($key, $secret, $token, $campaignId, $login, $auth) {
	$this->cc_key = $key;
	$this->cc_secret = $secret;
	$this->token = $token;
	$this->campaignId = $campaignId;
	$this->login = $login;
	$this->auth_token = $auth;

    }

    // Possible transitions:

    public $TRANSITIONS = array(

    'PROCESSING' => array('DELIVERY', 'CANCELLED'),
    'DELIVERY' => array( 'DELIVERED', 'CANCELLED'),
    'PICKUP' => array('DELIVERED', 'CANCELLED'));

    public $SUBSTATUS_CHOICES = array(

    'PROCESSING' => array('USER_UNREACHABLE','USER_CHANGED_MIND','USER_REFUSED_DELIVERY','USER_REFUSED_PRODUCT', 'SHOP_FAILED', 'REPLACING_ORDER'),
    'DELIVERY' 	 => array('USER_UNREACHABLE','USER_CHANGED_MIND','USER_REFUSED_DELIVERY','USER_REFUSED_PRODUCT', 'USER_REFUSED_QUALITY', 'SHOP_FAILED'),
    'PICKUP'  	 => array('USER_UNREACHABLE','USER_CHANGED_MIND','USER_REFUSED_DELIVERY','USER_REFUSED_PRODUCT', 'USER_REFUSED_QUALITY', 'SHOP_FAILED'));

    private function curl_oauth_exec($url, $put=false, $put_json=false) {
        $ch = curl_init();
	$httpheader = array('Authorization:
		OAuth
		oauth_token="'.$this->token.'",
		oauth_client_id="'.$this->cc_key.'",
		oauth_login="'.$this->login.'"');
	if (($put) AND ($put_json)) {
	    $httpheader[] = 'Content-Type: application/json';
	    $httpheader[] = 'Content-Length: ' . strlen($put_json);
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
	    curl_setopt($ch, CURLOPT_POSTFIELDS,$put_json);
	}
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader );
	curl_setopt($ch, CURLOPT_URL, $url );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	return curl_exec($ch);
    }

    function error_400($db=false){
	header('HTTP/1.0 400 Bad Request');
	if ($db) $db->close();
	exit();
    }


    function error_500($db=false){
	header('HTTP/1.0 500 Internal Server Error');
	if ($db) $db->close();
	exit();
    }


    function ok_200($output){
	header("HTTP/1.1 200 OK \r\n");
        header("Content-Type: application/json;charset=utf-8\r\n");
	$encoded = json_encode($output,JSON_UNESCAPED_UNICODE);
	file_put_contents('/tmp/cpa.log', "OUTBOUND --- ".date(DATE_RFC2822).PHP_EOL.$encoded.PHP_EOL.PHP_EOL, FILE_APPEND);
        echo ($encoded);
    }

    function POST_Cart($db, $data){
    //  implementation of  POST /cart call
    //  http://api.yandex.ru/market/partner/doc/dg/reference/post-cart.xml
    //  recieves json_decoded object and db link
    //  returns well formed array, for further json_encode and ouput
    //  or HTTP 500 error

    //FIXME: dependency!
    global $geoCoder;
    global $polygon;

	// Proper response structure
	$res = array('cart' => array('items' => array(), 'deliveryOptions' => array(), 'paymentMethods' => array()));
	$outlets = $db->outlets;
	$delivery_flag = true;
	foreach ($data->cart->items as $item) { 
	    $xs = $db->inStockForDelivery($item->offerId);
	    $delivery_flag = $delivery_flag && $db->inStock($item->offerId);
	    foreach($outlets as $x) {
		if (!in_array($x,$xs)) unset($outlets[array_search($x,$outlets)]);
	    }
	}
	
	$grand_total = 0;
        foreach ($data->cart->items as $item) {
	    if ($price = $db->getPrice($item->offerId)) {
		$grand_total += $item->count * $price;
	    }
	}

	$delivery_price = $this->std_delivery;
	$expensive_groups = $db->getExpensiveGroups();
	$city_not_found = false;
	$city_name = $geoCoder->extractCity($data);
        $city_box = $geoCoder->box($city_name);
        $in_polygon = $city_box->inside($polygon);
	if (($data->cart->delivery->region->id == 213) OR ($in_polygon))   {     // 213 == Moscow city 

        if($data->cart->delivery->region->id != 213)
	    $base_delivery = 500;
        else
	    $base_delivery =$delivery_price;

	if (count($outlets)>0) {
	    $outlets_list = array();
	    foreach ($outlets as $outlet) {
		$outlets_list[] = array('id' => $outlet);
	    }
	    $res['cart']['deliveryOptions'][] = array(
		'type'		=> 'PICKUP',
		'serviceName'   => 'Самовывоз',
		'price'		=> 0,
		'dates'		=> array ( 'fromDate' => date('d-m-Y', time()),'toDate' => date('d-m-Y', time()+24*60*60)),
		'outlets'	=> $outlets_list );
	}
	if ($grand_total < $this->payment_threshold) 
	{
	    $res['cart']['paymentMethods'] = $this->paymentMethods[0];
	} 
	else  {$res['cart']['paymentMethods'] = $this->paymentMethods[1];}
	if ($delivery_flag)
	    $res['cart']['deliveryOptions'][] = array(
		'type' => 'DELIVERY',
		'serviceName' => 'Собственная служба доставки',
		'price' => $base_delivery,
		'dates' => array ('fromDate' => date('d-m-Y', time() + 24*60*60))); 		//  Hardcoded for "tomorrow"
    } else {
    $ems = new EMSDelivery();
    $ems_regions = $ems->emsGetLocations(EMS::emsRussia);
    if (is_object($data->cart->delivery->region->parent)) $sub = $data->cart->delivery->region->parent;
    while ($sub) {
    $upper = mb_convert_case($sub->name, MB_CASE_UPPER, "UTF-8");

    if (isset($ems_regions[$upper])) {
	$dest = $ems_regions[$upper]; 
	}
    $sub = ((is_object($sub)) && (isset($sub->parent)) && (is_object($sub->parent))) ? $sub->parent : false;
	
    }

    $res['cart']['paymentMethods'] = $this->paymentMethods[1];
    $weight = 0;
    foreach ($data->cart->items as $item) { $weight += $db->getWeight($item->offerId)*$item->count; }

    if ($weight > $ems->emsGetMaxWeight()) $terms = false; else  $terms = $ems->emsCalculate($dest,$weight);
    
    if ($terms) 
	{ $price = round($terms['price']);
	  $eta_min = $terms['min'];
	  $eta_max = $terms['max'];
        $res['cart']['deliveryOptions'][] = array(
	    'type' => 'POST',
	    'serviceName' => 'EMS Почта России',
	    'price' => $price,
	    'dates' => array('fromDate' => date('d-m-Y', time() + $eta_min*24*60*60),'toDate' => date('d-m-Y', time() + $eta_max*24*60*60)));
    } else {
    unset($res['cart']['deliveryOptions']);
    $city_not_found = true;
    $res['cart']['deliveryOptions'][] = array(
		'type'		=> 'PICKUP',
		'serviceName'   => 'Самовывоз',
		'price'		=> 0,
		'dates'		=> array ( 'fromDate' => date('d-m-Y', time()),'toDate' => date('d-m-Y', time()+24*60*60)),
		'outlets'	=> array(array('id' => array_values($db->outlets)[0])));
     }
    } 

    foreach ($data->cart->items as $item) {
	    if (($stock = $db->inStock($item->offerId)) and (!$city_not_found)) $delivery = true; else {
		$delivery = false;
	    } 
	    if (!$stock) $stock = array();

	    $outlets = array_intersect($outlets, $stock);
	    if (!in_array($item->feedCategoryId, $expensive_groups)) { $delivery_price = $this->special_delivery; }
	    if ($price = $db->getPrice($item->offerId)) {
		$grand_total += $item->count * $price;
		$res['cart']['items'][] =
		    array(	'feedId'	=> $item->feedId,
				'offerId'	=> $item->offerId,
				'price'		=> (int)$price,
				'count'		=> $item->count,
				'delivery'	=> $delivery );
	    }
	    else error_500($db);
	}

	return $res;
    }


    function POST_OrderAccept($db, $data){
    //
    // implementation of POST order/accept request
    // http://api.yandex.ru/market/partner/doc/dg/reference/post-order-accept.xml
    // takes json_decoded object and db link
    // returns well formed array, for further json_encode and ouput
    // or false
    //

    // Proper response structure
        $res = array('order'=> array('id' => "0", 'accepted' => false));

        if ($success = $db->addOrder(
		$data->order->id,
		$data->order,
		$this->initial_status,
		$data->order->fake == 1 ? 1 : 0 )) {
	    $res['order']['id'] = (string)$data->order->id;
	    $res['order']['accepted'] = true;
	    return $res;
	} else {
	    return false;
	}
    }

    function POST_OrderStatus($db, $data){
    //
    // implementation of POST order/status request
    // http://api.yandex.ru/market/partner/doc/dg/reference/post-order-status.xml
    // takes json_decoded object and db link
    // returns true in case of success or false otherwise
    // also logs history (if needed)
    //
	$db->saveHistory(
	    $data->order->id,
	    $data->order->status,
	    $data->order);

	if ($res = $db->setStatus(
	    $data->order->id,
	    $data->order->status,
	    $data->order))
	    return true;
	else return false;
	}

    function PUT_OrderStatus($db,$id,$status,$substatus, $baseurl){
    //
    // implementation of PUT order/status request
    // http://api.yandex.ru/market/partner/doc/dg/reference/put-campaigns-id-orders-id-status.xml
    // takes db link, id of order, new status and baseurl for further redirection
    // redirects 301 to orders list page, or error 500 otherwise
    // logs history if needed
    //
	$put_json = array("order"=> array("status" => $status));
	if ($status == 'CANCELLED') $put_json['order']['substatus'] = $substatus;

	$url = $this->baseurl.'campaigns/'.$this->campaignId.'/orders/'.$id.'/status.json';
	$res = $this->curl_oauth_exec($url, true, json_encode($put_json,JSON_UNESCAPED_UNICODE));

	if ($body = json_decode($res)) {
	    $db->saveHistory($id, $status, $body->order);
	    $db->setStatus($id, $status, $body->order);
	} else $this->error_500($db);

	header("HTTP 1.0 301 Moved Permanently");
	header("Location: ".$baseurl."/orders");
    }

    function PUT_DeliveryMethod($db){
	$this->ni_501($db);
    }


    function GET_Orders($debug = false)  {
	$url = $this->baseurl.'campaigns/'.$this->campaignId.'/orders.json?pageSize=50&page='.$this->page;
	$ret = $this->curl_oauth_exec($url);
	return $ret;
    }

    function GET_Order($orderId){
	$url = $this->baseurl.'campaigns/'.$this->campaignId.'/orders/'.$orderId.'.json';
	$ret = $this->curl_oauth_exec($url);

	return $ret;
    }



    function ni_501($db){
	header("HTTP/1.0 501 Not implemented");
	if ($db) $db->close();
	exit();
    }


    function validate_auth(){
	$headers = getallheaders();
	if (
	(!isset($headers['Authorization']))
	// There's no such field in headers
	OR
	// field is present, but does not match proper value
	((isset($headers['Authorization'])) AND ($headers['Authorization']!= $this->auth_token)))  {
	header('HTTP/1.0 403 Unauthorized');
	die();
	} else return true;
    }
}

$api = new Market_API_v2($cc_key, $cc_secret, $token, $campaignId, $login, $auth);

// Some bitrix specific API thingies
// those credentials variables are system-wide-set in dbconn.php include

$db = new dbo_bitrix($DBLogin,$DBPassword, $DBName);

$route = isset($_GET['route']) ? $_GET['route'] : '';
if (strpos($route,"/page/")) {
    $api->page = (int)substr($route,strpos($route,"/page/")+6);
    $route = substr($route,0,strpos($route,"/page"));
}
file_put_contents('/tmp/cpa.log',"INBOUUND --- ".date(DATE_RFC2822).PHP_EOL.$HTTP_RAW_POST_DATA.PHP_EOL."----".PHP_EOL, FILE_APPEND);
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