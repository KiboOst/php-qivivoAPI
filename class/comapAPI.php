<?php
/*
qivivoAPI, php API for Smart Qivivo / Comap Thermostat.

MIT License
Copyright (c) 2017 KiboOst

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/


/*

https://github.com/KiboOst/php-qivivoAPI

*/

class qivivoAPI {

    public $version = '3.0';

    //USER FUNCTIONS======================================================
    //GET FUNCTIONS:
    public function refreshDatas() {
        $this->getDatas();
    }

    //@return['result'] array with house name
    public function getHouseIdbyName($name) {
        if ($this->_houses == null)
        {
            $datas = $this->getDatas();
        }
        if (isset($datas['error']))
        {
            return $datas;
        }
        for ($i=0; $i<count($this->_houses); ++$i) {
            if ($this->_houses[$i]['name'] == $name) {
                return array('result'=>$i);
            }
        }
        return array('error'=>'No house found with this name');
    }

    //@return['result'] array with heating datas
    public function getHeating($houseId=0) {
        if ($this->_houses == null)
        {
            $datas = $this->getDatas();
        }
        if (isset($datas['error']))
        {
            return $datas;
        }
        return array('result'=>$this->_houses[$houseId]['heating']);
    }

    //@return['result'] array with temperatures and settings
    public function getTempSettings($houseId=0) {
        if (isset($this->_houses[$houseId]['temperatures']))
        {
            return array('result'=>$this->_houses[$houseId]['temperatures']);
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/thermal-settings';
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);

        $this->_houses[$houseId]['temperatures'] = $jsonData;

        if ($jsonData['global_informations']['installation_type'] == 'multizone')
        {
            $this->_houses[$houseId]['isMultizone'] = true;
        }
        else
        {
            $this->_houses[$houseId]['isMultizone'] = false;
        }

        return array('result'=>$jsonData);
    }

    //@return['result'] array with multizone setting
    public function isMultizone($houseId=0) {
        if (isset($this->_houses[$houseId]['isMultizone']))
        {
            return array('result'=>$this->_houses[$houseId]['isMultizone']);
        }

        $this->getTempSettings($houseId);
        return array('result'=>$this->_houses[$houseId]['isMultizone']);
    }

    //@return['result'] array with readable formated program
    public function getPrograms($houseId=0) {
        if (isset($this->_houses[$houseId]['programs']))
        {
            return array('result'=>$this->_houses[$houseId]['programs']);
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/programs';
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);

        $this->_houses[$houseId]['programs'] = $jsonData['programs'];

        return array('result'=>$jsonData['programs']);
    }

    //@return['result'] array with current program selection
    public function getCurrentProgram($houseId=0) {
        if (isset($this->_houses[$houseId]['programs']))
        {
            $programs = $this->_houses[$houseId]['programs'];
        }
        else
        {
            $programs = $this->getPrograms($houseId)['result'];
        }

        foreach ($programs as $program) {
            if ($program['is_activated'])
            {
                return array('result'=>$program);
            }
        }
        return array('error'=>'No current program found');
    }

    //@return['result'] array with readable formated program
    public function getSchedules($houseId=0) {
        if (isset($this->_houses[$houseId]['schedules']))
        {
            return array('result'=>$this->_houses[$houseId]['schedules']);
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/schedules';
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);

        $this->_houses[$houseId]['schedules'] = $jsonData;

        return array('result'=>$jsonData);
    }

    //@return['result'] array with current program selection
    public function getCurrentSchedule($houseId=0) {
        $currentProgram = $this->getCurrentProgram($houseId)['result'];
        if (isset($currentProgram['error']))
        {
            return $currentProgram;
        }

        if (isset($this->_houses[$houseId]['schedules']))
        {
            $schedules = $this->_houses[$houseId]['schedules'];
        }
        else
        {
            $schedules = $this->getschedules($houseId)['result'];
        }

        foreach ($schedules as $schedule) {
            if ($schedule['id'] == $currentProgram['zones'][0]['schedule_id'])
            {
                return array('result'=>$schedule);
            }
        }
        return array('error'=>'No current schedule found');
    }

