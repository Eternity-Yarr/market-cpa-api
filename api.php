<?php

include('config.inc.php');  // Organization specific stuff
include('dbconn.php');  // Host specific thingies

include('dbo.class.php');

// Bitrix implementation of abstract class dbo
include('bitrix.class.php');

class Market_API_v2{

private $baseurl = 'https://api.partner.market.yandex.ru/v2/';


private $campaignId = 'my_campaign';
private $cc_key = 'my_key';
private $cc_secret = 'my_secret';
private $token ='my_token';

function __construct($key, $secret, $token, $campaignId) {

$this->cc_key = $key;
$this->cc_secret = $secret;
$this->token = $token;
$this->campaignId = $campaignId;

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
	oauth_client_id="d",
	oauth_login="ba"'));
    curl_setopt($ch, CURLOPT_URL, $url );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    return curl_exec($ch);



}


  function POST_Cart($data){

echo "<pre>";
print_r($data);
echo "</pre>";

// Query for store


}

  function POST_OrderAccept(){}

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
}

function error_400(){

header('HTTP/1.0 400 Bad Request');
exit();


}
$api = new Market_API_v2($cc_key, $cc_secret, $token, $campaignId);

// Some bitrix specific API thingies

$db = new dbo_bitrix($DBLogin,$DBPass, $DBName);
$stock =$db->inStock(5585);
print_r(gettype($stock));
$route = isset($_GET['route']) ? $_GET['route'] : '';

$a = new stdClass();

$raw_data = '{"cart":{"currency":"RUR","items":[{"feedId":9997,"offerId":"5585","feedCategoryId":"1","offerName":"Ноутбук Sony VAIO FIT 15E SVF1521P1R (Core i5 3337U 1800 Mhz/15.5\"/1366x768/6144Mb/750Gb/DVD-RW/Wi-Fi/Bluetooth/Win 8 64 ... ","count":1}],"delivery":{"region":{"id":213,"name":"Москва","type":"CITY","parent":{"id":1,"name":"Москва и Московская область","type":"SUBJECT_FEDERATION","parent":{"id":3,"name":"Центр","type":"COUNTRY_DISTRICT","parent":{"id":225,"name":"Россия","type":"COUNTRY"}}}}}}}';

switch ($route) {

case 'cart':
$data = json_decode($HTTP_RAW_POST_DATA);
$data = json_decode($raw_data);
if ($data === NULL) {
error_400();
}
$output = $api->POST_cart($data);
break;

case 'order/accept':
break;

case 'order/status':
break;

case '':
break;

default:
error_400();
break;

}



// print_r(json_decode($api->GET_Order(2)));

// $fp = fopen('/var/www-ssl/debug_post.log','a+');
// fwrite($fp, print_r(json_decode($HTTP_RAW_POST_DATA),1));
// fclose($fp);


?>