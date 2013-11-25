<?php

include('config.inc.php');

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


  function POST_Cart(){}

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

$api = new Market_API_v2($cc_key, $cc_secret, $token, $campaignId);

print_r($api->GET_Orders());

?>
