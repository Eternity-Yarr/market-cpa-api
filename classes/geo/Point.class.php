<?php
class Point {
 public $x;
 public $y;

 function __construct($x, $y) {
  $this->x = $x;
  $this->y = $y;
 }

 function isLeft(Point $p0, Point $p1) {
    return (($p1->x - $p0->x) * ($this->y - $p0->y)
            - ($this->x -  $p0->x) * ($p1->y - $p0->y));
 }

 function __toString() { 
  return "Point{x=$this->x,y=$this->y}";
 }
}
?>