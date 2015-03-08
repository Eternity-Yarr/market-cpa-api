<?php
class Item {
 public $feedId;
 public $offerId;
 public $feedCategoryId;
 public $offerName;
 public $count;
 
 function __construct(stdClass $parsedItem) {
 	$this->feedId = $parsedItem->feedId;
 	$this->offerId = $parsedItem->offerId;
 	$this->feedCategoryId = $parsedItem->feedCategoryId;
 	$this->offerName = $parsedItem->offerName;
 	$this->count = $parsedItem->count;
 }

 function __toString() { 
  return "Item{offerId=\"$this->offerId\",offerName=\"$this->offerName\",categoryId=\"$this->feedCategoryId\"}x$this->count";
 }
}
?>