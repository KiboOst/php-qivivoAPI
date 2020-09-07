<?php
/*

https://github.com/KiboOst/php-qivivoAPI

*/

class qivivoAPI {

    public $_version = '2.0';

    //USER FUNCTIONS======================================================
    //GET FUNCTIONS:
    public function refreshDatas()
    {
        $this->getDatas();
    }
    public function getHeating() //@return['result'] array with heating datas
    {
        if ($this->_houseData == null) $datas = $this->getDatas();
        if ( isset($datas['error']) ) return $datas;
        return array('result'=>$this->_houseData['heating']);
    }

    public function getTempSettings() //@return['result'] array with temperatures and settings
    {
        if ( isset($this->_houseData['temperatures'])) {
            return array('result'=>$this->_houseData['temperatures']);
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/thermal-settings';
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);

        $this->_houseData['temperatures'] = $jsonData;

        if ($jsonData['global_informations']['installation_type'] == 'multizone')
        {
            $this->_houseData['isMultizone'] = true;
        } else {
            $this->_houseData['isMultizone'] = false;
        }

        return array('result'=>$jsonData);
    }

    public function isMultizone() //@return['result'] array with multizone setting
    {
        if ( isset($this->_houseData['isMultizone'])) {
            return array('result'=>$this->_houseData['isMultizone']);
        }

        $this->getTemperatures();
        return array('result'=>$this->_houseData['isMultizone']);
    }

    public function getPrograms() //@return['result'] array with readable formated program
    {
        if ( isset($this->_houseData['programs'])) {
            return array('result'=>$this->_houseData['programs']);
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/programs';
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);

        $this->_houseData['programs'] = $jsonData['programs'];
        $this->_houseData['schedules'] = $jsonData['schedule'];

        return array('result'=>$jsonData['programs']);
    }

    public function getSchedules() //@return['result'] array with readable formated program
    {
        if ( isset($this->_houseData['schedules'])) {
            return array('result'=>$this->_houseData['schedules']);
        }

        $this->getPrograms();
        return array('result'=>$this->_houseData['schedules']);
    }

    public function getCurrentProgram() //@return['result'] array with current program selection
    {
        if ( isset($this->_houseData['programs'])) {
            $programs = $this->_houseData['programs'];
        } else {
            $programs = $this->getPrograms()['result'];
        }

        foreach($programs as $program)
        {
            if ($program['is_activated']) {
                return array('result'=>$program);
            }
        }
        return array('error'=>'No current program found');
    }

