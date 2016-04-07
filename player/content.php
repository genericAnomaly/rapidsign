<?php

require_once('../include/config.php');
require_once('../include/db.php');
require_once('../include/weather.class.php');

/*
$sites = astra_fetchBuildings(astra_createHandle());
$sites = $sites['data'];
$array = array();
foreach($sites as $site) {
	$s['name'] = $site['Name'];
	$s['guid'] = $site['Id'];
	$array[] = $s;
}
header('content-type:text/plain');
print_r($sites);
*/



//Initialise DB connection
$dbh = db_connect();



//Get player row of player to perform fetch for
$where = array();
if (isset($_REQUEST['id'])) $where['player_id'] = $_REQUEST['id'];
if (isset($_REQUEST['name'])) $where['player_shortname'] = $_REQUEST['name'];
if (isset($_REQUEST['player_id'])) $where['player_id'] = $_REQUEST['player_id'];
if (isset($_REQUEST['player_shortname'])) $where['player_shortname'] = $_REQUEST['player_shortname'];
$player = db_fetchPlayerInfo($dbh, $where);
//TODO: Error handling, make sure a player was fetched
//die(json_encode($player));


//Get the slides
$player['range'] = 'current';
$slides = db_fetchDurations($dbh, $player);



//Query astra for schedule
$ah = astra_createHandle();
$schedule = astra_fetchSchedule($ah, $player['astra_guid']);



//Pack it up
$pack = array();

$row['type'] = 'schedule';
$row['content'] = $schedule;
$pack[] = $row;

foreach($slides as $slide) {
	$row['type'] = 'image';
	$row['content'] = $slide['content_src'];
	$pack[] = $row;
}




//Weather junk!
//TODO: associate lat/lon values with signs somehow. Really need a good model for this stuff to be more modular
$lat = 42.3798;
$lon = -71.1284;
//TODO: pass $dbh into Weather class?

$w = new Weather($lat, $lon);
$forecast = $w->getForecastJSON();
$forecast = json_decode($forecast, true);	//Just to be extra counterintuitive
$secondary = array();
$secondary[] = array('type' => 'forecast', 'content' => $forecast);











//Wait up that's just one region
$output['primary'] = $pack;
$output['secondary'] = $secondary;
$pack = $output;

header('content-type:text/plain');
//$json = json_encode($pack, JSON_PRETTY_PRINT);
$json = json_encode($pack); //Can't do that in old timey php without that weird upcompatibility version shim :0

echo $json;












?>