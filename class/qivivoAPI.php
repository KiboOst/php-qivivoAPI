<?php
/*

https://github.com/KiboOst/php-qivivoAPI

*/

class qivivoAPI {

    public $_version = '0.5';

    //USER FUNCTIONS======================================================
    //GET FUNCTIONS:
    public function refreshDatas()
    {
        $this->getDatas();
    }
    public function getHeating() //@return['result'] array with heating datas
    {
        if ($this->_fullDatas == null) $datas = $this->getDatas();
        if ( isset($datas['error']) ) return $datas;
        return array('result'=>$this->_fullDatas['heating']);
    }

    public function getTemperatures() //@return['result'] array with temperatures and settings
    {
        if ($this->_fullDatas == null) $datas = $this->getDatas();
        if ( isset($datas['error']) ) return $datas;
        $datas = $this->_fullDatas['temperatures'];
        $datas['message'] = $this->_fullDatas['message'];
        return array('result'=>$datas);
    }

    public function getWeather() //@return['result'] array with weather datas
    {
        if ($this->_fullDatas == null) $datas = $this->getDatas();
        if ( isset($datas['error']) ) return $datas;
        return array('result'=>$this->_fullDatas['weather']);
    }

    public function getZoneMode($zone) //@return['result'] array with current zone mode
    {
        foreach ($this->_fullDatas['multizone']['wirelessModules'] as $mod)
        {
            if ($mod['zone_name'] == $zone)
            {
                $currentModeIdx = $mod['zone_current_mode'];
                $currentMode = $this->_zoneModes[$currentModeIdx];
                return array('result'=>$currentMode);
            }
        }
        return array('result'=> null, 'error'=>'Unfound zone');
    }

    public function getProgram($name='') //@return['result'] array with readable formated program
    {
        if ($this->_fullDatas == null) $datas = $this->getDatas();
        if ( isset($datas['error']) ) return $datas;

        $programs = $this->_fullDatas['plannings']['plannings'];
        $foundProg = false;
        foreach($programs as $prog)
        {
            if ($name == $prog['name']) $foundProg = $prog['days'];
        }

        //planning found?
        if ($foundProg)
        {
            $prog = $this->programToReadable($foundProg);
            return array('result'=>$prog);
        }
        return array('result'=>null, 'error'=>'Unfound program');
    }

    public function getCurrentProgram() //@return['result'] array with current program selection, multizone supported
    {
        if ($this->_fullDatas == null) $datas = $this->getDatas();
        if ( isset($datas['error']) ) return $datas;

        $programs = $this->_fullDatas['plannings']['plannings'];
        $result = array();

        //get only zone or multizone thermostat zone:
        $ID = $this->_fullDatas['plannings']['current_planning'];
        foreach($programs as $prog)
        {
            if ($ID == $prog['id']) $result['Zone Thermostat'] = $prog['name'];
        }

        //get other zone if any:
        if ($this->_isMultizone)
        {
            $modules = $this->_fullDatas['multizone']['wirelessModules'];
            foreach($modules as $module)
            {
                $zoneName = $module['zone_name'];
                if ($zoneName != 'Zone Thermostat')
                {
                    $current_mode = $module['zone_current_mode'];
                    $zoneuid = $module['zone_uid'];
                    foreach($programs as $prog)
                    {
                        if ($zoneuid == $prog['id']) $result[$zoneName] = $prog['name'];
                    }
                }
            }
        }
        return array('result'=>$result);
    }

    public function getSynthesis($dateStart, $dateEnd)
    {
        $url = $this->_urlRoot.'/synthese/month';
        $post = 'dateStart='.$dateStart.'&dateEnd='.$dateEnd;
        $answer = $this->_request('POST', $url, $post, $xmlRequest=true);
        $jsonAnswer = json_decode($answer, true);
        if (!isset($jsonAnswer['success'])) return array('result'=>null, 'error'=>$jsonAnswer);
        unset($jsonAnswer['content']['synthesisData']['success']);
        return array('result'=>$jsonAnswer['content']['synthesisData']);
    }

    public function getProducts()
    {
        $url = 'https://www.qivivo.com/fr/mon-compte/produits';
        $answer = $this->_request('GET', $url);

        //load it as html document:
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">'.$answer);

        //get all <div class="product">:
        $classname = 'product';
        $finder = new DomXPath($dom);
        $nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
        $tmp_dom = new DOMDocument();
        foreach ($nodes as $node)
        {
            $tmp_dom->appendChild($tmp_dom->importNode($node, true));
        }
        $innerHTML = trim($tmp_dom->saveHTML());

        //set that in an array:
        $datas = array();
        $in = array();
        $newArray = ['Qiviv', 'Modul', 'Passe'];
        foreach(preg_split("/((\r?\n)|(\r\n?))/", $innerHTML) as $line)
        {
            $lineClean = trim(strip_tags($line));
            if (strlen($lineClean) == 0) continue;

            $lineStart = substr(trim($line), 0, 3);
            if ($lineStart == '<h3') //new element
            {
                array_push($datas, $in);
                $in = array();
                array_push($in, $lineClean);
            }
            if ($lineStart == '<ul')
            {
                array_push($in, $lineClean);
            }
            if ($lineStart == '<li')
            {
                array_push($in, $lineClean);
            }
        }
        //first entry is empty:
        $datas = array_splice($datas, 1);
        return array('result'=>$datas);
    }

