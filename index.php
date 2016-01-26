<?php

require_once('include/config.php');
require_once('include/db.php');

dispatcher();


function dispatcher() {
	//Dispatch requested functionality
	//If user is not logged in, dump to the login prompt. Otherwise deliver request
	//Allowed requests:
		//action = upload
			//non-AJAX file upload request. Delivers index when complete.
		//action = fetch
			//AJAX fetch request. Details stored in $_POST['fetch'].
		//action = insert
			//AJAX inster request. Details stored in $_POST['insert'].
		//action = update
			//TODO
			//AJAX update request. Details stored in $_POST['update'].
		//No postdata
			//Index request. Deliver the index normally
		//Anything else
			//Malformed or tampered request. Deliver the index with a notice.
	
	
	//TODO: Revise player fetch to either auto-authenticate or fetch from a different (unified) file, since un-logged-in players get a login page when they ask for JSON with this version
	
	
	$auth = account_dispatcher();
	
	if ($auth['status'] == false) {
		print buildLoginPage();
		die();
	}
	
	if ($auth['user']['user_role'] == 0) {
		print buildBumpbackPage($auth);	//TODO: if bumpback stays it needs a logout button
		die();
	}
	
	//print_r_html($auth);
	
	$action = '';
	if (isset($_POST['action'])) $action = $_POST['action'];
	
	if ($action == 'upload') { //upload requested, do the upload and send a fresh index, I'm not even going to /think/ about doing this part over AJAX until the rest is done, that's just asking for a headache
		post_uploadHandler($auth);	//TODO: add back result reporting for uploader
	}
	
	if ($action == 'fetch') {
		ajax_fetchHandler($auth);
		die();
	}
	
	if ($action == 'insert') {
		ajax_insertHandler($auth);
	}
	
	if ($action == 'delete') {
		ajax_deleteHandler($auth);
	}
	
	print buildIndex($auth);
}


//**
//** Non-index request handlers
//*************************************************************

function ajax_fetchHandler($auth) {
	if (!isset($_POST['fetch'])) return;
	//$request = json_decode($_POST['fetch']);
	$request = $_POST['fetch'];
	$response;
	
	if ($request['what'] == 'durations') {
		$where = array();
		if (!isset($request['where'])) json_die_error("Your request was missing information required to respond to it");
		$where = $request['where'];
		if (isset($where['user_id'])) unset($where['user_id']);	//db call accepts it, user shouldn't be sending it.
		
		//Get user's visibility
		$visible = account_getDurationVisibility($auth, $where);
		//If no permissions, bumpback
		if ($visible == PRIV_NONE) json_die_error("You do not have permission to view that!");
		//If only ours, add the qualifier to $where
		if ($visible == PRIV_MINE) $where['user_id'] = $auth['user']['user_id'];
		
		//Fetch json version
		$dbh = db_connect();
		$response['json'] = db_fetchDurations($dbh, $where);
		
		//Also spit out an html version for prototype (TODO, make the manager build the html client side)
		$html = buildScheduleTable($auth, $where);
		$response['html'] = $html;
		$response['error'] = false;
		
		$response['where'] = $where;
		
		$json = json_encode($response);
		die($json);
	}
	

	
}

function ajax_insertHandler($auth) {
	if (!isset($_POST['insert'])) return;
	$request = $_POST['insert'];
	$response;
	
	if ($request['what'] == 'duration') {
		$values = $request['values'];
		
		//TODO: More thorough validation and sanitisation(make sure id is valid, etc)
		if ( !isset($values['content_id'])	|| $values['content_id'] < 1 )	json_die_error('Bad or missing content id');		//TODO: confirm content is real and visible to user
		if ( !isset($values['player_id'])	|| $values['player_id'] < 1 )	json_die_error('Bad or missing player id');			//TODO: confirm player is real
		if ( !isset($values['date_start'])	|| $values['date_start'] == -1	||	!isValidDate($values['date_start']) ) json_die_error('Bad or missing date');
		if ( !isset($values['date_end']) 	|| $values['date_end'] == -1	||	!isValidDate($values['date_end']) ) json_die_error('Bad or missing date');		//TODO: enforce positive datespan
		if ( !isset($values['duration_name']) || empty($values['duration_name']) ) {
			$values['duration_name'] = 'Untitled duration';
		} else {
			$values['duration_name'] = sanitise_string($values['duration_name']);
		}
		$values['user_id'] = $auth['user']['user_id'];
		
		//TODO: also check that user can see that content?
		
		if (!APP_CAN_WRITE_INVISIBLE && account_getDurationVisibility($auth, $values) < PRIV_MINE) json_die_error('You need permission to view content in order to schedule content on this player!');
		
		if (account_getDurationWriteability($auth, $values) < PRIV_MINE) json_die_error('You do not have permission to schedule content on this player!');
		
		$dbh = db_connect();
		$result = db_insertDuration($dbh, $values);
		$response['result'] = $result;
		$response['error'] = false;
		if ($result == -1) $response['error'] = true;
	}
	
	$json = json_encode($response);
	die($json);
}

