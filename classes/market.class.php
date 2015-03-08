<?php
class Market_API_v2 {
    use Market_API_v2_russian;
    private $log;

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

    private $geoCoder;
    private $polygon;


    function __construct($key, $secret, $token, $campaignId, $login, $auth, GeoCode $geoCoder, Polygon $polygon) {
	$this->log = Logger::getLogger("core");
	$this->cc_key = $key;
	$this->cc_secret = $secret;
	$this->token = $token;
	$this->campaignId = $campaignId;
	$this->login = $login;
	$this->auth_token = $auth;
	$this->geoCoder = $geoCoder;
	$this->polygon = $polygon;
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
    $this->log->debug("Processing CART request");

	// Proper response structure
	$res = array('cart' => array('items' => array(), 'deliveryOptions' => array(), 'paymentMethods' => array()));
	$outlets = $db->outlets;
	$delivery_flag = true;
	$items = $data->cart->items;
	$this->log->debug("There is ".count($items)." items in request");
	foreach ($data->cart->items as $parsedItem) { 
		$item = new Item($parsedItem);
		$this->log->debug("Processing ".$item);
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
	$city_name = $this->geoCoder->extractCity($data);
        $city_box = $this->geoCoder->box($city_name);
        $in_polygon = $city_box->inside($this->polygon);
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
?>