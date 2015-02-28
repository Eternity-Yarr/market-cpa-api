<?php

class GeoCode {

  private function query($addr) { 
    $json = file_get_contents("http://geocode-maps.yandex.ru/1.x/?format=json&kind=locality&results=1&geocode=".urlencode($addr));
    $response = json_decode($json)->response;

    return $response;
  }

  function coordinates($addr) {
    $response = $this->query($addr);
    if($response->GeoObjectCollection->metaDataProperty->GeocoderResponseMetaData->found > 0) {
        $pos = explode(" ",$response->GeoObjectCollection->featureMember[0]->GeoObject->Point->pos);
	return new Point($pos[0], $pos[1]);
    }
    else 
	return NULL;
  }

  function box($addr) {
   $response = $this->query($addr);
    if($response->GeoObjectCollection->metaDataProperty->GeocoderResponseMetaData->found > 0) {
        $bounds = $response->GeoObjectCollection->featureMember[0]->GeoObject->boundedBy->Envelope;
        $pos = explode(" ",$bounds->lowerCorner);
	$lower =  new Point($pos[0], $pos[1]);
        $pos = explode(" ",$bounds->upperCorner);
	$upper =  new Point($pos[0], $pos[1]);
	return new BoundingBox($lower, $upper);
    }
    else 
	return NULL;
  }

  function extractCityJSON($cart) {
    return $this->extractCity(json_decode($cart));
  }

  function extractCity($cartObject) {
    return $this->city($cartObject->cart->delivery->region);
  }




  private function city($node) {
    if($node->type == "CITY") 
      return $node->name;
    else if (is_object($node->parent))
     return $this->city($node->parent);
    else 
     return NULL;
  }

}

?>