function post_uploadHandler($auth) {
	//precondition: received action=upload
	if (!isset($_FILES['content_file'])) return 'Error: No file provided';
	$upload = $_FILES['content_file'];
	
	//Determine where to save the upload
	$timestamp = new DateTime('now');
	$timestamp = $timestamp->format('Y-m-d-Gis-');
	$target = APP_IMAGE_DIR . $timestamp . basename(  sanitise_string($upload['name'])  );
	
	//Verify that the file has an image extension
	$type = strtolower( pathinfo($target,PATHINFO_EXTENSION) );
	if ($type != 'png' && $type != 'jpg' && $type != 'jpeg') return 'Error: Uploaded file is not an allowed image type (png or jpeg)';
	
	//Verify that file contents are an image
	$info = getimagesize($upload['tmp_name']);
		//Will be null if it's not an image
		//Otherwise it looks something like this: Array ( [0] => 976 [1] => 549 [2] => 3 [3] => width="976" height="549" [bits] => 8 [mime] => image/png ) 
	if ($info === false) return 'Error: Uploaded file is not an image';
	
	//Ensure there's no collision (very unlikely with timestamper)
	if (file_exists($target)) return 'Error: A file with that name already exists on the server';
	
	//Reject giant filesize	(size is in bytes, so enforce a generous 5mb limit)
	if ($upload['size'] > 1024*1024*5) return 'Error: Filesize exceeds upload limit';
	
	//We got this far, attempt to save the upload
	//NOTE TO ANY FUTURE MAINTAINERS: If a user comes to you with this error, you probably forgot to give your web server permission to write to the upload directory
	if ( !move_uploaded_file($upload['tmp_name'], $target) ) return 'Error: A server error has occurred, please try your upload again. If the problem persists, contact the IT department.';
	
	//Grab the name
	$name = $upload['name'];
	if (isset($_POST['content_name']) && !empty($_POST['content_name']) ) $name = sanitise_string($_POST['content_name']);
	
	//Now create an entry for this content in the DB
	$content = array();
	$content['user_id'] = $auth['user']['user_id']; //TODO
	$content['name'] = $name;
	$content['src'] = $target;
	$dbh = db_connect();
	$id = db_insertContent($dbh, $content);
	
	if ($id == -1) return 'Error: Could not create an entry for your content in the database';
	
	
	return 'Success! Your content has been uploaded';
}

