<?php
class Outlet {
	public $dbId;
	public $alias;
	public $extId;
	
	public function __construct($dbId, $alias, $extId) {
		$this->dbId = $dbId;
		$this->alias = $alias;
		$this->extId = $extId;
	}

	function __toString() {
		return "Outlet[$this->alias]{dbId=$this->dbId,extId=$this->extId}";
	}
}
?>