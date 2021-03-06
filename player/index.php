<?php
	require_once('../include/config.php');
	require_once('../include/db.php');
	
	
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
	
	
	$dbh = db_connect();
	$players = db_fetchPlayers($dbh, $where);
	$player = $players[0];
	//print_r($player);
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
	var player_shortname = '<?php echo $player['player_shortname']; ?>';
	var player_id = <?php echo $player['player_id'] ?>;
	
	
	//interval in seconds between page changes
	var pageDelay = 10;
	var transitionEffect = 'slide';// 'fade';
	
	//Initialise
	$(function() {
		requestAstraSchedule();

	});
	
	

	
	
	function requestAstraSchedule() {
		var data = {'site' : player['astra_guid']};
		$.get('../../astra/json.php', data, receiveAstraSchedule);
	}
	function receiveAstraSchedule(data) {
		try {
			data = $.parseJSON(data);
		} catch (e) {
			errorHandler(e);
		}
		
		if (Array.isArray(data) && data.length > 0) {
			displayContent(data, 'rows');
		} else {
			cleanup();
			requestDurations();
		}
	}
	
	
	
	function requestDurations() {
		var data = {
			'action'	:	'fetch',
			'fetch'		:	{
				'what'	:	'durations',
				'where'	:	{
					'player_id'	:	player['player_id'],
					'range'		:	'current'
				}
			}
		};
		$.post('../index.php', data, receiveDurations);
	}
	function receiveDurations(data) {
		try {
			data = $.parseJSON(data);
		} catch (e) {
			errorHandler(e);
		}
		
		if (Array.isArray(data['json']) && data['json'].length > 0) {
			displayContent(data['json'], 'durations');
		} else {
			cleanup();
			requestAstraSchedule();
		}

	}
	
	
	
	function cleanup() {
		$('#schedule').find(':hidden').remove();
		$('#schedule .slide').hide( transitionEffect, { direction: "left", easing: 'easeInOutQuint' }, 1500);
	}
	
	
	
	function displayContent(content, type) {
		//To whoever maintains this after me; I am sorry for this awful spaghetti mess

		//Keep the console up to date with what we try to run this function on in case it messes up so debugging is easier.
		console.log({'content' : content, 'type' : type});
		
		//clean up any hidden slides left over from the last run
		$('#schedule').find(':hidden').remove();
		
		//Bookmark the old slide to be hidden
		var slide_old = $('#schedule .slide');

		//Create a new slide
		var slide_new;
		
		var empty = false;
		var delay = 1000*pageDelay;
		
		//Make sure content is present
		if (Array.isArray(content) && content.length > 0 ) {	//Safety in case either fetch returns empty
			//Yes it's string comparison. I was in a hurry okay?
			if (type == 'rows') slide_new = buildScheduleSlide(content);
			if (type == 'durations') slide_new = buildImgSlide(content);

			//If there is content of the same type remaining, schedule another call to display it after the slide interval. If not, schedule a request for updated content.
			if (content.length > 0) {
				setTimeout(displayContent,  delay, content, type);
			} else {
				//Empty-at-the-end case, use the standard delay before requesting more content
				empty = true;
			}
		} else {
			//Empty-from-the-start case, request the next type of content immediately
			empty = true;
			delay = 1;
			slide_new = $('<div class="slide"></div>');
		}
		
		if (empty == true) {
			if (type == 'durations') setTimeout(requestAstraSchedule, delay);
			if (type == 'rows') setTimeout(requestDurations, delay);
		}
		
		//Animate slide transition
		slide_old.hide( transitionEffect, { direction: "left", easing: 'easeInOutQuint' }, 1500);
		slide_new.hide();
		slide_new.show( transitionEffect, { direction: "right", easing: 'easeInOutQuint' }, 1500);
		
	}
	
	function buildScheduleSlide(rows) {
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
		return slide_new;
	}
	
	function buildImgSlide(slides) {
		var content = slides.shift();
		var slide_new = $('<div class="slide"></div>');
		$('#schedule').append(slide_new);
		var src = '../' + content['content_src'];
		src = "url('" + src + " ')";
		var img = $('<div class="fitimg" style="background-image:' + src + ';"></div>');
		slide_new.append(img);
		return slide_new;
	}
	
	
	
	
	//	Error handling
	//==========================================================

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
		<div id="inner">
			<div id="schedule">
			</div>
		</div>
	</div>
</body>
	
</html>