function ajax_deleteHandler($auth) {
	//TODO: ensure fails on no auth
	if ( !isset($auth['status']) || $auth['status'] != 1) json_die_error('Cannot perform requested operation; you are not logged in');
	
	if (!isset($_POST['delete'])) json_die_error('Missing or invalid request');
	$request = $_POST['delete'];
	$response = array();
	$response['request'] = $request;
	
	if ($request['what'] == 'duration') {
		//Input validation
		if (!isset($request['where'])) json_die_error('Missing or invalid request');
		$where = $request['where'];
		if (!isset($where['duration_id'])) json_die_error('Missing or invalid request');
		if (!is_numeric($where['duration_id'])) json_die_error('Missing or invalid request');

		//Verify user has permission to perform requested delete
		$dbh = db_connect();
		$duration = db_fetchDurationInfo($dbh, $where);
		$read = account_getDurationVisibility($auth, $duration);
		$write = account_getDurationWriteability($auth, $duration);
		if (!APP_CAN_WRITE_INVISIBLE && $read < $write) $write = $read;
		$allowed = false;
		if (	(account_isSuperuser($auth) )
			||	($write==PRIV_ALL)
			||	($write==PRIV_MINE && $duration['user_id'] == $auth['user']['user_id'])
			) $allowed = true;
		if ($allowed == false) json_die_error('You do not have permission to delete that!');
		
		//Run the deletion
		$response['deleted_durations_rows'] = db_deleteDuration($dbh, $where);
		$response['error'] = true;
		if ($response['deleted_durations_rows'] == 1) $response['error'] = false;
		$json = json_encode($response);
		die($json);
		
	}
	
	if ($request['what'] == 'content') {
		//Input validation
		if (!isset($request['where'])) json_die_error('Missing or invalid request');
		$where = $request['where'];
		if (!isset($where['content_id'])) json_die_error('Missing or invalid request');
		if (!is_numeric($where['content_id'])) json_die_error('Missing or invalid request');
		$where['duration_id'] = '%';	//Allow it to select all linked durations
		
		if (!account_canEditContent($auth, $where)) json_die_error('You do not have permission to delete that!');
		
		$dbh = db_connect();
		$response['deleted_content_rows'] = db_deleteContent($dbh, $where);
		$response['deleted_durations_rows'] = db_deleteDuration($dbh, $where);
		$response['error'] = true;
		if ($response['deleted_content_rows'] == 1) $response['error'] = false;
		$response['deleted_content_id'] = $where['content_id'];
		$json = json_encode($response);
		die($json);
	}
}




//**
//** Login index request handler
//*************************************************************

function buildLoginPage() {
	$html = buildDocumentHead();
	$html .= '
	<div id="pane_login">
		<form action="?" method="post">
			<h2 class="center">Sign in with your Cambridge College credentials to access the Digital Sign Management Console</h2>
			<br/>
			<p class="center"><input type="text" name="auth_upn" placeholder="Your Cambridge College E-Mail" /></p><br/>
			<p class="center"><input type="password" name="auth_pw" placeholder="Password" /></p><br/>
			<p class="center"><input type="submit" value="Log in" /></p>
			<input type="hidden" name="action" value="login" />
			<p>Remember, only enter your Cambridge College credentials on websites whose addresses begin with <strong>cambridgecollege.edu</strong>, or on managed service provider sites like outlook.com</p>
		</form>
	</div>
</body>
</html>';
	return $html;
	//TODO: Change this up to use ajax for logging in and out
}

function buildBumpbackPage($auth) {
	$html = buildDocumentHead();
	$html .= '<div id="pane_topbar">'
	. 'CC Signage Management Tool <strong>Alpha</strong> <a id="info_alpha">[more info]</a> | '
	. 'Logged in as ' . $auth['upn'] . ' | <a id="logout">Logout</a>'
	. '</div>';
	$html .= '<div id="pane_login" class="center"><h1>Welcome to the interim Cambridge College Digital Signage Management System.</h1><p>Your account has not yet been authorised to post content to the digital signs. Please contact IT for assistance, or create a <a href="https://helpdesk.cambridgecollege.edu" target="_blank">helpdesk ticket</a>.</p></div>';
	return $html;
}

//**
//** Index request handler
//*************************************************************

function buildIndex($auth) {
	
	//TODO: yoink that debug when you're done with it
	//print_r_html($auth);
	
	$html = buildDocumentHead();
	
	$html .= '<div id="pane_topbar">'
	. 'CC Signage Management Tool <strong>Alpha</strong> <a id="info_alpha">[more info]</a> | '
	. 'Logged in as ' . $auth['upn'] . ' | <a id="logout">Logout</a>'
	. '</div>';
	
	$html .= buildLibraryPane($auth);
	
	$html .= buildScheduleViewPane($auth);

	$html .= '</body>
</html>';

	return $html;
}

function buildDocumentHead() {
	$html = '<!doctype html>
<html>
<head>
	<meta charset="utf-8" />
	
	<title>CC Digital Signage Manager</title>
	<link href="style.css" rel="stylesheet" type="text/css" />
	
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
	<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
	
	<script src="manager.js"></script>
	
</head>
<body>
	<div id="notice"></div>
';
	return $html;
}


