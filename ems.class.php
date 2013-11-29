<?php

class EMS {

    protected $baseurl = 'http://emspost.ru/api/rest/?';

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

	$json = $this->httpRequest($this->baseurl.'method=ems.test.echo');
    
        if ($res = json_decode($json)) {
	    return ((isset($res->rsp->stat)) and ($res->rsp->stat=='ok'));
	} else return false;

    }
    
    public function emsGetLocations($type=EMS::emsCities) {

    

    }

    public function emsGetMaxWeight() {

    }

    public function emsCalculate() {

    }


}

?>