    //SET FUNCTIONS:
    public function setTemperature($value, $programName=false)
    {
        $url = $this->_urlRoot.'/temps-reel/temperature';
        $myID = false;

        if ($programName)
        {
            $programs = $this->_fullDatas['plannings']['plannings'];
            foreach($programs as $prog)
            {
                if ($programName == $prog['name']) $myID = $prog['id'];
            }
            if (!$myID) return array('result'=>null, 'error'=>'Sorry, could not find program '.$name);
        }

        $post = 'service=QTC&wantedTemperature='.$value.'&planningId=';
        if ($myID) $post .= $myID;
        $answer = $this->_request('POST', $url, $post, $xmlRequest=true);
        $jsonAnswer = json_decode($answer, true);
        if (!isset($jsonAnswer['success']['state'])) return array('result'=>null, 'error'=>$jsonAnswer);
        return array('result'=>true);
    }

    public function setZoneMode($zone, $mode)
    {
        $url = $this->_urlRoot.'/temps-reel/mode';
        $programId = -1;
        foreach($this->_fullDatas['multizone']['wirelessModules'] as $module)
        {
            if ($module['zone_name'] == $zone)
            {
                $programId = $module['zone_uid'];
                break;
            }
        }
        if ($programId != -1)
        {
            $post = 'wantedMode='.$mode.'&programId='.$programId;
            $answer = $this->_request('POST', $url, $post, $xmlRequest=true);
            $jsonAnswer = json_decode($answer, true);
            if (!isset($jsonAnswer['success']['state'])) return array('result'=>null, 'error'=>$jsonAnswer);
            return array('result'=>true);
        }
        return array('result'=>null, 'error'=>'unfound zone');

        /*
        wantedMode:
            confort -2 -> 8
            confort -1 -> 7
            Hors-Gel -> 6
            Arrêt -> 5
            Eco -> 4
            confort -> 3
        */
    }

    public function setHeatingPower($state=true) //@state false/true | @return['result'] true, @return['error'] if any
    {
        $state = var_export($state, true);
        $availValues = ['false', 'true'];
        if (!in_array($state, $availValues)) return array('result'=>null, 'error'=>'Got wrong value, should be in '.implode(', ', $availValues));

        $url = $this->_urlRoot.'/settings-heating-power';
        $post = 'state='.$state;
        $answer = $this->_request('POST', $url, $post, $xmlRequest=true);
        $jsonAnswer = json_decode($answer, true);
        if (!isset($jsonAnswer['success']['state'])) return array('result'=>null, 'error'=>$jsonAnswer);
        return array('result'=>true);

    }
    public function setTempSettings($code='', $value='') //@code in availValues | @return['result'] true, @return['error'] if any
    {
        $availValues = ['pres_1', 'pres_2', 'pres_3', 'pres_4', 'confort', 'nuit', 'hg', 'absence'];
        if (!in_array($code, $availValues)) return array('result'=>null, 'error'=>'Got wrong value, should be in '.implode(', ', $availValues));

        $url = $this->_urlRoot.'/settings';
        $post = $code.'='.strval($value);
        $answer = $this->_request('POST', $url, $post, $xmlRequest=true);
        $jsonAnswer = json_decode($answer, true);
        if (!isset($jsonAnswer['success']['state'])) return array('result'=>null, 'error'=>$jsonAnswer);
        return array('result'=>true);
    }

    public function setDepartureAlert($days=3) //@days in availValues | @return['result'] true, @return['error'] if any
    {
        //$url = $this->_urlRoot.'/update-alert'; ??
        $availValues = [1, 1.5, 2, 2.5, 3, 3.5, 4];
        if (!in_array($days, $availValues)) return array('result'=>null, 'error'=>'Got wrong value, should be in '.implode(', ', $availValues));

        $url = $this->_urlRoot.'/settings-absence';
        $post = 'dayNumber='.$days;
        $answer = $this->_request('POST', $url, $post, $xmlRequest=true);
        $jsonAnswer = json_decode($answer, true);
        if (!isset($jsonAnswer['success']['state'])) return array('result'=>null, 'error'=>$jsonAnswer);
        return array('result'=>true);
    }