//**
//** #pane_schedule_view stuff
//*************************************************************

function buildScheduleViewPane($auth) {
	$html  = '<div id="pane_schedule_view">';
		$html .= '<h2>Schedule Viewer' . buildPlayerSelector($auth) . '</h2>';
		$html .= '<div id="schedule_content" class="scroll_pane"></div>';
		$html .= '<br/>';
		$html .= buildScheduleInsertForm();
	$html .= '</div>';
	return $html;
}

function buildPlayerSelector($auth) {
	$dbh = db_connect();
	$players = db_restricted_fetchPlayers($dbh, $auth);
	$html = '<select id="select_player">';
	foreach ($players as $player) $html .= '<option value="' . $player['player_id'] . '">' . $player['player_name'] . '</option>';
	$html .= '</select>';
	return $html;
}

function buildScheduleTable($auth, $where) {
	
	//Fetch the content
	$dbh = db_connect();
	$durations = db_fetchDurations($dbh, $where);
	
	//Editing rights
	$write = account_getDurationWriteability($auth, $where);
	
	//Format it
	$html = '<table class="scroll_content">';

	foreach ($durations as $duration) {
		$html .= '<tr ' . assoc_to_data($duration) . '>';
			$html .= '<td>' . $duration['duration_name'] . '<br/>' . $duration['content_name'] . '<br/>' . friendlyDate($duration['date_start']) . ' - ' . friendlyDate($duration['date_end']) . '</td>';
			$html .= '<td>';
			if (	($write == PRIV_ALL) ||
					($write == PRIV_MINE && $duration['user_id'] == $auth['user']['user_id']) ) $html .= '<button name="duration_delete">Delete</button>';
			$html .= '</td>';
			$html .= '<td><img src="' . $duration['content_src'] . '" alt="' . $duration['content_name'] . '" /></td>';
		$html .= '</tr>';
	}
	
	$html .= '</table>';
	
	return $html;
}

function buildScheduleInsertForm() {
	$html = '
	<div id="form_scheduler" class="form_ajax grid">
		<h3>Schedule content</h3>
		
		<input id="scheduler_content_id" type="hidden" value="-1" />
		<input id="datestamp_start" type="hidden" value="-1"/>
		<input id="datestamp_end"   type="hidden" value="-1"/>

		<div class="thumbnail_remainder">
			<p>
				<input id="scheduler_duration_name" type="text" placeholder="Name on schedule" style="width:100%;"/><br/>
				<input id="date_start" type="text" placeholder="Starting" /><input id="date_end" type="text" placeholder="Ending" />
			</p>
			<p class="center"><input id="schedule_submit" type="submit" value="Schedule"/></p>
		</div>
		<div class="thumbnail_reserved">
			<div id="scheduler_preview_container">
				<img id="scheduler_preview_content" />
			</div>
		</div>
	</div>';
	return $html;
}




//**
//** #pane_library stuff
//*************************************************************

function buildLibraryPane($auth) {
	$dbh = db_connect();
	
	$where['user_id'] = $auth['user']['user_id'];
	if (account_canViewAllContent($auth) ) $where['user_id'] = '%';
	
	$content = db_fetchContent($dbh, $where);
	
	
	$html = '<div id="pane_library">';
		if (account_canViewAllContent($auth) ) $html .= '<button id="library_toggle_filter" style="float:right; font-size:0.75em;">Toggle</button>'; //TODO: js for this, needs id
		$html .= '<h2>';
			$html .= 'Content Library';
			
		$html .= '</h2>';
		$html .= '<div id="library_content" class="scroll_pane">';
			$html .= '<table class="scroll_content">';
				foreach ($content as $row) {
					$mine = ($auth['user']['user_id'] == $row['user_id']);
					$class='content-others';
					if ($mine) $class='content-mine';
					$html .= '<tr class="' . $class . '"' . assoc_to_data($row) . '>';
						$html .= '<td>';
							$html .= $row['content_name'];
							if ($mine) {
								$html .= '<br/><span class="metadata">Uploaded by me</span>';
							} else {
								$html .= '<br/><span class="metadata">Uploaded by ' . $row['ldap_upn'] . '</span>';
							}
						$html .= '</td>';
						$html .= '<td>';
							$html .= '<button name="content_select">Schedule</button>';
							if ($mine || account_canEditAllContent($auth)) $html .= '<button name="content_delete">Delete</button>';
						$html .= '</td>';
						$html .= '<td><img src="' . $row['content_src'] . '" alt="' . $row['content_name'] . '" /></td>';
					$html .= '</tr>';
				}
			$html .= '</table>';
		$html .= '</div>';
		$html .= '<br/>';
		$html .= '<div id="library_upload">';
	
			$html .= '<form action="?" method="post" enctype="multipart/form-data">';
				$html .= '<h3>Upload new content</h3>';
				$html .= '<p>Content must be uploaded in an image format (png, jpeg, etc). For best results, upload with a 16:10 aspect ratio and a minimum size of 1000x560.</p>';
				$html .= '<input type="input" name="content_name" placeholder="Name"/><input type="file"  name="content_file" accept="image/*" /><br/>';
				$html .= '<input type="hidden" name="action" value="upload" />';
				$html .= '<br/>';
				$html .= '<p class="center"><input type="submit" value="Upload"></p>';
			$html .= '</form>';
		$html .= '</div>';
	$html .= '</div>';
	
	return $html;
}