    //@return['result'] array with devices
    public function getDevices($houseId=0) {
        $url = $this->_urlRoot.'/park/housings/'.$this->_houses[$houseId]['id'].'/connected-objects';
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);
        $this->_houses[$houseId]['devices'] = $jsonData;
        return array('result'=>$jsonData);
    }

    //@return['result'] array with zones
    public function getZones($houseId=0) {
        if (isset($this->_houses[$houseId]['zones']))
        {
            return array('result'=>$this->_houses[$houseId]['zones']);
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/zones';
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);

        $this->_houses[$houseId]['zones'] = $jsonData;

        return array('result'=>$jsonData);
    }

    //@zoneName | @return['result'] array with zone events
    public function getZoneEvents($zoneName='', $houseId=0) {
        $zones = $this->_houses[$houseId]['heating']['zones'];
        foreach ($zones as $zone) {
            if ($zone['title'] == $zoneName)
            {
                return array('result'=>$zone['events']);
            }
        }
        return array('error'=>'Could not find this Zone');
    }

    //@zoneName | @return['result'] true, @return['error'] if any
    public function hasTimeOrder($zoneName='', $houseId=0) {
        $zoneEvents = $this->getZoneEvents($zoneName, $houseId);
        if (isset($zoneEvents['error']))
        {
            return $zoneEvents;
        }
        if (isset($zoneEvents['result']['temporary_instruction']['set_point']))
        {
            return array('result'=>true);
        }
        return array('result'=>false);
    }

    //@return['result'] array with weather datas
    public function getWeather($houseId=0) {
        $url = $this->_urlRoot.'/weather/weather/current?housing_id='.$this->_houses[$houseId]['id'];
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);
        return array('result'=>$jsonData);
    }

    //@return['result'] array of devices with zone, order, by serial number
    public function getFullDevices($houseId=0) {
        if (!isset($this->_houses[$houseId]['devices']))
        {
            $this->getDevices($houseId);
        }

        $products = $this->_houses[$houseId]['devices'];
        $Devices = [];
        foreach ($products as $device) {
            if (isset($device['serial_number']))
            {
                $serial = $device['serial_number'];
                unset($device['serial_number']);
                $Devices[$serial] = $device;
            }
        }

        //get devices zones and order:
        if (!isset($this->_houses[$houseId]['zones']))
        {
            $this->getZones($houseId);
        }

        $zones = $this->_houses[$houseId]['zones'];
        foreach ($zones as $zone) {
            $zoneName = $zone['title'];
            //get zone order:
            foreach ($this->_houses[$houseId]['heating']['zones'] as $heatingZone) {
                if ($heatingZone['id'] == $zone['id'])
                {
                    if ($zone['instruction_type'] == 'temperature')
                    {
                        $order = $heatingZone['set_point']['instruction'];
                        $status = $heatingZone['heating_status'];
                    }
                    else
                    {
                        $order = $heatingZone['set_point']['instruction'];
                        $status = null;
                    }
                }
            }

            foreach ($Devices as $serial => $device) {
                if (in_array($serial, $zone['connected_objects']))
                {
                    $Devices[$serial]['zone'] = $zoneName;
                    $Devices[$serial]['order'] = $order;
                    $Devices[$serial]['heating_status'] = $status;
                    break;
                }
            }
        }
        return array('result'=>$Devices);
    }


    //SET FUNCTIONS:

    //@state false/true | @return['result'] true, @return['error'] if any
    public function setHeating($state=true, $houseId=0) {
        $state = var_export($state, true);
        $availValues = ['false', 'true'];
        if (!in_array($state, $availValues))
        {
            return array('result'=>null, 'error'=>'Got wrong value, should be in '.implode(', ', $availValues));
        }

        if ($state == true)
        {
            $post = '{"state":"on"}';
        }
        else
        {
            $post = '{"state":"off"}';
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId].'/thermal-control/heating-system-state';
        $answer = $this->_request('PUT', $url, $post);

        if ($this->isJson($answer))
        {
            return array('result'=>true);
        }
        else
        {
            return array('error'=>'error while changing heating');
        }
    }

    //@return['result'] true, @return['error'] if any
    public function setTemperature($value, $period=120, $zoneName=null, $houseId=0) {
        //get thermostat zone id:
        if ($this->_houses == null) {
            $datas = $this->getDatas();
        }

        $zoneId = null;
        $zones = $this->_houses[$houseId]['heating']['zones'];
        foreach ($zones as $zone) {
            if ($zoneName)
            {
                if ($zone['title'] == $zoneName)
                {
                    $zoneId = $zone['id'];
                    if ($zone['set_point_type'] == 'pilot_wire')
                    {
                        return array('error'=>'Cannot set temperature for wire order zone.');
                    }
                }
            }
            else
            {
                if ($zone['set_point_type'] != 'pilot_wire')
                {
                    $zoneId = $zone['id'];
                }
            }
        }

        if (!$zoneId)
        {
            return array('error'=>'Could not find zone.');
        }

        $hasTimeOrder = $this->hasTimeOrder($zoneName, $houseId);
        if ($hasTimeOrder['result'])
        {
            $this->cancelZoneOrder($zoneName, $houseId);
        }

        $post = '{"duration":'.$period.',"set_point":{"instruction":'.$value.'}}';
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/thermal-control/zones/'.$zoneId.'/temporary-instruction';
        $answer = $this->_request('POST', $url, $post);

        if ($this->isJson($answer))
        {
            $jsonData = json_decode($answer, true);
            if (isset($jsonData['set_point']['instruction']) && isset($jsonData['heating_status']))
            {
                return array('result'=>
                                array('instruction'=>$jsonData['set_point']['instruction'],
                                      'heating_status'=>$jsonData['heating_status']
                                  )
                            );
            }
            else
            {
                return array('error'=>'cannot get new order.');
            }
        }
        else
        {
            return array('error'=>'error while changing zone temperature');
        }
    }

    //@return['result'] true, @return['error'] if any
    public function setZoneMode($mode, $period=120, $zoneName=null, $houseId=0) {
        if (!$mode || !$zoneName)
        {
            return array('error'=>'You must specify mode name, period in minutes, zone name.');
        }
        $availValues = ['stop', 'frost_protection', 'eco', 'comfort_minus2', 'comfort_minus1', 'comfort'];
        if (!in_array($mode, $availValues))
        {
            return array('result'=>null, 'error'=>'Got wrong value, should be in '.implode(', ', $availValues));
        }

        $zoneId = null;
        $zones = $this->_houses[$houseId]['heating']['zones'];
        foreach ($zones as $zone) {
            if ($zone['title'] == $zoneName)
            {
                $zoneId = $zone['id'];
                if ($zone['set_point_type'] != 'pilot_wire')
                {
                    return array('error'=>'Cannot set temperature for wire order zone.');
                }
            }
        }

        if (!$zoneId)
        {
            return array('error'=>'Could not find zone.');
        }

        $hasTimeOrder = $this->hasTimeOrder($zoneName, $houseId);
        if ($hasTimeOrder['result'])
        {
            $this->cancelZoneOrder($zoneName, $houseId);
        }

        $post = '{"duration":'.$period.',"set_point":{"instruction":"'.$mode.'"}}';
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/thermal-control/zones/'.$zoneId.'/temporary-instruction';
        $answer = $this->_request('POST', $url, $post);

        if ($this->isJson($answer))
        {
            return array('result'=>true);
        }
        else
        {
            return array('error'=>'error while changing zone mode');
        }
    }

    //@return['result'] ['set_point']['instruction'], @return['error'] if any
    public function cancelZoneOrder($zoneName=null, $houseId=0) {
        $zoneId = null;
        $zones = $this->_houses[$houseId]['heating']['zones'];

        if (!$zoneName)
        {
            $zoneId = $zones[0]['id'];
        }
        else
        {
            foreach ($zones as $zone) {
                if ($zone['title'] == $zoneName)
                {
                    $zoneId = $zone['id'];
                    break;
                }
            }
            if (!$zoneId)
            {
                return array('error'=>'Could not find zone.');
            }
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/thermal-control/zones/'.$zoneId.'/temporary-instruction';
        $answer = $this->_request('DELETE', $url, null);

        if ($this->isJson($answer))
        {
            $jsonData = json_decode($answer, true);
            if (isset($jsonData['set_point']['instruction']))
            {
                return array('result'=>$jsonData['set_point']['instruction']);
            }
            else
            {
                return array('error'=>'cannot get new order.');
            }
        }
        else
        {
            return array('error'=>'error while setting departure');
        }
    }

    //@name string, @datas array | @return['result'] true, @return['error'] if any
    public function setProgram($name='', $houseId=0) {
        if ($this->isMultizone()['result'] === false)
        {
            return $this->setSchedule($name, $houseId);
        }

        if (isset($this->_houses[$houseId]['programs']))
        {
            $programs = $this->_houses[$houseId]['programs'];
        }
        else
        {
            $programs = $this->getPrograms($houseId)['result'];
        }

        foreach ($programs as $program) {
            if ($program['title'] == $name)
            {
                $id = $program['id'];
                $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/programs/'.$id.'/activate';
                $answer = $this->_request('POST', $url, null);
                if ($this->isJson($answer))
                {
                    return array('result'=>true);
                }
                else
                {
                    return array('error'=>'error while changing program');
                }
            }
        }
    }

    //No program in Monozone, setProgram() use setSchedule()
    //@name string, @datas array | @return['result'] true, @return['error'] if any
    public function setSchedule($name='', $houseId=0) {
        if (isset($this->_houses[$houseId]['schedules']))
        {
            $schedules = $this->_houses[$houseId]['schedules'];
        }
        else
        {
            $schedules = $this->getSchedules($houseId)['result'];
        }

        $program = $this->getCurrentProgram($houseId)['result'];
        $programId = $program['id'];

        $zone = $this->_houses[$houseId]['heating']['zones'][0];
        $zoneId = $zone['id'];

        foreach ($schedules as $schedule) {
            if ($schedule['title'] == $name)
            {
                $id = $schedule['id'];

                $data = array(
                    "programming_type"=>"connected",
                    "schedule_id"=>$id
                );

                echo 'setSchedule -> '.$id;


                $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/programs/'.$programId.'/zones/'.$zoneId;
                $post = json_encode($data);
                $answer = $this->_request('POST', $url, $post);
                if ($this->isJson($answer))
                {
                    return array('result'=>true);
                }
                else
                {
                    return array('error'=>'error while changing schedule');
                }
            }
        }
    }

    //$settingsAr array | @return['result'] true, @return['error'] if any
    public function setTempSettings($settingsAr, $houseId=0) {
        if (!is_array($settingsAr))
        {
            return array('error'=>'You must send settings array of value by 0.5.');
        }

        $post = json_encode($settingsAr);
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/custom-temperatures';
        $answer = $this->_request('PUT', $url, $post);

        if ($this->isJson($answer))
        {
            return array('result'=>true);
        }
        else
        {
            return array('error'=>'error while settings temperatures settings');
        }
    }

    //@return['result'] true, @return['error'] if any
    public function setAway($houseId=0) {
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/thermal-control/leave-home';
        $answer = $this->_request('POST', $url, null);

        if ($this->isJson($answer))
        {
            return array('result'=>true);
        }
        else
        {
            return array('error'=>'error while settings temperatures settings');
        }
    }

    //@return['result'] true, @return['error'] if any
    public function cancelAway($houseId=0) {
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/thermal-control/leave-home';
        $answer = $this->_request('DELETE', $url, null);

        if ($this->isJson($answer))
        {
            return array('result'=>true);
        }
        else
        {
            return array('error'=>'error while setting departure');
        }
    }

    //@$startDate, $endDate string like "2020-09-03T22:00:12.000Z" | @return['result'] true, @return['error'] if any
    public function setDeparture($startDate=null, $endDate=null, $houseId=0) {
        if (is_null($startDate) && is_null($endDate))
        {
            $post = '{}';
        }
        else
        {
            $post = '{"begin_at":"'.$startDate.'","end_at":"'.$endDate.'"}';
        }

        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/thermal-control/absence';
        $answer = $this->_request('POST', $url, $post);

        if ($this->isJson($answer))
        {
            return array('result'=>true);
        }
        else
        {
            return array('error'=>'error while setting departure');
        }
    }

    //@return['result'] true, @return['error'] if any
    public function cancelDeparture($houseId=0) {
        $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$houseId]['id'].'/thermal-control/absence';
        $answer = $this->_request('DELETE', $url, null);

        if ($this->isJson($answer))
        {
            return array('result'=>true);
        }
        else
        {
            return array('error'=>'error while setting departure');
        }
    }

    //INTERNAL FUNCTIONS==================================================
    protected function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    //CALLING FUNCTIONS===================================================
    //request thermostat datas
    protected function getDatas() {
        $url = $this->_urlRoot.'/park/housings';
        $answer = $this->_request('GET', $url);
        $jsonData = json_decode($answer, true);
        if (!isset($jsonData[0]['id']))
        {
            $this->error = 'Could not get any data!';
            return array('result'=>null, 'error' => $this->error);
        }

        for ($i=0; $i<count($jsonData); ++$i) {
            $this->_houses[$i] = $jsonData[$i];
            $url = $this->_urlRoot.'/thermal/housings/'.$this->_houses[$i]['id'].'/thermal-details';
            $answer = $this->_request('GET', $url);
            $this->_houses[$i]['heating'] = json_decode($answer, true);
        }
    }

    protected function _request($method, $url, $post=null) {
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
                'referer: https://app.comapsmarthome.com',
                'Host: api.comapsmarthome.com',
                'Authorization: Bearer '.$this->_token
            ];
            if ($method == 'POST')
            {
                array_push($headers, 'access-control-request-method: POST');
            }
            else
            {
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

    public $_houses = null;

    protected $_comapUser;
    protected $_comapUserPass;
    protected $_urlAuth = 'https://cognito-idp.eu-west-3.amazonaws.com';
    protected $_urlRoot = 'https://api.comapsmarthome.com';
    protected $_curlHdl = null;
    protected $_zoneModes = ['Thermostat', null, null, 'Confort', 'Eco', 'Arret', 'Hors-gel', 'Confort -1', 'Confort -2'];

    protected function connect() {
        $this->_curlHdl = curl_init();
        curl_setopt($this->_curlHdl, CURLOPT_HEADER, 0);
        curl_setopt($this->_curlHdl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($this->_curlHdl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->_curlHdl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYPEER, false);

        $post = '{
            "AuthFlow":"USER_PASSWORD_AUTH",
            "AuthParameters":{"USERNAME":"'.$this->_comapUser.'", "PASSWORD":"'.$this->_comapUserPass.'"},
            "ClientId":"56jcvrtejpracljtirq7qnob44"
        }';
        curl_setopt($this->_curlHdl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->_curlHdl, CURLOPT_POSTFIELDS, $post);

        $headers = [
            'access-control-request-method: POST',
            'origin: https://app.comapsmarthome.com',
            'referer: https://app.comapsmarthome.com/',
            'accept-encoding: gzip, deflate, br',
            'content-type: application/x-amz-json-1.1',
            'x-amz-target: AWSCognitoIdentityProviderService.InitiateAuth',
        ];
        curl_setopt($this->_curlHdl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->_curlHdl, CURLOPT_URL, $this->_urlAuth);
        $answer = curl_exec($this->_curlHdl);

        if ($this->isJson($answer))
        {
            $json = json_decode($answer, true);
            //error ?
            if (isset($json['__type']))
            {
                $this->error = 'Unknown error';
                if ($json['__type'] == 'UserNotFoundException')
                {
                    $this->error = 'User does not exist';
                }
                if ($json['__type'] == 'NotAuthorizedException')
                {
                    $this->error = 'Incorrect username or password';
                }
                return false;
            }

            //get idToken:
            if (isset($json['AuthenticationResult']['IdToken']))
            {
                $this->_token = $json['AuthenticationResult']['IdToken'];
                return true;
            }
            else
            {
                $this->error = 'Cannot find IdToken.';
                return false;
            }
        }
        else
        {
            $this->error = 'Authentification error.';
            return false;
        }
    }

    public function __construct($comapUser, $comapUserPass) {
        $this->_comapUser = $comapUser;
        $this->_comapUserPass = $comapUserPass;

        if ($this->connect() == true)
        {
            $this->getDatas();
        }
    }
} //qivivoAPIv2 end
?>
