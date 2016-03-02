<?php

include('include/config.php');
include('include/weather.class.php');
include('include/db.php');


//http://forecast.weather.gov/MapClick.php?lat=42.3798&lon=-71.1284&FcstType=json


header('content-type:text/plain');

$w = new Weather(42.3798, -71.1284);
//$w->fetchForecast();

print $w->loadForecast();

?>