//**
//** User login/out functions
//*************************************************************

function account_dispatcher() {
	//Dispatcher for login status
	
	//Start the session
	session_start();
	
	//If a log-out was requested, purge the session data and send a confirmation
	if (isset($_POST['action']) && $_POST['action'] == 'logout') {
		unset($_SESSION['upn']);
		//header("Location:index.php");
		$response['error'] = false;
		$response['refresh'] = true;
		$json = json_encode($response);
		die($json);
	}
	
	//If a log-in was requested, attempt to authenticate. If successful, save to session data, then die with a reload header
	if (isset($_POST['action']) && $_POST['action'] == 'login') {
		$upn = 'na';
		$pw = 'na';
		if (isset($_POST['auth_upn']) && isset($_POST['auth_pw'])) {
			$upn = $_POST['auth_upn'];
			$pw = $_POST['auth_pw'];
		}
		$valid = ad_authUser($upn, $pw);
		if ($valid) $_SESSION['upn'] = $upn;
	}

	//Pull info from the session data. We now know who is logged in.
	$auth = array();
	if (isset($_SESSION['upn'])) {
		$auth['upn'] = $_SESSION['upn'];
		$auth['status'] = true;
	} else {
		$auth['upn'] = null;
		$auth['status'] = false;
	}
	
	//*************
	//BEGIN UNIT TESTING BYPASS
	//Uncomment the next two lines to force logging in as the user 'testing'
	//$auth['upn'] = 'testing';
	//$auth['status'] = true;
	//*************
	
	//Check with the users table to get this user's editing permissions
	$where['ldap_upn'] = strtolower($auth['upn']);
	$dbh = db_connect();
	$userInfo = db_fetchUserInfo($dbh, $where);
	if (count($userInfo) == 0) {
		$what['ldap_upn'] = strtolower($auth['upn']);
		$what['user_role'] = 0;
		db_insertUser($dbh, $what);
		$userInfo = db_fetchUserInfo($dbh, $where);
		//TODO: Email the administrator to inform them that a new user has logged in and is awaiting permissions
	}
	$auth['user'] = $userInfo;
	
	$auth['permissions'] = db_fetchUserPermissions($dbh, $auth['user']);
	
	//Bubble global permissions down to player permissions
	if ( isset($auth['permissions']['players']) ) {
		foreach ($auth['permissions']['players'] as &$player) {
			foreach ($auth['permissions']['global'] as $key => $value) {
				if (isset($player[$key]) && $player[$key] < $value) $player[$key] = $value;
			}
		}
	}
	
	return $auth;
}


function account_isSuperuser($auth) {
	if ($auth['permissions']['global']['perm_superuser'] == 1) return true;
	return false;
}	

function account_canViewAllContent($auth) {
	if ( account_isSuperuser($auth) ) return true;
	if ($auth['permissions']['global']['perm_content'] >= PRIV_CAN_VIEW) return true;
	return false;
}

function account_canEditAllContent($auth) {
	if ( account_isSuperuser($auth) ) return true;
	if ($auth['permissions']['global']['perm_content'] >= PRIV_CAN_EDIT) return true;
	return false;
}