    public function getProducts() //@return['result'] array with products
    {
        $url = $this->_urlRoot.'/park/housings/'.$this->_houseData['id'].'/connected-objects';
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);
        return array('result'=>$jsonData);
    }

    public function getZones() //@return['result'] array with zones
    {
        if ( isset($this->_houseData['zones'])) {
            return array('result'=>$this->_houseData['zones']);
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/zones';
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);

        $this->_houseData['zones'] = $jsonData;

        return array('result'=>$jsonData);
    }

    public function getWeather() //@return['result'] array with weather datas
    {
        $url = $this->_urlRoot.'/weather/weather/current?housing_id='.$this->_houseData['id'];
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);
        return array('result'=>$jsonData);
    }


    //SET FUNCTIONS:
    public function setHeating($state=true) //@state false/true | @return['result'] true, @return['error'] if any
    {
        $state = var_export($state, true);
        $availValues = ['false', 'true'];
        if (!in_array($state, $availValues)) return array('result'=>null, 'error'=>'Got wrong value, should be in '.implode(', ', $availValues));

        if ($state == true) {
            $post = '{"state":"on"}';
        } else {
            $post = '{"state":"off"}';
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/thermal-control/heating-system-state';
        $answer = $this->_request('PUT', $url, $post);

        if ($this->isJson($answer)) {
            return array('result'=>true);
        } else {
            return array('error'=>'error while changing program');
        }
    }

    public function setTemperature($value, $period=120, $zoneName=null) //@return['result'] true, @return['error'] if any
    {
        //get thermostat zone id:
        if ($this->_houseData == null) $datas = $this->getDatas();
        $zones = $this->_houseData['heating']['zones'];
        foreach($zones as $zone)
        {
            if ($zoneName) {
                if ($zone['title'] == $zoneName) {
                    $zoneId = $zone['id'];
                    if ($zone['set_point_type'] == 'pilot_wire') {
                        return array('error'=>'Cannot set temperature for wire order zone.');
                    }
                }
            } else {
                if ($zone['set_point_type'] != 'pilot_wire') {
                    $zoneId = $zone['id'];
                }
            }
        }

        $post = '{"duration":'.$period.',"set_point":{"instruction":'.$value.'}}';
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/thermal-control/zones/'.$zoneId.'/temporary-instruction';
        $answer = $this->_request('POST', $url, $post);

        if ($this->isJson($answer)) {
            return array('result'=>true);
        } else {
            return array('error'=>'error while changing zone temperature');
        }
    }

    public function setZoneMode($mode, $period=120, $zoneName=null) //@return['result'] true, @return['error'] if any
    {
        if (!$mode || !$zoneName) return array('error'=>'You must specify mode name, period in minutes, zone name.');
        $availValues = ['stop', 'eco', 'comfort_minus2', 'comfort_minus1', 'comfort'];
        if (!in_array($mode, $availValues)) return array('result'=>null, 'error'=>'Got wrong value, should be in '.implode(', ', $availValues));

        $zoneId = null;
        $zones = $this->_houseData['heating']['zones'];
        foreach($zones as $zone)
        {
            if ($zone['title'] == $zoneName) {
                $zoneId = $zone['id'];
                if ($zone['set_point_type'] != 'pilot_wire') {
                    return array('error'=>'Cannot set temperature for wire order zone.');
                }
            }
        }

        if (!$zoneId) {
            return array('error'=>'Could not find zone.');
        }

        $post = '{"duration":'.$period.',"set_point":{"instruction":"'.$mode.'"}}';
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/thermal-control/zones/'.$zoneId.'/temporary-instruction';
        $answer = $this->_request('POST', $url, $post);

        if ($this->isJson($answer)) {
            return array('result'=>true);
        } else {
            return array('error'=>'error while changing zone mode');
        }
    }

    public function setProgram($name='') //@name string, @datas array | @return['result'] true, @return['error'] if any
    {
        if ( isset($this->_houseData['programs'])) {
            $programs = $this->_houseData['programs'];
        } else {
            $programs = $this->getPrograms()['result'];
        }

        foreach($programs as $program)
        {
            if ($program['is_activated'] == false && $program['title'] == $name) {
                $id = $program['id'];
                $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/programs/'.$id.'/activate';
                $answer = $this->_request('POST', $url, null);
                if ($this->isJson($answer)) {
                    return array('result'=>true);
                } else {
                    return array('error'=>'error while changing program');
                }
            }
        }
    }

    public function setTempSettings($settingsAr) //$settingsAr array | @return['result'] true, @return['error'] if any
    {
        if (!is_array($settingsAr)) return array('error'=>'You must send settings array of value by 0.5.');

        $post = json_encode($settingsAr);
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/custom-temperatures';
        $answer = $this->_request('PUT', $url, $post);

        if ($this->isJson($answer)) {
            return array('result'=>true);
        } else {
            return array('error'=>'error while settings temperatures settings');
        }
    }

    public function setAway() //@return['result'] true, @return['error'] if any
    {
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/thermal-control/leave-home';
        $answer = $this->_request('POST', $url, null);

        if ($this->isJson($answer)) {
            return array('result'=>true);
        } else {
            return array('error'=>'error while settings temperatures settings');
        }
    }

    public function cancelAway() //@return['result'] true, @return['error'] if any
    {
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/thermal-control/leave-home';
        $answer = $this->_request('DELETE', $url, null);

        if ($this->isJson($answer)) {
            return array('result'=>true);
        } else {
            return array('error'=>'error while setting departure');
        }
    }

    public function setDeparture($startDate=null, $endDate=null) //@$startDate, $endDate string like "2020-09-03T22:00:12.000Z" | @return['result'] true, @return['error'] if any
    {
        if (is_null($startDate) && is_null($endDate)) {
            $post = '{}';
        } else {
            $post = '{"begin_at":"'.$startDate.'","end_at":"'.$endDate.'"}';
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/thermal-control/absence';
        $answer = $this->_request('POST', $url, $post);

        if ($this->isJson($answer)) {
            return array('result'=>true);
        } else {
            return array('error'=>'error while setting departure');
        }
    }

    public function cancelDeparture() //@return['result'] true, @return['error'] if any
    {
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houseData['id'].'/thermal-control/absence';
        $answer = $this->_request('DELETE', $url, null);

        if ($this->isJson($answer)) {
            return array('result'=>true);
        } else {
            return array('error'=>'error while setting departure');
        }
    }

    //INTERNAL FUNCTIONS==================================================
    protected function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    //CALLING FUNCTIONS===================================================
    protected function getDatas() //request thermostat datas
    {
        $url = $this->_urlRoot.'/park/housings';
        $answer = $this->_request('GET', $url);
        $answer = substr($answer, 1, -1);

        $jsonData = json_decode($answer, true);

        if (!isset($jsonData['id']))
        {
            $this->error = 'Could not get any data!';
            return array('result'=>null, 'error' => $this->error);
        }

        $this->_houseData = $jsonData;

        $url = $this->_urlRoot.'/thermal/housings/'.$jsonData['id'].'/thermal-details';
        $answer = $this->_request('GET', $url);
        $this->_houseData['heating'] = json_decode($answer, true);
    }

    protected function _request($method, $url, $post=null)
    {
        curl_setopt($this->_curlHdl, CURLOPT_URL, $url);
        //first OPTIONS request:
        curl_setopt($this->_curlHdl, CURLOPT_HTTPGET, true);
        curl_setopt($this->_curlHdl, CURLOPT_CUSTOMREQUEST, 'OPTIONS');

        if (isset($this->_token))
        {
            $headers = [
                'access-control-request-headers: authorization',
                'Referrer Policy: no-referrer-when-downgrade',
                'Accept: application/json, text/plain, */*',
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: https://app.comapsmarthome.com',
                'referer: https://app.comapsmarthome.com/real-time',
                'sec-fetch-dest: empty',
                'sec-fetch-mode: cors',
                'sec-fetch-site: same-site',
                'Host: api.comapsmarthome.com',
                'Authorization: Bearer '.$this->_token
            ];
            if ($method == 'POST') {
                array_push($headers, 'access-control-request-method: POST');
            } else {
                array_push($headers, 'access-control-request-method: GET');
            }

            curl_setopt($this->_curlHdl, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($this->_curlHdl);

        if ($method == 'GET')
        {
            curl_setopt($this->_curlHdl, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($this->_curlHdl, CURLOPT_HTTPGET, true);
        }
        if ($method == 'POST')
        {
            curl_setopt($this->_curlHdl, CURLOPT_CUSTOMREQUEST, 'POST');
            if ($post) curl_setopt($this->_curlHdl, CURLOPT_POSTFIELDS, $post);
        }
        if ($method == 'PUT')
        {
            curl_setopt($this->_curlHdl, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($post) curl_setopt($this->_curlHdl, CURLOPT_POSTFIELDS, $post);
        }
        if ($method == 'DELETE')
        {
            curl_setopt($this->_curlHdl, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if ($post) curl_setopt($this->_curlHdl, CURLOPT_POSTFIELDS, $post);
        }

        $response = curl_exec($this->_curlHdl);

        //echo 'request answer: '.$response."<br><br>";
        return $response;
    }


    //AUTHORIZATION=======================================================
    public $error = null;
    public $_token = null;

    public $_houseData = null;

    protected $_qivivo_user;
    protected $_qivivo_pass;
    protected $_urlAuth = 'https://authentication.comapsmarthome.com/auth/realms/smarthome-prod/protocol/openid-connect/token';
    protected $_urlRoot = 'https://api.comapsmarthome.com';
    protected $_curlHdl = null;
    protected $_zoneModes = ['Thermostat', null, null, 'Confort', 'Eco', 'Arret', 'Hors-gel', 'Confort -1', 'Confort -2'];

    protected function connect()
    {
        $this->_curlHdl = curl_init();
        curl_setopt($this->_curlHdl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($this->_curlHdl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->_curlHdl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->_curlHdl, CURLOPT_HTTPGET, true);

        $post = 'username='.$this->_qivivo_user.'&password='.$this->_qivivo_pass.'&client_id=smarthome-webapp&grant_type=password';
        $answer = $this->_request('POST', $this->_urlAuth, $post);

        if ($this->isJson($answer)) {
            $json = json_decode($answer, true);
            if (isset($json['access_token'])) {
                $this->_token = $json['access_token'];
                return true;
            } else {
                $this->error = 'Cannot find access_token.';
                return false;
            }
        } else {
            $this->error = 'Authentification error.';
            return false;
        }
    }

    function __construct($qivivo_user, $qivivo_pass)
    {
        $this->_qivivo_user = urlencode($qivivo_user);
        $this->_qivivo_pass = urlencode($qivivo_pass);

        if ($this->connect() == true)
        {
            $this->getDatas();
        }
    }
} //qivivoAPIv2 end
?>