    public function setProgram($name='', $datas=false) //@name string, @datas array | @return['result'] true, @return['error'] if any
    {
        //get planning id:
        $programs = $this->_fullDatas['plannings']['plannings'];
        $myID = false;
        foreach($programs as $prog)
        {
            if ($name == $prog['name']) $myID = $prog['id'];
        }
        if (!$myID) return array('result'=>null, 'error'=>'Sorry, could not find program '.$name);

        //set program day per day:
        $url = $this->_urlRoot.'/programmation/set-periods-byday';
        $dayId = 0;
        foreach($datas as $day)
        {
            $postDatas = 'programId='.$myID;
            $postDatas .= '&dayId='.$dayId;
            $postPlanning = '';
            $periodID = 0;
            foreach($day as $period)
            {
                $postPlanning .= '&dayProgram['.$periodID.'][0]='.intval(explode(':', $period[0])[0]);
                $postPlanning .= '&dayProgram['.$periodID.'][1]='.intval(explode(':', $period[0])[1]);
                $postPlanning .= '&dayProgram['.$periodID.'][2]='.intval(explode(':', $period[1])[0]);
                $postPlanning .= '&dayProgram['.$periodID.'][3]='.intval(explode(':', $period[1])[1]);
                $postPlanning .= '&dayProgram['.$periodID.'][4]='.$period[2];
                $periodID += 1;
            }
            $postDatas .=  $postPlanning;
            $postDatas = urlencode($postDatas);
            $postDatas = str_replace(array('%3D', '%26'), array('=', '&'), $postDatas);
            $answer = $this->_request('POST', $url, $postDatas, $xmlRequest=true);
            $jsonAnswer = json_decode($answer, true);
            if (!isset($jsonAnswer['success']['state'])) return array('result'=>null, 'error'=>$jsonAnswer);
            if ($jsonAnswer['success']['state'] != 'success') return array('result'=>null, 'error'=>$jsonAnswer);
            $dayId += 1;
        }
        return array('result'=>true);
    }

    //INTERNAL FUNCTIONS==================================================
    protected function programToReadable($program) //datas from qivivo['days'] to readable datas per day
    {
        $programDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $prog = array();
        $dayIdx = 0;
        foreach($program as $progDay)
        {
            $prog[$programDays[$dayIdx]] = array();
            foreach($progDay as $hour)
            {
                $hourFrom = $hour[0].':'.$hour[1];
                $hourTo = $hour[2].':'.$hour[3];
                $progCode = $hour[4];
                $hourStr = $hourFrom.' | '.$hourTo;
                $prog[$programDays[$dayIdx]][$hourStr] = $progCode;
            }
            $dayIdx += 1;
        }
        return $prog;
    }

    //CALLING FUNCTIONS===================================================
    protected function getDatas() //request thermostat datas
    {
        $url = $this->_urlRoot.'/temps-reel/update';
        $answer = $this->_request('GET', $url, false, true);
        $jsonDatas = json_decode($answer, true);

        if (!isset($jsonDatas['success']))
        {
            $this->error = 'Could not get any datas!';
            return array('result'=>null, 'error' => $this->error);
        }
        if ($jsonDatas['success'] != 'success')
        {
            $this->error = 'Datas not available!';
            return array('result'=>null, 'error' => $this->error);
        }


        $this->_fullDatas = $jsonDatas['content'];
        if ($this->_fullDatas['multizone']['status'] == true)
        {
            $this->_isMultizone = true;
        }
        return array('result'=>true);
    }

    protected function _request($method, $url, $post=null, $xmlRequest=false)
    {
        if (!isset($this->_curlHdl))
        {
            $this->_curlHdl = curl_init();
            curl_setopt($this->_curlHdl, CURLOPT_CONNECTTIMEOUT, 5);

            curl_setopt($this->_curlHdl, CURLOPT_COOKIEFILE, $this->_cookFile);
            curl_setopt($this->_curlHdl, CURLOPT_COOKIEJAR, $this->_cookFile);

            curl_setopt($this->_curlHdl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->_curlHdl, CURLOPT_HEADER, true);
            curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->_curlHdl, CURLOPT_REFERER, 'https://www.qivivo.com/account/');
            curl_setopt($this->_curlHdl, CURLOPT_USERAGENT, 'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:51.0) Gecko/20100101 Firefox/51.0');
            curl_setopt($this->_curlHdl, CURLOPT_ENCODING , 'gzip, deflate');
        }

        curl_setopt($this->_curlHdl, CURLOPT_URL, $url);