function account_canEditContent($auth, $where) {
	//$where should describe the content requested
	
	//1) Do they just have global edit rights? If so true 
	if ( account_canEditAllContent($auth) ) return true;
	
	//2) [query required] If not they must own the content
	$dbh = db_connect();
	$rows = db_fetchContent($dbh, $where);
	foreach ($rows as $row) {
		//If /any/ returned rows aren't owned, must return false
		if ($row['user_id'] != $auth['user']['user_id']) return false;
	}
	
	//Either all matching rows are owned or no rows are affected; either way, return true
	return true;
}



function account_getDurationVisibility($auth, $where) {
	//IF superuser OR global_read==PRIV_ALL OR local_read==PRIV_ALL,		PRIV_ALL
	//ELSE IF global_read==PRIV_MINE OR local_read==PRIV_MINE, 				PRIV_MINE
	//ELSE not allowed to query this player, bumpback.						PRIV_NONE
	
	//ifs are exploded out instead of || joined for legibility
	
	//Superuser check
	if (account_isSuperuser($auth)) return PRIV_ALL;
	//Global read all check
	if ($auth['permissions']['global']['perm_player_read'] == PRIV_ALL) return PRIV_ALL;
	//Local read all check
	if (isset($auth['permissions']['players'][$where['player_id']]['perm_player_read']) && $auth['permissions']['players'][$where['player_id']]['perm_player_read'] == PRIV_ALL) return PRIV_ALL;
	
	//Global read own check
	if ($auth['permissions']['global']['perm_player_read'] == PRIV_MINE) return PRIV_MINE;
	//Local read own check
	if (isset($auth['permissions']['players'][$where['player_id']]['perm_player_read']) && $auth['permissions']['players'][$where['player_id']]['perm_player_read'] == PRIV_MINE) return PRIV_MINE;
	
	return PRIV_NONE;
}

function account_getDurationWriteability($auth, $where) {
	//Superuser check
	if (account_isSuperuser($auth)) return PRIV_ALL;
	//Global write all
	if ($auth['permissions']['global']['perm_player_write'] == PRIV_ALL) return PRIV_ALL;
	//Local write all
	if (isset($auth['permissions']['players'][$where['player_id']]['perm_player_write']) && $auth['permissions']['players'][$where['player_id']]['perm_player_write'] == PRIV_ALL) return PRIV_ALL;
	
	//Global write own check
	if ($auth['permissions']['global']['perm_player_write'] == PRIV_MINE) return PRIV_MINE;
	//Local write own check
	if (isset($auth['permissions']['players'][$where['player_id']]['perm_player_write']) && $auth['permissions']['players'][$where['player_id']]['perm_player_write'] == PRIV_MINE) return PRIV_MINE;
	
	return PRIV_NONE;
}




//Date Helpers

function isValidDate($date) {
	if (empty($date)) return false;
	try {
		$do = new DateTime($date);
	} catch (Exception $e) {
		//FLAGRANT ERROR, YES PROBABLO
		return false;
	}
	return true;
}

function friendlyDate($date) {
	//PHP_DATE_FRIENDLY
	$dt = new DateTime($date);
	$friendly = $dt->format(PHP_DATE_FRIENDLY);
	return $friendly;
}





//General use helper functions

function json_die_error($error) {
	//Convenience function to quickly spit out a standardised error message to json
	$response['error'] = true;
	$response['error_message'] = $error;
	$json = json_encode($response);
	die($json);
}

function print_r_html($data) {
	print '<pre>';
	print_r($data);
	print '</pre>';
}

function assoc_to_data($array) {
	$data = '';
	$strip = array('"', "'", "\n", "\r");
	foreach ($array as $key => $value) {
		$key = str_replace($strip, '', $key);
		$value = str_replace($strip, '', $value);
		$data .= 'data-' . $key . '="' . $value . '" ';
	}
	return $data;
}


function sanitise_string($string) {
	$string = trim($string);
	$string = strip_tags($string);		//Strip tags first
	$string = htmlentities($string);	//Then escape for html
	$string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_ENCODE_HIGH);		//Then deal with anything else
	return $string;
}



?>