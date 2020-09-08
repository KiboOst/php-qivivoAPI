<img align="right" src="/readmeAssets/qivivoAPI.jpg" width="150">

# php-qivivoAPI

## php API for Smart Qivivo / Comap Thermostat

This php API allows you to control your Smart Qivivo / Comap Thermostat.

The official Qivivo API doesn't provide any function yet for multi-zone.

If you need a simple api to use official Qivivo API without headaches: [php-simpleQivivoAPI](https://github.com/KiboOst/php-simpleQivivoAPI)

Jeedom user ? Check this  [Jeedom Plugin !](https://kiboost.github.io/jeedom_docs/plugins/qivivo/fr_FR/)

### Use case example
- Get your Qivivo data to trigger other actions.<br />
- Set a scenario in your smarthome (or trigger an url script) like "Going hollidays", to automatically change programs according to your scenario (*working, hollidays, away*).<br />
- Set your heating according to other weather sources (external Netatmo sensor, etc).<br />
- Set your heating if your interior camera recognize you.<br />
- As Qivivo doesn't support IFTTT yet, you can make your own trigger script!


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
- Download class/qivivoAPIv2.php and put it on your server.
- Include qivivoAPI.php in your script.
- Start it with your Qivivo username/password.
All function should return an array with 'result' or 'error'. So you can check for 'error' before getting 'result': if (!isset($answer['error']) ...

- This API

#### Connection

```php
require($_SERVER['DOCUMENT_ROOT'].'/path/to/qivivoAPIv2.php');
$_qivivo = new qivivoAPI($qivivo_user, $qivivo_pass);
if (isset($_qivivo->error)) echo $_qivivo->error;
```

[&#8657;](#php-qivivoapi)
Let the fun begin:
<img align="right" src="/readmeAssets/read.png" width="48">
#### READING OPERATIONS<br />

```php

//get heating:
$heating = $_qivivo->getHeating();
echo "<pre>_____>heating:<br>".json_encode($heating, JSON_PRETTY_PRINT)."</pre><br>";

//get temperatures settings:
$settings = $_qivivo->getTempSettings();
echo "<pre>_____>settings:<br>".json_encode($settings, JSON_PRETTY_PRINT)."</pre><br>";

//get devices with info (serial number, firmware, etc.):
$getDevices = $_qivivo->getDevices();
echo "<pre>_____>getDevices:<br>".json_encode($getDevices['result'], JSON_PRETTY_PRINT)."</pre><br>";

//get array of serial=>devices with zone, current order etc:
$getFullDevices = $_qivivo->getFullDevices();
echo "<pre>_____>getFullDevices:<br>".json_encode($getFullDevices['result'], JSON_PRETTY_PRINT)."</pre><br>";

//get zones, with name, id, type, connected objects serials:
$getZones = $_qivivo->getZones();
echo "<pre>_____>getZones:<br>".json_encode($getZones, JSON_PRETTY_PRINT)."</pre><br>";

//get zone events (temporary_instruction, ...):
$getZoneEvents = $_qivivo->getZoneEvents('Chambres');
echo "<pre>_____>getZoneEvents:<br>".json_encode($getZoneEvents, JSON_PRETTY_PRINT)."</pre><br>";

//get name of current program:
$getCurrentProgram = $_qivivo->getCurrentProgram();
echo "<pre>_____>getCurrentProgram:<br>".json_encode($getCurrentProgram, JSON_PRETTY_PRINT)."</pre><br>";

//get all programs, with shedule id per zone:
$getPrograms = $_qivivo->getPrograms();
echo "<pre>_____>getPrograms:<br>".json_encode($getPrograms, JSON_PRETTY_PRINT)."</pre><br>";

//get all shedules, with days time slots:
$getSchedules = $_qivivo->getSchedules();
echo "<pre>_____>getSchedules:<br>".json_encode($getSchedules, JSON_PRETTY_PRINT)."</pre><br>";

//get weather:
$weather = $_qivivo->getWeather();
echo "<pre>_____>weather:<br>".json_encode($weather, JSON_PRETTY_PRINT)."</pre><br>";

```

[&#8657;](#php-qivivoapi)
<img align="right" src="/readmeAssets/set.png" width="48">
#### CHANGING OPERATIONS<br />

```php
//change heating:
$setHeating = $_qivivo->setHeating(true);

//set thermostat temperature (Second argument is duration in minutes, can be omitted default 120. Last argument not necessary if one thermostat only):
$setTemperature = $_qivivo->setTemperature(15, 120, 'Salle');

//set zone mode (Second argument is duration in minutes):
//available modes are: 'stop', 'eco', 'comfort_minus2', 'comfort_minus1', 'comfort'
$setZoneMode = $_qivivo->setZoneMode('comfort_minus1', 120, 'Chambres');

//change running program:
$_qivivo->setProgram('Absence');


//change temperatures settings:
$settingsAr = array(
                    "away"=>15.5,
                    "frost_protection"=>12,
                    "night"=>17.5,
                    "connected"=>array(
                                    "presence_1"=>18,
                                    "presence_2"=>19,
                                    "presence_3"=>20,
                                    "presence_4"=>20.5
                                ),
                    "smart"=>array(
                                "comfort"=>19
                            )
                );
$_qivivo->setTempSettings($settingsAr);


//set /cancel away:
$_qivivo->setAway();
$_qivivo->cancelAway();

//set / cancel departure:
$startDate = "2020-09-03T22:00:00.000Z";
$endDate = "2020-09-13T12:00:00.000Z";
$_qivivo->setDeparture($startDate, $endDate);
$_qivivo->cancelDeparture();


```

[&#8657;](#php-qivivoapi)
<img align="right" src="/readmeAssets/changes.png" width="48">
## Version history

#### v2.0 (2020-09-07)
- New v2 version for new Comap interface!
Read the doc : Lot of changes in functions and returns. Less functions regarding programs as all is editable in Comap interface now!

#### v0.6 (2019-05-26)
- Qivivo servers switch to Comap.

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

Copyright (c) 2020 KiboOst

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
