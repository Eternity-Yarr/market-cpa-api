<?php

trait Market_API_v2_russian {

// statuses

    public $DELIVERY = array ( 'DELIVERY' => 'Доставка' , 'PICKUP' => 'Самовывоз', 'POST' => 'EMS' );

    public $PAYMENTS = array ( 'CASH_ON_DELIVERY' => 'Курьеру', 'SHOP_PREPAID' => 'Предоплата', 'YANDEX' => 'Предоплата Яндексу');

    public $STATUS = array(
  'UNPAID'   =>
    array(	'Оформлен, нет оплаты',
		'Не оплачен',
		'danger'),
  'RESERVED' => 
    array(	'Заказ зарезервирован',
		'В резерве',
		'warning'),
  'PROCESSING' => 
    array(	'Заказ находится в обработке',
		'В обработке',
		'danger'),
  'DELIVERY'  =>
    array(	'Заказ передан в доставку',
		'В доставке',
		'warning'),
  'PICKUP' =>
    array(	'Заказ доставлен в пункт самовывоза',
		'В самовывозе',
		'warning'),
  'DELIVERED' =>
    array(	'Заказ получен покупателем',
		'Доставлен',
		'success'),
  'CANCELLED' => 
    array(	'Заказ отменен',
		'Отменен',
		''));

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

    public $BUYER = array(
    'firstName'	=> 'Имя покупателя',
    'phone'		=> 'Номер телефона',
    'email'		=> 'Электронная почта',
    'lastName'		=> 'Фамилия покупателя',
    'middleName'	=> 'Отчество покупателя');

    public $ADDRESS = array(
    'country'		=> 'Страна',
    'city'		=> 'Город/село',
    'house'		=> 'Номер дома',
    'postcode'		=> 'Почтовый индекс',
    'street'		=> 'Улица',
    'subway'		=> 'Метро',
    'block'		=> 'Корпус',
    'entrance'		=> 'Подъезд',
    'entryphone'	=> 'Домофон',
    'floor'		=> 'Этаж',
    'apartment'		=> 'Квартира',
    'recipient'		=> 'ФИО получателя',
    'phone'		=> 'Телефон получателя');


}
?>