<?php

class Polygon {
 private $points;
 private $n;
 
 function __construct($json) {
    $points = json_decode($json);
    $polygonPoints = array();

    foreach($points as $p) {
      $polygonPoints[] = new Point($p[0], $p[1]);
    }
    $this->init($polygonPoints);
 }

 private function init(array $points) {
    $this->points = $points;
    $this->n = count($points);
    $this->points[] = $points[0];
 }

 // fallback alghorithm
 private function crossingNumber(Point $p) {

  $cn = 0;    // the  crossing number counter
  // loop through all edges of the polygon
  for($i = 0; $i < $this->n; $i++) {    // edge from V[i]  to V[i+1]
   if ((($this->points[$i]->y <= $p->y) AND ($this->points[$i+1]->y > $p->y))     // an upward crossing
    OR (($this->points[$i]->y > $p->y) AND ($this->points[$i+1]->y <= $p->y))) { // a downward crossing
        // compute  the actual edge-ray intersect x-coordinate
        $vt = ($p->y  - $this->points[$i]->y) / ($this->points[$i+1]->y - $this->points[$i]->y);
        if ($p->x <  $this->points[$i]->x + $vt * ($this->points[$i+1]->x - $this->points[$i]->x)) // P.x < intersect
          $cn++;   // a valid crossing of y=P.y right of P.x
      }
    }
    return ($cn % 2);    // 0 if even (out), and 1 if  odd (in)
  }

  private function windingNumber(Point $p) {
    $wn = 0;    // the  winding number counter
    // loop through all edges of the polygon
    for ($i = 0; $i < $this->n; $i++) {   // edge from V[i] to  V[i+1]
        if ($this->points[$i]->y <= $p->y) {          // start y <= P.y
            if ($this->points[$i+1]->y  > $p->y)      // an upward crossing
                 if ($p->isLeft($this->points[$i], $this->points[$i+1]) > 0)  // P left of  edge
                     $wn++;            // have  a valid up intersect
        }
        else {                        // start y > P.y (no test needed)
            if ($this->points[$i+1]->y  <= $p->y)     // a downward crossing
                 if ($p->isLeft($this->points[$i], $this->points[$i+1]) < 0)  // P right of  edge
                     $wn--;            // have  a valid down intersect
        }
    }
    return $wn;
  } 

  public function inside(Point $p) {
    return $this->windingNumber($p) != 0;
  }

  function __toString() {
    return "Polygon{n=$this->n}";
  }
}

?>