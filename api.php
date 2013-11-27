<?php

include('config.inc.php');  // Organization specific stuff
include('dbconn.php');  // Host specific thingies

include('dbo.class.php');

// Bitrix implementation of abstract class dbo
include('bitrix.class.php');

class Market_API_v2 {

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
    private $paymentMethods = array(array("CASH_ON_DELIVERY", "SHOP_PREPAID"), array("SHOP_PREPAID")); 

    // limit cash_on_delivery method for orders with total price less than this threshold
    private $payment_threshold = 90000; 

    // Delivery prices. We assume that customer buys items w expensive delivery, 500 RUR. 
    private $std_delivery = 500;
    // If there's any items are present in cart, that delivered w special price - falling back to this option;
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

    public $DELIVERY = array ( 'DELIVERY' => 'Доставка' , 'PICKUP' => 'Самовывоз' );
    
    public $PAYMENTS = array ( 'CASH_ON_DELIVERY' => 'Курьеру', 'SHOP_PREPAID' => 'Предоплата' );

    public $STATUS = array(
    
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

    public $SUBSTATUS = array(

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
  'USER_REFUSED_DELIVERY' =>
    array(	'Покупателя не устраивают условия доставки',
		'Доставка не устраивает'),
  'USER_REFUSED_PRODUCT' => 
    array(	'Покупателю не подошел товар',
		'Товар не устраивает'),
  'USER_REFUSED_QUALITY' =>
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
	$delivery_price = $this->std_delivery;
	$expensive_groups = $db->getExpensiveGroups();
	foreach ($data->cart->items as $item) {

	if ($stock = $db->inStock($item->offerId)) $delivery = true; else $delivery = false;
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
	if ($grand_total < $this->payment_threshold) {
	$res['cart']['paymentMethods'] = $this->paymentMethods[0];
	} else  {
	$res['cart']['paymentMethods'] = $this->paymentMethods[1];}
	$res['cart']['deliveryOptions'][] = array( 
	    'type' => 'DELIVERY', 
	    'serviceName' => 'Собственная служба доставки', 
	    'price' => $delivery_price, 
	    'dates' => array ('fromDate' => date('d-m-Y', time() + 24*60*60))); 		//  Hardcoded for tomorrow

	return $res;
    }


    function POST_OrderAccept($db, $data){

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

    function PUT_OrderStatus($db,$id,$status,$baseurl){

	$put_json = array("order"=> array("status" => $status));
	$url = $this->baseurl.'campaigns/'.$this->campaignId.'/orders/'.$id.'/status.json';
	$fp = fopen('/var/www-ssl/put.log','a+');
	fwrite($fp, $url);
	$res = $this->curl_oauth_exec($url, true, json_encode($put_json,JSON_UNESCAPED_UNICODE)); 
	fwrite($fp,$res);
	fclose($fp);
	
	if ($body = json_decode($res)) {
	$db->setStatus($id, $status, $body);
	} else $this->error_500($db);

	header("HTTP 1.0 301 Moved Permanently");
	header("Location: ".$baseurl."/orders");
	

    }

    function PUT_DeliveryMethod($db){

	$this->ni_501($db);
    }


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
	$response = json_decode($api->GET_Orders());
	$orders = $response->orders;
	include('market.tpl.php');  
	break;

    case 'put/status':
	if (in_array($_POST['new_status'], array_keys($api->STATUS))) {
	$api->PUT_OrderStatus($db,(int)$_POST['order_id'], $_POST['new_status'], $baseurl);
	} else $api->error_500($db);
        break;

    default:
        // If method is not recognized - blame yandex.
        $api->error_400($db);
        break;
}

$db->close();


?>