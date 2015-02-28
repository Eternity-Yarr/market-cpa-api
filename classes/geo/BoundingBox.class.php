<?php

class BoundingBox {
  public $lower;
  public $upper;

  function __construct(Point $lower, Point $upper) {
    $this->lower = $lower;
    $this->upper = $upper;
  }

  public function inside(Polygon $poly) {
    return $poly->inside($this->lower) OR $poly->inside($this->upper);
  }

  function __toString() {
    return "BoundingBox{lower=$this->lower,upper=$this->upper}";
  }
}

?>