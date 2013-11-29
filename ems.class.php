<?php

class EMS {

    static protected $baseurl = 'http://emspost.ru/api/rest/?';

    const emsCities = 'cities';
    const emsRegions = 'regions';
    const emsRussia = 'russia';
    const emsCountries = 'countries';

}

class EMSDelivery extends EMS {


    private function httpRequest($url) {
    
    $ch = curl_init();
    
	curl_setopt($ch, CURLOPT_URL, $url );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	return curl_exec($ch);

    }

    public function emsEcho() {

	$json = $this->httpRequest(EMS::$baseurl.'method=ems.test.echo');
    
        if ($res = json_decode($json)) {
	    return ((isset($res->rsp->stat)) and ($res->rsp->stat=='ok'));
	} else return false;

    }
    
    public function emsGetLocations($type=EMS::emsCities) {

	$json = $this->httpRequest(EMS::$baseurl.'method=ems.get.locations&type='.$type.'&plain=true');  // wtf BUG. if plain is not true, gibberish returned
	if (($res = json_decode($json)) AND (isset($res->rsp->locations))) {
	    $locations = array();
	    foreach ($res->rsp->locations as $element) {
		$locations[$element->name] = $element->value;
		}
	    } else return false;
	return $locations;
    }

    public function emsGetMaxWeight() {
    
	$json = $this->httpRequest(EMS::$baseurl.'method=ems.test.echo');
    
        if (($res = json_decode($json)) and (isset($res->rsp->max_weight))) {
	
	return $res->rsp->max_weight;

	} else return false;


    }

    public function emsCalculate($dest,$weight) {
    
    $from = 'city--moskva';  			// HARDCODED from Moscow
    $url = sprintf(EMS::$baseurl.'method=ems.calculate&from=%s&to=%s&weight=%f',$from,$dest,$weight);
    $json = $this->httpRequest($url);
    
    if (($res = json_decode($json)) and (isset($res->rsp->price))) {
	    return array('price' => $res->rsp->price,'min'=>$res->rsp->term->min, 'max'=> $res->rsp->term->max);
	}
    else return false;

    }


}

?>