<img align="right" src="/readmeAssets/qivivoAPI.jpg" width="150">

# php-qivivoAPI

## php API for Smart Qivivo Thermostat

This php API allows you to control your Smart Qivivo Thermostat.

The official Qivivo API doesn't provide any function yet for multi-zone.
In browser/app, at this time, when using multi-zone, Qivivo doesn't provide option to store/load different programs for each zone.<br />
So I developed this API in a few hours part-time to be able to do it, and not have to manually change each period of each day for each zone!<br />

If you need a simple api to use official Qivivo API without headaches: [php-simpleQivivoAPI](https://github.com/KiboOst/php-simpleQivivoAPI)

<img align="right" src="Jeedom/Assets/logoJeedom.png" width="64">

Jeedom user ? Check this [example](https://github.com/KiboOst/php-qivivoAPI/tree/master/Jeedom) of integration into Jeedom!

### Use case example
- Get your Qivivo data to trigger other actions.<br />
- Set a scenario in your smarthome (or trigger an url script) like "Going hollidays", to automatically change programs according to your scenario (*working, hollidays, away*).<br />
- Set your heating according to other weather sources (external Netatmo sensor, etc).<br />
- Set your heating if your interior camera recognize you.<br />
- As Qivivo doesn't support IFTTT yet, you can make your own trigger script!

*It is developed with a french account, so some data like days in programs will be in french. I can do it in en if other users need it. Feel free to submit an issue or pull request to add more.*

*This isn't an official API | USE AT YOUR OWN RISK!<br />
This API is reverse-engineered, provided for research and development for interoperability.*<br />

[Requirements](#requirements)<br />
[How-to](#how-to)<br />
[Connection](#connection)<br />
[Reading datas](#reading-operations)<br />
[Changing datas](#changing-operations)<br />
[Version history](#version-history)<br />

<img align="right" src="/readmeAssets/requirements.png" width="48">

## Requirements
- PHP v5+
- cURL (quite standard in PHP servers).
- The API require internet access (it will authenticate against Qivivo servers).

[&#8657;](#php-qivivoapi)
<img align="right" src="/readmeAssets/howto.png" width="48">
## How-to
- Download class/qivivoAPI.php and put it on your server.
- Include qivivoAPI.php in your script.
- Start it with your Qivivo username/password.
All function should return an array with 'result' or 'error'. So you can check for 'error' before getting 'result': if (!isset($answer['error']) ...

- This API

#### Connection

```php
require($_SERVER['DOCUMENT_ROOT'].'/path/to/qivivoAPI.php');
$_qivivo = new qivivoAPI($qivivo_user, $qivivo_pass);
if (isset($_qivivo->error)) echo $_qivivo->error;
```

[&#8657;](#php-qivivoapi)
Let the fun begin:
<img align="right" src="/readmeAssets/read.png" width="48">
#### READING OPERATIONS<br />

```php
//you can get all datas from thermostat and parse them if you need:
echo "<pre>_____>_qivivo:<br>".json_encode($_qivivo, JSON_PRETTY_PRINT)."</pre><br>";

//get home temperatures and temperatures settings:
$temps = $_qivivo->getTemperatures();
echo "<pre>_____>temps:<br>".json_encode($temps, JSON_PRETTY_PRINT)."</pre><br>";

//get actual mode of a non-thermostat zone: will return Confort -2, Confort -1, Hors-gel, Arrêt, Eco or Confort
$getZoneMode = $_qivivo->getZoneMode('Chambres');
echo "<pre>_____>getZoneMode:<br>".json_encode($getZoneMode, JSON_PRETTY_PRINT)."</pre><br>";

//get heating:
$heating = $_qivivo->getHeating();
echo "<pre>_____>heating:<br>".json_encode($heating, JSON_PRETTY_PRINT)."</pre><br>";

//get weather:
$weather = $_qivivo->getWeather();
echo "<pre>_____>weather:<br>".json_encode($weather, JSON_PRETTY_PRINT)."</pre><br>";

//get synthesis between two datas
//date format yearmonthdayhour. You can ask synthesis for a month, or just one dey like this for example:
$getSynthesis = $_qivivo->getSynthesis(201711050000, 201711060000);
echo "<pre>_____>getSynthesis:<br>".json_encode($getSynthesis, JSON_PRETTY_PRINT)."</pre><br>";

//get products with info (serial number, firmware, etc.)
$getProducts = $_qivivo->getProducts();
echo "<pre>_____>getProducts:<br>".json_encode($getProducts['result'], JSON_PRETTY_PRINT)."</pre><br>";

//get current program:
//will return array with zone thermostat, and others zones if multizone is activated.
$getCurrentProgram = $_qivivo->getCurrentProgram();
echo "<pre>_____>getCurrentProgram:<br>".json_encode($getCurrentProgram, JSON_PRETTY_PRINT)."</pre><br>";

//get program by name:
/*Will return readable datas like:
"Lundi": {
        "0:0 | 5:59": "nuit",
        "6:0 | 7:59": "pres_2",
        "8:0 | 17:29": "absence",
        "17:30 | 21:59": "pres_1",
        "22:0 | 23:59": "nuit"
    },
*/
$getProgram = $_qivivo->getProgram('programme 1');
echo "<pre>_____>getProgram:<br>".json_encode($getProgram, JSON_PRETTY_PRINT)."</pre><br>";

```

[&#8657;](#php-qivivoapi)
<img align="right" src="/readmeAssets/set.png" width="48">
#### CHANGING OPERATIONS<br />

```php
//change heating:
$setHeatingPower = $_qivivo->setHeatingPower(true);
echo "<pre>_____>setHeatingPower:<br>".json_encode($setHeatingPower, JSON_PRETTY_PRINT)."</pre><br>";

//set thermostat temperature
$setTemperature = $_qivivo->setTemperature(17.5, false);
echo "<pre>_____>setTemperature:<br>".json_encode($setTemperature, JSON_PRETTY_PRINT)."</pre><br>";

//set zone mode:
//available modes are: confort -2 -> 8, confort -1 -> 7, Hors-Gel -> 6, Arrêt -> 5, Eco -> 4, confort -> 3
$setZoneMode = $_qivivo->setZoneMode('MyRoom', 7);
echo "<pre>_____>setZoneMode:<br>".json_encode($setZoneMode['result'], JSON_PRETTY_PRINT)."</pre><br>";

//change temperatures settings:
//available settings are: 'pres_1', 'pres_2', 'pres_3', 'pres_4', 'confort', 'nuit', 'hg', 'absence'
$setTempSettings = $_qivivo->setTempSettings('pres_1', 19.5);
echo "<pre>_____>setTempSettings:<br>".json_encode($setTempSettings, JSON_PRETTY_PRINT)."</pre><br>";

//set departure alert setting, in day number:
$setDepartureAlert = $_qivivo->setDepartureAlert(2);
echo "<pre>_____>setDepartureAlert:<br>".json_encode($setDepartureAlert, JSON_PRETTY_PRINT)."</pre><br>";

//change program:
/*
There is two sorts of programs, for thermostat zone or other zones.
- Thermostat zone available values are:
'pres_1', 'pres_2', 'pres_3', 'pres_4', 'confort', 'nuit', 'hg', 'absence'
- Other zone values are:
'mz_comfort', 'mz_comfort_minus_one', 'mz_comfort_minus_two', 'mz_eco', 'mz_frost', 'mz_off'

You will first have to build an array for all days starting from monday, with periods and setting.
You can store different arrays for different programs, and just change them in a few seconds.
*/
//change thermostat zone program example:
$dayType1 = [['0:0', '5:29', 'nuit'],
             ['5:30', '8:14', 'pres_3'],
             ['8:15', '15:59', 'nuit'],
             ['16:0', '17:29', 'pres_2'],
             ['17:30', '23:59', 'pres_3']];
$dayType2 = [['0:0', '7:29', 'nuit'],
             ['7:30', '21:59', 'pres_3'],
             ['22:0', '23:59', 'pres_2']];
$myWorkMasterProgram = [$dayType1, $dayType1, $dayType2, $dayType1, $dayType1, $dayType2, $dayType2];
$setProgram = $_qivivo->setProgram('Semaine Travail', $myWorkMasterProgram);
echo "<pre>_____>setProgram:<br>".json_encode($setProgram, JSON_PRETTY_PRINT)."</pre><br>";

//change other zone program example:
$dayType1 = [['0:0', '5:29', 'mz_eco'],
             ['5:30', '7:59', 'mz_comfort'],
             ['8:0', '17:29', 'mz_eco'],
             ['17:30', '21:59', 'mz_comfort_minus_one'],
             ['22:0', '23:59', 'mz_eco']];
$dayType2 = [['0:0', '5:29', 'mz_eco'],
             ['5:30', '9:29', 'mz_comfort'],
             ['9:30', '17:29', 'mz_comfort_minus_two'],
             ['17:30', '21:59', 'mz_comfort_minus_one'],
             ['22:0', '23:59', 'mz_eco']];
$myWorkZoneProgram = [$dayType1, $dayType1, $dayType2, $dayType1, $dayType1, $dayType2, $dayType2];
$setProgram = $_qivivo->setProgram('mz_Chambres', $myWorkZoneProgram);
echo "<pre>_____>setProgram:<br>".json_encode($setProgram, JSON_PRETTY_PRINT)."</pre><br>";

```

[&#8657;](#php-qivivoapi)
<img align="right" src="/readmeAssets/changes.png" width="48">
## Version history

#### v0.5 (2018-12-27)
- New: setZoneMode($zone, $mode)

#### v0.4 (2018-03-27)
- fix for Qivivo https switch

#### v0.25 (2018-03-01)
- New : getZoneMode()

#### v0.2 (2017-11-07)
- New : setTemperature()
- New : getSynthesis()
- Change: getTemperatures() now return message

#### v0.1 (2017-11-07)
- First public version!

[&#8657;](#php-qivivoapi)
<img align="right" src="/readmeAssets/mit.png" width="48">
## License

The MIT License (MIT)

Copyright (c) 2018 KiboOst

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
