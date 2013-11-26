<?php

include('config.inc.php');  // Organization specific stuff
include('dbconn.php');  // Host specific thingies

include('dbo.class.php');

// Bitrix implementation of abstract class dbo
include('bitrix.class.php');

class Market_API_v2 {

    private $baseurl = 'https://api.partner.market.yandex.ru/v2/';
    private $campaignId = 'my_campaign';
    private $cc_key = 'my_key';
    private $cc_secret = 'my_secret';
    private $token ='my_token';
    private $login ='mylogin';
    private $auth_token = 'token from partner interface';

    // hardcoded payment methods for current implementation
    private $paymentMethods = array(array("CASH_ON_DELIVERY", "SHOP_PREPAID"), array("SHOP_PREPAID"));

    // limit cash_on_delivery method for orders with total price less than this threshold
    private $payment_threshold = 90000;

    private $std_delivery = 500;
    private $special_delivery = 250;


    function __construct($key, $secret, $token, $campaignId, $login, $auth) {

	$this->cc_key = $key;
	$this->cc_secret = $secret;
	$this->token = $token;
	$this->campaignId = $campaignId;
	$this->login = $login;
	$this->auth_token = $auth;
    }

// statuses

    private $STATUS = array(
    
  'RESERVED' => 
    array(	'Заказ зарезервирован',
		'В резерве'),
  'PROCESSING' => 
    array(	'Заказ находится в обработке',
		'В обработке'),
  'DELIVERY'  =>
    array(	'Заказ передан в доставку',
		'В доставке'),
  'PICKUP' =>
    array(	'Заказ доставлен в пункт самовывоза',
		'В самовывозе'),
  'DELIVERED' =>
    array(	'Заказ получен покупателем',
		'Доставлен'),
  'CANCELLED' => 
    array(	'Заказ отменен',
		'Отменен'));

// substatuses

    private $SUBSTATUS = array(

  'RESERVATION_EXPIRED' =>
    array(	'Покупатель не завершил оформление зарезервированного заказа вовремя',
		'Резерв снят'),
  'USER_NOT_PAID' =>
    array(	'Покупатель не оплатил заказа',
		'Не оплачен'),
  'USER_UNREACHABLE' =>
    array(	'Не удалось связаться с покупателем',
		'Не доступен'),
  'USER_CHANGED_MIND' =>
    array(	'Покупатель отменил заказ по собственным причинам',
		'Передумал'),
  'REFUSED_DELIVERY' =>
    array(	'Покупателя не устраивают условия доставки',
		'Доставка не устраивает'),
  'REFUSED_PRODUCT' => 
    array(	'Покупателю не подошел товар',
		'Товар не устраивает'),
  'REFUSED_QUALITY' =>
    array(	'Покупателя не устраивает качество товара',
		'Качество низкое'),
  'SHOP_FAILED' =>
    array(	'Магазин не может выполнить заказ',
		'Магазин отказался'),
  'REPLACING_ORDER' =>
    array(	'Покупатель изменяет состав заказа',
		'Замена заказа'),
  'PROCESSING_EXPIRED' =>
    array(	'Магазин не обработал заказ вовремя',
		'Не успели обработать'));

    // Possible transitions:

    private $TRANSITIONS = array(

    'PROCESSING' => array('DELIVERY', 'CANCELLED'),
    'DELIVERY' => array('PICKUP', 'DELIVERED', 'CANCELLED'),
    'PICKUP' => array('DELIVERY', 'CANCELLED'));

    private $SUBSTATUS_CHOICES = array(

    'PROCESSING' => array('USER_UNREACHABLE','USER_CHANGED_MIND','USER_REFUSED_DELIVERY','USER_REFUSED_PRODUCT', 'SHOP_FAILED', 'REPLACING_ORDER'),
    'DELIVERY' 	 => array('USER_UNREACHABLE','USER_CHANGED_MIND','USER_REFUSED_DELIVERY','USER_REFUSED_PRODUCT', 'USER_REFUSED_QUALITY', 'SHOP_FAILED'),
    'PICKUP'  	 => array('USER_UNREACHABLE','USER_CHANGED_MIND','USER_REFUSED_DELIVERY','USER_REFUSED_PRODUCT', 'USER_REFUSED_QUALITY', 'SHOP_FAILED'));


    private function curl_oauth_exec($url) {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization:
		OAuth
		oauth_token="'.$this->token.'",
		oauth_client_id="'.$this->cc_key.'",
		oauth_login="'.$this->login.'"'));
	curl_setopt($ch, CURLOPT_URL, $url );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	return curl_exec($ch);
    }


