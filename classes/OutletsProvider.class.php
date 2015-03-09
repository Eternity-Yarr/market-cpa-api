<?php

class OutletsProvider {
	private $outlets = array();

	public function add(Outlet $outlet)	{
		$this->outlets[$outlet->dbId] = $outlet;
	}

	public function byId($dbId) {
		return $this->outlets[$dbId];
	}

	public function listed($dbId) {
		return isset($this->outlets[$dbId]);
	}

	public function outlets() {
		return $this->outlets;
	}

	function __toString() {
		$aliases = array();
		foreach($this->outlets as $outlet) {
			$aliases[] = $outlet->alias;
		}

		return "OutletsProvider{".implode(',', $aliases)."}";
	}
}

?>