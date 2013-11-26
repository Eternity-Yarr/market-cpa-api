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

    abstract public function inStock($id);
    abstract public function getPrice($id);

    function close() {

	if ($this->link)
	$this->link->close();

    }

}
