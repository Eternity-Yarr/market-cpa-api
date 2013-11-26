<?php

// You should implement this simple methods by yourself, according to your CMS or DB scheme

abstract class dbo extends mysqli {

    protected $link;  		// mysqli link holder
    public $outlets;  		// available outlets id

    public final function __construct($user,$pass,$db) {

	$this->link = new mysqli('localhost',$user,$pass,$db);

	if (mysqli_connect_error()) {
	    die('No can connect('. mysqli_connect_errno() .') '. mysqli_connect_error());
	}
    }

    abstract public function inStock($id);   		// Returns array of outlet ids with $id in stock.
    abstract public function getPrice($id);  		// Returns actual price for $id
    abstract public function getOrderStatus($id); 	// Returns status of order $id, if order is present. Returns false otherwise.
    abstract public function addOrder($id,$body, $initial_status, $fake = false); // Add new order to DB, return true on success, false - on errors
    abstract public function setStatus($id,$status,$body);// Set new $status to order $id. Return true on success, and true on status CANCELLED even if
							// there s no such order, according to yandex api. Returns false otherwise.
    abstract public function saveHistory($order_id,$status,$body);  // not really neccessary, just to track yandex movements

    function close() {

	if ($this->link)
	$this->link->close();

    }

}