    function POST_Cart($db, $data){

    //  implementation of  POST /cart call
    //  http://api.yandex.ru/market/partner/doc/dg/reference/post-cart.xml
    //  recieves json_decoded object and db link
    //  returns well formed array, for further json_encode and ouput
    //  or HTTP 500 error

	// Proper response structure
	$res = array('cart' => array('items' => array(), 'deliveryOptions' => array(), 'paymentMethods' => array()));
	$outlets = $db->outlets;
	$grand_total = 0;

	foreach ($data->cart->items as $item) {

	if ($stock = $db->inStock($item->offerId)) $delivery = true; else $delivery = false;
	$outlets = array_intersect($outlets, $stock);

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
	if (count($outlets)>0) {
	    $outlets_list = array();
	    foreach ($outlets as $outlet) {
	    $outlets_list[] = array('id' => $outlet);
	    }
	    $res['cart']['deliveryOptions'][] = array(
		'type'		=> 'PICKUP',
		'serviceName'   => 'Самовывоз',
		'price'		=> 0,
		'dates'		=> array ( 'fromDate' => date('d-m-Y', time())),
		'outlets'	=> $outlets_list );
	}
	if ($grand_total < $this->payment_threshold) {
	$res['cart']['paymentMethods'] = $this->paymentMethods[0];
	} else  {
	$res['cart']['paymentMethods'] = $this->paymentMethods[1];}

	$res['cart']['deliveryOptions'][] = array(
	    'type' => 'DELIVERY',
	    'serviceName' => 'Собственная служба доставки',
	    'price' => 250,
	    'dates' => array ('fromDate' => date('d-m-Y', time() + 24*60*60))); 		//  Hardcoded for tomorrow

	return $res;
    }


    function POST_OrderAccept($db, $data){

    // Proper response structure
    $res = array('order'=> array('id' => 0, 'accepted' => false));

    $test_data = '{"order":{"id":4862,"fake":true,"currency":"RUR","delivery":{"type":"PICKUP","price":0,"serviceName":"Самовывоз","dates":{"fromDate":"26-11-2013","toDate":"26-11-2013"},"region":{"id":213,"name":"Москва","type":"CITY","parent":{"id":1,"name":"Москва и Московская область","type":"SUBJECT_FEDERATION","parent":{"id":3,"name":"Центр","type":"COUNTRY_DISTRICT","parent":{"id":225,"name":"Россия","type":"COUNTRY"}}}},"outlet":{"id":87363}},"items":[{"feedId":9997,"offerId":"5695","feedCategoryId":"160","offerName":"Графический планшет WACOM Intuos5 Pro L [PTH-851-RU]","price":20560,"count":1,"delivery":true}],"notes":"примечание"}}';
    $test_data = '{"order":{"id":5001,"fake":true,"currency":"RUR","paymentType":"PREPAID","paymentMethod":"SHOP_PREPAID","delivery":{"type":"DELIVERY","price":250,"serviceName":"Собственная служба доставки","dates":{"fromDate":"27-11-2013","toDate":"27-11-2013"},"region":{"id":158,"name":"Могилёв","type":"CITY","parent":{"id":29629,"name":"Могилёвская область","type":"SUBJECT_FEDERATION","parent":{"id":149,"name":"Беларусь","type":"COUNTRY"}}},"address":{"country":"Беларусь","city":"Могилёв","subway":"Волшебная","street":"11-ая","house":"12","floor":"3"}},"items":[{"feedId":9997,"offerId":"5695","feedCategoryId":"160","offerName":"Графический планшет WACOM Intuos5 Pro L [PTH-851-RU]","price":20560,"count":3,"delivery":true},{"feedId":9997,"offerId":"2770","feedCategoryId":"56","offerName":"Моноблок MSI Wind Top AE2712G-027 (Core i5 3470S 2900 Mhz/27\"/1920x1080/4096Mb/1000Gb/BlueRay/Wi-Fi/Bluetooth/Win 8 ... ","price":36500,"count":1,"delivery":true}],"notes":"примечание"}}';


    print_r(json_decode($test_data));
    return $res;

    }

    function POST_OrderStatus(){}

    function PUT_OrderStatus(){}

    function PUT_DeliveryMethod(){}

    function GET_Orders()  {

	$url = $this->baseurl.'campaigns/'.$this->campaignId.'/orders.json';
	$ret = $this->curl_oauth_exec($url);
	return $ret;

    }

    function GET_Order($orderId){

	$url = $this->baseurl.'campaigns/'.$this->campaignId.'/orders/'.$orderId.'.json';
	$ret = $this->curl_oauth_exec($url);

	return $ret;
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
    echo (json_encode($output,JSON_UNESCAPED_UNICODE));

    }

    function validate_auth(){

    $headers = getallheaders();
    if (
    (!isset($headers['Authorization']))
    // There's no such field in headers
    OR
    // field is present, but does not match proper value
    ((isset($headers['Authorization'])) AND ($headers['Authorization']!= $this->auth_token)))
    {
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

$a = new stdClass();

/*

    Test data , request examples

 $raw_data = '{"cart":{"currency":"RUR","items":[{"feedId":9997,"offerId":"5585","feedCategoryId":"1","offerName":"Ноутбук Sony VAIO FIT 15E SVF1521P1R (Core i5 3337U 1800 Mhz/15.5\"/1366x768/6144Mb/750Gb/DVD-RW/Wi-Fi/Bluetooth/Win 8 64 ... ","count":1}],"delivery":{"region":{"id":213,"name":"Москва","type":"CITY","parent":{"id":1,"name":"Москва и Московская область","type":"SUBJECT_FEDERATION","parent":{"id":3,"name":"Центр","type":"COUNTRY_DISTRICT","parent":{"id":225,"name":"Россия","type":"COUNTRY"}}}}}}}';
 $raw_data = '{"cart":{"currency":"RUR","items":[{"feedId":9997,"offerId":"255","feedCategoryId":"53","offerName":"Планшет Apple iPad 4 64Gb Wi-Fi + Cellular (MD524RU/A MD524TU/A) [MD524RS/A]","count":1}],"delivery":{"region":{"id":213,"name":"Москва","type":"CITY","parent":{"id":1,"name":"Москва и Московская область","type":"SUBJECT_FEDERATION","parent":{"id":3,"name":"Центр","type":"COUNTRY_DISTRICT","parent":{"id":225,"name":"Россия","type":"COUNTRY"}}}}}}}';
 $raw_data ='{"cart":{"items":[{"feedId":9997,"offerId":"5695","price":20560,"count":1,"delivery":true},{"feedId":9997,"offerId":"5108","price":28950,"count":1,"delivery":true},{"feedId":9997,"offerId":"2770","price":36500,"count":1,"delivery":true}],"deliveryOptions":[{"type":"DELIVERY","serviceName":"Собственная служба доставки","price":250,"dates":{"fromDate":"27-11-2013"}}],"paymentMethods":["CASH_ON_DELIVERY","SHOP_PREPAID"]}}';
 $raw_data ='{"cart":{"items":[{"feedId":9997,"offerId":"5695","price":20560,"count":1,"delivery":true},{"feedId":9997,"offerId":"5108","price":28950,"count":1,"delivery":true}],"deliveryOptions":[{"type":"DELIVERY","serviceName":"Собственная служба доставки","price":250,"dates":{"fromDate":"27-11-2013"}}],"paymentMethods":["CASH_ON_DELIVERY","SHOP_PREPAID"]}}';

*/

 $test_data = '{"order":{"id":5001,"fake":true,"currency":"RUR","paymentType":"PREPAID","paymentMethod":"SHOP_PREPAID","delivery":{"type":"DELIVERY","price":250,"serviceName":"Собственная служба доставки","dates":{"fromDate":"27-11-2013","toDate":"27-11-2013"},"region":{"id":158,"name":"Могилёв","type":"CITY","parent":{"id":29629,"name":"Могилёвская область","type":"SUBJECT_FEDERATION","parent":{"id":149,"name":"Беларусь","type":"COUNTRY"}}},"address":{"country":"Беларусь","city":"Могилёв","subway":"Волшебная","street":"11-ая","house":"12","floor":"3"}},"items":[{"feedId":9997,"offerId":"5695","feedCategoryId":"160","offerName":"Графический планшет WACOM Intuos5 Pro L [PTH-851-RU]","price":20560,"count":3,"delivery":true},{"feedId":9997,"offerId":"2770","feedCategoryId":"56","offerName":"Моноблок MSI Wind Top AE2712G-027 (Core i5 3470S 2900 Mhz/27\"/1920x1080/4096Mb/1000Gb/BlueRay/Wi-Fi/Bluetooth/Win 8 ... ","price":36500,"count":1,"delivery":true}],"notes":"примечание"}}';

switch ($route) {

case 'cart':
$api->validate_auth();  // validating Authorization token in headers

$data = json_decode($HTTP_RAW_POST_DATA);
if ($data === NULL) { $api->error_400($db); }  // If no data recieved - blame yandex.

$output = $api->POST_cart($db, $data);
$api->ok_200($output);
break;

case 'order/accept':
// $api->validate_auth();  // validating Authorization token in headers

$data = json_decode($test_data);
// $data = json_decode($HTTP_RAW_POST_DATA);
if ($data === NULL) { $api->error_400($db); }  // If no data recieved - blame yandex.
$output = $api->POST_OrderAccept($db,$data);

break;

case 'order/status':
$api->validate_auth();  // validating Authorization token in headers

break;

case '':
break;

default:
// If method is not recognized - blame yandex.

$api->error_400($db);
break;

}



// print_r(json_decode($api->GET_Order(2)));

// $fp = fopen('/var/www-ssl/debug_post.log','a+');
// fwrite($fp, print_r(json_decode($HTTP_RAW_POST_DATA),1));
// fclose($fp);

$db->close();


?>