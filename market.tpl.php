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
						<td><?=sprintf("%d&nbsp;руб.",$total)?></td>
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
	    <table class="table table-striped table-hover">

<?php

foreach ($order->items as $item) {

?>
<tr class="row">
	      <td class="col-md-9 word-wrapped">[<?=$item->offerId?>] <?=$item->offerName?></td>
	      <td class="col-md-1"><?=$item->count?></td>
	      <td class="col-md-2"><?=sprintf("%d&nbsp;руб.",$item->price)?></td>

</tr>
<?php
}
?>
<tr class="row"> <td colspan=3 class="text-right">Итого:&nbsp;<?=sprintf("%d&nbsp;руб.",$total)?></td></tr>
	    </table>
<? echo "<pre>"; print_r($order); echo "</pre>";?>
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