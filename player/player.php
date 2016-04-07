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
	
	//Page transition settings
	var pageDelay = 10;			//Seconds between pages
	var transitionEffect = 'slide';
	var refreshDelay = 60;		//Seconds between content refreshes;
	
	//Initialise
	$(function() {
		requestContent();
	});
	
	
	
	function requestContent() {
		var data = player;
		$.post('content.php', data, receiveContent);
	}
	function receiveContent(data) {
		try {
			data = $.parseJSON(data);
		} catch (e) {
			errorHandler(e);
		}
		//console.log(data);
		
		var slides = new Array();
		
		
		while (content = data.shift()) {
			if (content['type'] == 'schedule') {
				slides = slides.concat( scheduleToSlides(content['content']) );
			}
			if (content['type'] == 'image') {
				slides.push( buildImgSlide(content['content']) );
			}
		}
		
		console.log(slides);
		displaySlides(slides, 0);
	}
	
	
	
	function displaySlides(slides, i) {
		//End case
		if (i >= slides.length) {
			//If the timer is up, request new content, otherwise loop
			
			//displaySlides(slides, 0);
			
			requestContent();
			
			return;
		}
		
		//clean up any hidden slides left over from the last run
		$('#schedule').find(':hidden').remove();
		
		//Bookmark the old slide to be hidden
		var slide_old = $('#schedule .slide');
		
		var slide_new = $(slides[i]);
		$('#schedule').append(slide_new);
		slide_new.hide();
		
		
		slide_old.hide( transitionEffect, { direction: "left", easing: 'easeInOutQuint' }, 1500);
		slide_new.show( transitionEffect, { direction: "right", easing: 'easeInOutQuint' }, 1500);
		
		
		setTimeout(displaySlides, pageDelay*1000, slides, i+1);
		//displaySlides(slides, i+1);
	}
	
	
	
	
	
	
	
	function scheduleToSlides(schedule) {
		var slides = new Array();
		while (schedule.length > 0) {
			slides.push( buildScheduleSlides(schedule) );
		}
		return slides;
	}
	
	function buildScheduleSlides(rows) {
		var slide_new = $('<div class="slide"></div>');
		var table = $('<table></table>');
		slide_new.append(table);
		$('#schedule').append(slide_new);
		//console.log(rows);
		while (row = rows.shift()) {
			var trow = $('<tr></tr>');
			trow.append('<td>'+ row['code'] +'</td>');
			trow.append('<td>'+ row['name'] +'</td>');
			trow.append('<td>'+ row['instructor'] +'</td>');
			trow.append('<td>'+ row['room'] +'</td>');
			trow.append('<td>'+ row['start'] +'</td>');
			table.append(trow);
			if ( trow.position().top+trow.height() > $('#schedule').height() ) {
				//Hide the tr, put the row back in rows, and break the loop.
				trow.hide();
				rows.unshift(row);
				break;
			}
		}
		//slide_new.detach();
		//return slide_new;
		//return slide_new.prop('outerHTML');
		var ret = slide_new.prop('outerHTML');
		slide_new.remove();
		return ret;
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
			<div id="schedule" class="region row-1"></div>
			<div id="sidebar" class="region row-1"></div>
			<div class="region row-spacer"></div>
			<div id="line" class="region row-1point5"></div>
			<div class="region row-spacer"></div>
			<div id="logo" class="region row-2"></div>
		</div>
	</div>
</body>
	
</html>