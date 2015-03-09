<?php
spl_autoload_register(
  function ($class) {
  	$baseDir = dirname(__FILE__).'/classes/';
	if(file_exists($baseDir.'geo/' . $class . '.class.php')) {
    	include $baseDir.'geo/' . $class . '.class.php'; 
	} else if(file_exists($baseDir.'model/' . $class . '.class.php')) {
		include $baseDir.'model/' . $class . '.class.php'; 
	} else if(file_exists($baseDir . $class . '.class.php')) {
		include $baseDir . $class . '.class.php'; 
	} else {
		throw new Exception("Class ".$class." loading failed!");
	}
  }
);
?>