<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>Покупка на Маркете</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">

		<!-- Bootstrap -->
		<link href="/bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
		<link href="/bootstrap/css/bootstrap-theme.min.css" rel="stylesheet" media="screen">

		<!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
			<!--[if lt IE 9]>
			<script src="/bootstrap/js/html5shiv.js"></script>
			<script src="/bootstrap/js/respond.min.js"></script>
		<![endif]-->
		<style type="text/css">
			del {
				color: #999;
			}
			.modal-dialog {
				width: 900px;
			}
			.word-wrapped {
			     word-wrap: break-word;
			    word-break: break-all;
			}
		</style>
	</head>
	<body>
		<header class="container">
			<h1 class="col-md-10 col-md-offset-1">«Покупка на Маркете»</h1>
		</header>
		<section class="container">
			<article class="col-md-10 col-md-offset-1">
				<table class="table table-striped table-hover">
					<tr>
						<th>Номер заказа</th>
						<th>Дата и время</th>
						<th>Статус</th>
						<th>Сумма</th>
						<th>Доставка</th>
						<th>Оплата</th>
						<th></th>
					</tr>
<?php
foreach ($orders as $order) {
$status = $api->STATUS[$order->status][0];
if (trim($order->substatus) != '') $title = "title=\"".$api->SUBSTATUS[$order->substatus][0]."\""; else $title = '';
$total = 0;
$payment = (isset($api->PAYMENTS[$order->paymentMethod])) ? $api->PAYMENTS[$order->paymentMethod] : 'Не указана';
foreach ($order->items as $item) {
$total += $item->price * $item->count;
}
?>
					<tr>
						<td><?=$order->id?></td>
						<td><?=$order->creationDate?></td>
						<td <?=$title?>><?=$status?></td>
						<td class="text-right"><?=number_format($total, 0, ',', ' ');?>&nbsp;руб.</td>
						<td><?=$api->DELIVERY[$order->delivery->type]?></td>
						<td><?=$payment?></td>
						<td>
	    <!-- Button trigger modal -->
	    <button class="btn btn-default btn-xs" data-toggle="modal" data-target="#order_<?=$order->id?>">Подробнее... </button>
	    <!-- Modal -->
	    <div class="modal fade" id="order_<?=$order->id?>" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
		<div class="modal-dialog">
		    <div class="modal-content">
    			<div class="modal-header">
    			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
    			<h4 class="modal-title" id="myModalLabel">Заказ <?=$order->id?> от <?=$order->creationDate?></h4>
    		    </div>
    		    <div class="modal-body">

<table class="table">
	<tr class="row">
		<td class="col-md-6">
<div class="panel panel-default">
  <div class="panel-heading text-center"><h3 class="panel-title"><strong>Информация о покупателе</strong></h3></div>
  <div class="panel-body">
    <?if (isset($order->buyer)):?>
<dl class="dl-horizontal">
	<dt>Фамилия</dt>
	<dd><?=$order->buyer->lastName;?></dd>
	<dt>Имя</dt>
	<dd><?=$order->buyer->firstName;?></dd>
	<dt>Отчество</dt>
	<dd><?=$order->buyer->middleName;?></dd>
	<dt>Телефон</dt>
	<dd><?=$order->buyer->phone;?></dd>
	<dt>E-mail</dt>
	<dd><?=$order->buyer->email;?></dd>
</dl>
<?endif;?>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading text-center"><h3 class="panel-title"><strong>Информация о заказе</strong></h3></div>
  <div class="panel-body">
<dl class="dl-horizontal">
	<dt>Статус заказа</dt>
	<dd>
		<select id="#order_<?=$order->id?>_status" disabled="disabled">
  <option>132132</option>
  <option>2</option>
  <option>3</option>
  <option>4</option>
  <option>5</option>
</select>
<button onclick="$('#order_<?=$order->id?>_status').removeAttr('disabled');">Изменить...</button>

	</dd>
</dl>
  </div>
</div>

</td>


<td class="col-md-6">

<?switch ($order->delivery->type):
case 'DELIVERY':?>

<div class="panel panel-default">
  <div class="panel-heading"><h3 class="panel-title text-center"><strong>Доставка на <?=$order->delivery->dates->toDate;?></strong>&nbsp;(<?=number_format($order->delivery->price, 0, ',', ' ');?>&nbsp;руб.)</h3></div>
  <div class="panel-body">
<dl class="dl-horizontal">
	<dt>Страна</dt>
	<dd><?=$order->delivery->address->country;?></dd>
	<dt>Город</dt>
	<dd><?=$order->delivery->address->city;?></dd>
	<dt>Улица</dt>
	<dd><?=$order->delivery->address->street;?></dd>
	<dt>Дом</dt>
	<dd><?=$order->delivery->address->house;?></dd>
	<dt>Телефон</dt>
	<dd><?=$order->delivery->address->phone;?></dd>
	<dt>Контактное лицо</dt>
	<dd><?=$order->delivery->address->recipient;?></dd>
</dl>
  </div>
</div>



<?break;?>
<?case 'PICKUP':?>
<p class="text-center"><h3 class="panel-title text-center"><strong>Самовывоз на <?=$order->delivery->dates->toDate;?></strong>&nbsp;(из <?=$outlet_names[$order->delivery->outletId]?>)</h3></p>
<?break;?>
<?default:?>
<p class="text-center">Не доставка и не самовывоз</p>
<?endswitch;?>
</td>
</tr>
</table>

	    <table class="table table-striped table-hover table-condensed">
	    	<tr class="row">
	    		<th>Наименование</th>
	    		<th>Кол-во</th>
	    		<th>Цена</th>
	    	</tr>
<?php

foreach ($order->items as $item) {

?>
<tr class="row">
	      <td class="col-md-9 word-wrapped">[<?=$item->offerId?>] <?=$item->offerName?></td> 
	      <td class="col-md-1 text-center"><?=$item->count?></td> 
	      <td class="col-md-2 text-right"><?=number_format($item->price, 0, ',', ' ');?>&nbsp;руб.</td> 

</tr>
<?php
}
?>
<tr class="row"> <td colspan=3 class="text-right">Итого:&nbsp;<strong><?=number_format($total, 0, ',', ' ');?>&nbsp;руб.</strong></td></tr>
	    </table>

<?
// echo "<pre>"; print_r($order); echo "</pre>";
?>
		    </div>
		    <div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal">Закрыть</button>
    		    </div>
	    </div><!-- /.modal-content -->
	  </div><!-- /.modal-dialog -->
	</div><!-- /.modal -->
				</td>

					</tr>
<?php
}
?>
				</table>
			</article>
		</section>
		<footer class="container">

		</footer>
		<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
		<script src="/bootstrap/js/jquery.js"></script>
		<!-- Include all compiled plugins (below), or include individual files as needed -->
		<script src="/bootstrap/js/bootstrap.min.js"></script>
	</body>
</html>