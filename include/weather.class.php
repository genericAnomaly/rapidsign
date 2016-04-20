<?php

define('WEATHER_FORECAST_URL',		'http://forecast.weather.gov/MapClick.php?');
define('WEATHER_USERAGENT_STRING',	'CambridgeCollegeRapidSignage/0.2.0 (Web Application, https://github.com/genericAnomaly/rapidsign)');
define('WEATHER_CACHE_MINUTES',		'30');

//http://forecast.weather.gov/MapClick.php?lat=42.3798&lon=-71.1284&FcstType=json


class Weather {
	
	private $lat;
	private $lon;
	
	public function __construct($lat, $lon) {
		$this->setLocation($lat, $lon);
	}
	
	public function setLocation($lat, $lon) {
		$this->lat = $lat;
		$this->lon = $lon;
	}
	
	
	public function fetchForecast() {
		//Build the query
		$query = array(	'lat'		=> $this->lat,
						'lon'		=> $this->lon,
						'FcstType'	=> 'json');
		$query = http_build_query($query);
		
		//Create a cURL handle and populate its options
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, WEATHER_FORECAST_URL.$query);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		
		//Required by forecast.weather.gov
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_USERAGENT, WEATHER_USERAGENT_STRING);
		
		//Execute it
		$result = curl_exec($ch);
		
		return $result;
	}
	
	
	public function loadForecast() {
		//Open a handle to the DB
		$dbh = db_connect();
		
		//Check the DB for a cached forecast
		$where = array(	'lat'		=> $this->lat,
						'lon'		=> $this->lon);
		$cache = db_fetchForecast($dbh, $where);
		
		$forecast = $cache['json'];
		
		$refresh = false;
		//If no cache exists, refresh
		if ($cache == null) $refresh = true;

		//If  freshness is older than threshold, refresh
		if ($cache != null) {
			$cacheFreshness = new DateTime($cache['fresh']);
			$now = new DateTime();
			if ($now > $cacheFreshness) $refresh = true;
		}
		
		//If a refresh is needed, pull fresh data, determine its fresh-til DateTime, and push it to the DB
		if ($refresh == true) {
			$forecast = $this->fetchForecast();
			$freshUntil = new DateTime();
			$threshold = new DateInterval('PT'.WEATHER_CACHE_MINUTES.'M');	//Yeah, I hate it too.
			$freshUntil->add($threshold);
			$what = array(	'lat'		=> $this->lat,
							'lon'		=> $this->lon,
							'fresh'		=> $freshUntil->format('Y-m-d H:i:s'),
							'json'		=> $forecast);
			db_updateForecast($dbh, $what);
		}
		
		//Return either the cached or fresh forecast
		return $forecast;
	}
	
	public function getForecastJSON() {
		//For the sake of removing ambiguity
		return $this->loadForecast();
	}

	
	
}


?>