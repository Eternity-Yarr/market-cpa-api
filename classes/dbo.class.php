<?php

/*
 * market-cpa-api dealer-with
 * v. 0.2 | https://github.com/Eternity-Yarr/market-cpa-api/
 *
 * CC0 1.0 license.
 */

// You should implement this simple methods by yourself, according to your CMS or DB scheme

abstract class dbo extends mysqli {
    private $log;
    public $outletsProvider;
    protected $link;  		    // mysqli link holder


    public final function __construct($user, $pass, $db, OutletsProvider $outletsProvider) {
    $this->log = Logger::getLogger("dbo");
	$this->link = new mysqli('localhost', $user, $pass, $db);
    $this->outletsProvider = $outletsProvider;

	if (mysqli_connect_error()) {
	       $this->log->error('No can connect('. mysqli_connect_errno() .') '. mysqli_connect_error());
	   }
    }

    abstract public function inStock(Item $item);  		// Returns array of outlet ids with $id in stock.
    abstract public function getWeight(Item $item);		// Retruns weight of product
    abstract public function getPrice(Item $item); 		// Returns actual price for $id
    abstract public function getOrderStatus($id); 	// Returns status of order $id, if order is present. Returns false otherwise.
    abstract public function addOrder($id, $body, $initial_status, $fake = false); // Add new order to DB, return true on success, false - on errors
    abstract public function setStatus($id,$status,$body);// Set new $status to order $id. Return true on success, and true on status CANCELLED even if
							// there s no such order, according to yandex api. Returns false otherwise.
    abstract public function saveHistory($order_id,$status,$body);  // not really neccessary, just to track yandex movements
    abstract public function getExpensiveGroups();	// returns ids of groups with expensive delivery items

    public function close() {
    	if ($this->link)
    	   $this->link->close();
    }

}
