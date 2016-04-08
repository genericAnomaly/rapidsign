<?php
	require_once('../include/config.php');
	require_once('../include/db.php');
	
	/*
	$where = array();
	if (isset($_GET['player_id'])) {
		$where['player_id'] = $_GET['player_id'];
	} else if (isset($_GET['name'])) {
		$where['player_shortname'] = $_GET['name'];
	} else if (isset($_GET['player'])) {
		//legacy compatibility
		$where['player_shortname'] = $_GET['player'];
	} else {
		//default to first player
		$where['player_id'] = 1;
	}
	*/
	
	$where = array();
	if (isset($_GET['id'])) $where['player_id'] = $_GET['id'];
	if (isset($_GET['name'])) $where['player_shortname'] = $_GET['name'];
	if (isset($_GET['player_id'])) $where['player_id'] = $_GET['player_id'];
	if (isset($_GET['player_shortname'])) $where['player_shortname'] = $_GET['player_shortname'];
	
	$dbh = db_connect();
	$player = db_fetchPlayerInfo($dbh, $where);
	$json = json_encode($player);

?><!doctype html>
<html>
<head>
	<meta charset="utf-8" />
	<title>Interim Digital Sign</title>
	
	<link rel="stylesheet" href="style.css" />
	
	<!--
	<link rel="icon" href="images/favicon.png" type="image/png" />
	<link rel="icon" href="images/favicon.ico" type="image/x-icon" />
	-->
	
	
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
	<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
	
	
	<script>
	
	var player = <?php echo $json ?>;
	var refresh = false;
	
	//Global vars for communicating between asynchronous stuff COS IDK IT SEEMS LIKE THERE SHOULD BE A BETTER WAY
	var cachedContent = false;
	var cacheRefreshed = false;
	var regionsActivated = false;
	
	//Page transition settings
	//TODO: get these from the db instead of hardcoding them
	var pageDelay = 10;			//Seconds between pages
	var transitionEffect = 'slide';
	var refreshDelay = 60;		//Seconds between content refreshes;
	
	//Initialise
	$(function() {
		requestContent();
		//activateRegions();
	});
	
	
	
	
	
	
	

	function activateRegions() {
		var regions = $('.active');
		regions.each( function(index) { displaySlidesOnRegion( [], 0, $(this).attr('id') ) });
		regionsActivated = true;
	}
	
	function buildSlidesOnRegion(regionName) {
		
		//If there's no cached content for this region return an empty array; the slideshow function will know what that means.
		if (!cachedContent.hasOwnProperty(regionName)) return [];
		var content = cachedContent[regionName];
		
		//Build the actual slides here
		slideArray = new Array();
		for (i in content) {
			
			switch(content[i]['type']) {
				//Oh god why am I doing this I hate switch/case statements where are the braces it's like python or something
				case 'schedule':
					slideArray = slideArray.concat( scheduleToSlidesOnRegion(content[i]['content'], regionName) );
					break;
				case 'image':
					slideArray.push( buildImgSlide(content[i]['content']) );
					break;
				case 'forecast':
					//hell yeah it's THING DO TIME
					console.log(content[i]);
					slideArray = slideArray.concat( buildWeatherSlides(content[i]['content']) );
			}
			
		}
		

		
		return slideArray;
	}
	
	function displaySlidesOnRegion(slideArray, slideIndex, regionName) {
		//If we just finished a full cycle,
		if (slideIndex >= slideArray.length) {
			//Normally, just loop back to 0
			slideIndex -= slideArray.length;
			//If the cache has updated since we last rebuilt this region's slides, rebuild from the cache
			//TODO: do the check
			slideArray = buildSlidesOnRegion(regionName);
		}
		
		//Tidy up the region and remove old slides (this is for slides we told to hide on previous runs of this function but couldn't delete because they were in the process of animated-hiding)
		$('#'+regionName).find(':hidden').remove();
		
		//Grab the old slide to be hidden
		var slide_old = $('#'+regionName+' .slide');
		
		//Slot the new slide in and set it hidden
		var slide_new = $(slideArray[slideIndex]);
		$('#'+regionName).append(slide_new);
		slide_new.hide();
		
		//FX show the new slide and hide the old one
		slide_old.hide( transitionEffect, { direction: "left", easing: 'easeInOutQuint' }, 1500);
		slide_new.show( transitionEffect, { direction: "right", easing: 'easeInOutQuint' }, 1500);
		
		//Trigger the next slide to display after the pageDelay has elapsed.
		setTimeout(displaySlidesOnRegion, pageDelay*1000, slideArray, slideIndex+1, regionName);
	}
	
	
	
	
	
	
	function requestContent() {
		//Precondition: none
		//Postcondition: Queried fresh content with receiveContent as the callback
		var data = player;
		$.post('content.php', data, receiveContent);
	}
	function receiveContent(data) {
		//Precondition: none
		//Postcondition: Global var 'cachedContent' is populated with received data. requestContent() is scheduled to run in refreshDelay seconds. If the active regions hadn't been activated already they are now.
		try {
			data = $.parseJSON(data);
		} catch (e) {
			errorHandler(e);
			data = {};
		}
		
		cachedContent = data;
		console.log('Updated the cache.');
		console.log(cachedContent);
		
		if (!regionsActivated) activateRegions();
		
		setTimeout(requestContent, refreshDelay*1000);
	}
	
	
	
	
	
	
	
	
	
	


	function buildImgSlide(content) {
		var slide_new = $('<div class="slide"></div>');
		var src = '../' + content;
		src = "url('" + src + " ')";
		var img = $('<div class="fitimg" style="background-image:' + src + ';"></div>');
		slide_new.append(img);
		//return slide_new;
		return slide_new.prop('outerHTML');
	}
	
	function scheduleToSlidesOnRegion(schedule, regionName) {
		var slides = new Array();
		schedule = schedule.slice(0);
		while (schedule.length > 0) {
			slides.push( buildScheduleSlidesOnRegion(schedule, regionName) );
		}
		return slides;
	}
	
	function buildScheduleSlidesOnRegion(rows, regionName) {
		var slide_new = $('<div class="slide"></div>');
		var table = $('<table></table>');
		slide_new.append(table);
		$('#'+regionName).append(slide_new);
		//console.log(rows);
		while (row = rows.shift()) {
			var trow = $('<tr></tr>');
			trow.append('<td>'+ row['code'] +'</td>');
			trow.append('<td>'+ row['name'] +'</td>');
			trow.append('<td>'+ row['instructor'] +'</td>');
			trow.append('<td>'+ row['room'] +'</td>');
			trow.append('<td>'+ row['start'] +'</td>');
			table.append(trow);
			if ( trow.position().top+trow.height() > $('#'+regionName).height() ) {
				//Hide the tr, put the row back in rows, and break the loop.
				trow.hide();
				rows.unshift(row);
				break;
			}
		}
		var ret = slide_new.prop('outerHTML');
		slide_new.remove();
		return ret;
	}
	
	
	
	
	function buildWeatherSlides(weather) {
		/*
		Super quick notes on what we want and how weather.gov packages it their forecasts
		['data']['iconLink'][i]			Icon image full url
		['data']['text'][i]				Long text description of weather ("A chance of showers, mainly between 1pm and 4pm.  Mostly cloudy, with a high near 52. Southwest wind around 14 mph, with gusts as high as 30 mph.  Chance of precipitation is 30%.")
		['data']['temperature'][i]		Temperature as an int
		['data']['weather'][i]			Headline weather ("Chance Showers")
		['time']['startPeriodName'][i]	Headline time ("This Afternoon")
		['time']['tempLabel'][i]		High or Low for corresponding temperature
		*/
		var panels = new Array();
		var length = weather['data']['weather'].length;
		for (var i=0; i<length; i++) {
			var panel = $('<div class="weather-panel"></div>');
			panel.append( $('<div class="weather-header">'+weather['time']['startPeriodName'][i]+'</div>') );
			//panel.append( $('<div class="weather-column-1"><img src="'+weather['data']['iconLink'][i]+'" alt="" /></div>') );
			panel.append( $('<img class="weather-icon" src="'+weather['data']['iconLink'][i]+'" alt="" />') );
			panel.append( $('<p class="weather-temp">'+weather['time']['tempLabel'][i]+' '+weather['data']['temperature'][i]+'F</p>') );
			panel.append( $('<p class="weather-brief">'+weather['data']['weather'][i]+'</p>') );
			panels.push(panel);
		}
		//return panels;
		
		var slides = new Array();
		while (panels.length > 0) {
			var slide = $('<div class="slide"></div>');
			for (var i=0; i<3; i++) { //TODO: make this not hardcoded
				if (panel = panels.shift()) slide.append(panel);
			}
			slides.push(slide);
		}
		return slides;
		
		
	}
	
	
	
	
	
	
	function errorHandler(error) {
		console.log('An error has occurred, resetting the sign.');
		console.log(error);
		//TODO: Log that a reset error occurred in the db or something.
		location.reload();
	}

	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	</script>
	
</head>

<body>
	<div id="sign" style="<?php echo $player['player_css']; ?>">
		<!--TODO: Move all the structure and CSS into Templates stored in the DB-->
		<div id="inner">
			<div id="primary" class="region active row-1"></div>
			<div id="sidebar" class="region active row-1"></div>
			<div class="region row-spacer"></div>
			<div id="line" class="region row-1point5"></div>
			<div class="region row-spacer"></div>
			<div id="logo" class="region row-2"></div>
			<div id="ticker" class="region row-2"></div>
			<div id="clock" class="region row-2"></div>
		</div>
	</div>
</body>
	
</html>