        if ($method == 'POST')
        {
            curl_setopt($this->_curlHdl, CURLOPT_HTTPGET, false);
            curl_setopt($this->_curlHdl, CURLOPT_FOLLOWLOCATION, true);

            curl_setopt($this->_curlHdl, CURLOPT_POST, false);
            curl_setopt($this->_curlHdl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($this->_curlHdl, CURLOPT_POSTFIELDS, $post);

            //should have token after login:
            if (isset($this->_token))
            {
                curl_setopt($this->_curlHdl, CURLOPT_HEADER, false);
                curl_setopt($this->_curlHdl, CURLOPT_HTTPHEADER, array(
                                                        'Connection: keep-alive',
                                                        'Origin: https://www.qivivo.com',
                                                        'Referer: https://www.qivivo.com/fr/login',
                                                        'Upgrade-Insecure-Requests: 1',
                                                        'Accept-Encoding: gzip',
                                                        'Content-Type: application/x-www-form-urlencoded',
                                                        'Authorization: Bearer '.$this->_token
                                                        )
                                                    );
            }
        }
        else
        {
            curl_setopt($this->_curlHdl, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($this->_curlHdl, CURLOPT_HTTPGET, true);
            curl_setopt($this->_curlHdl, CURLOPT_POST, false);
        }

        if ($xmlRequest)
        {
            curl_setopt($this->_curlHdl, CURLOPT_HEADER, false);
            curl_setopt($this->_curlHdl, CURLOPT_HTTPHEADER, array(
                                                    'Host: www.qivivo.com',
                                                    'Accept: */*',
                                                    'Accept-Language: fr-FR,fr;q=0.8,en-US;q=0.5,en;q=0.3',
                                                    'Accept-Encoding: gzip',
                                                    'Referer: https://www.qivivo.com/account/',
                                                    'Content-Type: application/x-www-form-urlencoded',
                                                    'X-Requested-With: XMLHttpRequest',
                                                    'Connection: keep-alive'
                                                    )
                                                );
        }


        $response = curl_exec($this->_curlHdl);

        //$info   = curl_getinfo($this->_curlHdl);
        //echo "<pre>cURL info".json_encode($info, JSON_PRETTY_PRINT)."</pre><br>";

        if(curl_errno($this->_curlHdl))
        {
            echo 'Curl error: '.curl_error($this->_curlHdl);
        }

        if ($response === false)
        {
            echo 'cURL error: '.curl_error($this->_curlHdl);
        }
        else
        {
            return $response;
        }
    }

    //AUTHORIZATION=======================================================
    public $error = null;
    public $_csrf = null;
    public $_csrfName = null;
    public $_token = null;

    public $_fullDatas = null;
    public $_isMultizone = false;

    protected $_qivivo_user;
    protected $_qivivo_pass;
    protected $_urlAuth = 'https://www.qivivo.com/fr/login';
    protected $_urlAuthCheck = 'https://www.qivivo.com/fr/login_check';
    protected $_urlRoot = 'https://www.qivivo.com/account';
    protected $_curlHdl = null;
    protected $_cookFile = '';
    protected $_zoneModes = ['Thermostat', null, null, 'Confort', 'Eco', 'Arret', 'Hors-gel', 'Confort -1', 'Confort -2'];

    protected function getCSRF($answerString)
    {
        preg_match_all("/( name=\"_csrf_token\" value=\")(.*)(\")/m", $answerString, $matches);
        if (isset($matches[2][0]))
        {
            $this->_csrfName = '_csrf_token';
            $this->_csrf = $matches[2][0];
            return true;
        }
        return false;
    }

    protected function connect()
    {
        //get csrf, required for login and all post requests:
        $url = $this->_urlAuth;
        $answer = $this->_request('GET', $url);

        $var = $this->getCSRF($answer);
        if ($var == false)
        {
            $this->error = 'Could not find qivivo CSRF.';
            return false;
        }

        //get token, required for all post requests as bearer auth:
        $url = $this->_urlAuthCheck;
        $post = '_username='.$this->_qivivo_user.'&_password='.$this->_qivivo_pass.'&_csrf_token='.$this->_csrf;
        $answer = $this->_request('POST', $url, $post);

        $cookies = explode('Set-Cookie: ', $answer);
        foreach($cookies as $var)
        {
            if (strpos($var, 'REMEMBERME=') === 0)
            {
                $cookieValue = explode(';', $var)[0];
                $cookieValue = str_replace('REMEMBERME=', '', $cookieValue);
                $token = urldecode($cookieValue);
                if ($token != 'deleted')
                {
                    $this->_token = $token;
                    return true;
                }
            }

        }
        //unfound valid token:
        $this->error = 'Could not find qivivo token.';
        return false;
    }

    function __construct($qivivo_user, $qivivo_pass, $homeName=0)
    {
        $this->_qivivo_user = urlencode($qivivo_user);
        $this->_qivivo_pass = urlencode($qivivo_pass);

        if ($this->connect() == true)
        {
            $this->getDatas();
        }
    }
} //qivivoAPI end
?>
