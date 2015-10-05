<?php

define('MYSQL_HOST',	'localhost');
define('MYSQL_USER',	'signage_user');
define('MYSQL_PW',		'5ydEabntqsyhTz2e');
define('MYSQL_DB',		'signage');


dispatcher();

function dispatcher() {
	$action = 'none';
	if (isset($_POST['action'])) $action = $_POST['action'];
	
	if ($action == 'insert') {
		$result = post_insertHandler();
	}
	
	$html = buildDocumentHead() . '
	<div style="width: 600px; margin: 16px auto; background : #fff; padding: 24px;">';
	if (isset($result)) {
		if ($result) {
			$html .= '<p class="notice" style="color:#f00">Your comment has been received; thank you for your feedback.</p>';
		} else {
			$html .= '<p class="notice" style="color:#f00">An error occurred while recording your comment; please try again. If the problem persists, please contact the IT department directly.</p>';
		}
	}
	$html .= '
		<h1>About the alpha version</h1>
		<p>This system is currently in active development. The interface is not final, and there may be occasional software bugs or missing core features. We thank you in advance for your understanding.</p>
		<h2>Features currently in development</h2>
		<ul>
			<li>Ability to edit existing content and durations</li>
			<li>More polished and user-friendly UI
				<ul>
					<li>Modal scheduling dialogs</li>
					<li>Consistent professional theming</li>
				</ul>
			</li>
		</ul>
		<h2>Feedback</h2>
		<p>If you would like to report a bug or request a feature not currently present on the planned features list, you may do so using the form below.</p>
		<form id="form_feedback" action="index.php" method="post">
			<input type="hidden" name="action" value="insert" />
			<textarea style="max-width: 100%; width: 100%; height: 150px;" name="comment" form="form_feedback">Type your comment here</textarea>
			<p class="center"><input type="submit" value="Submit Feedback" /></p>
		</form>
	</div>';
	
	
	print ($html);
}




function post_insertHandler() {
	if (!isset($_POST['comment'])) return false;
	if ($_POST['comment'] == 'Type your comment here') return false;
	
	$auth = account();
	
	$what = array();
	$what['feedback_comment'] = $_POST['comment'];
	$what['user_upn'] = $auth['upn'];
	
	//print_r($what);
	
	$dbh = db_connect();
	$result = db_insertFeedback($dbh, $what);
	
	if ($result == -1) return false;
	return true;
}




function db_connect() {
	try {
		@$dbh = new PDO('mysql:host='.MYSQL_HOST.';dbname='.MYSQL_DB, MYSQL_USER, MYSQL_PW);
	} catch (PDOException $e) {
		die ('DB Error');
	}
	return $dbh;
}

function db_insertFeedback($dbh, $what) {
	$stmt = $dbh->prepare("
		INSERT INTO `signage`.`feedback`
		(`feedback_id`, `user_upn`, `feedback_comment`)
		VALUES
		(NULL, :user_upn, :feedback_comment);
	");
	
	$stmt->bindValue(':user_upn',			$what['user_upn']);
	$stmt->bindValue(':feedback_comment',	$what['feedback_comment']);
	
	if ($stmt->execute()) {
		$id = $dbh->lastInsertId();
		return $id;
	}
	return -1;
}


function account() {
	session_start();
	
	$auth = array();
	if (isset($_SESSION['upn'])) {
		$auth['upn'] = $_SESSION['upn'];
		$auth['status'] = true;
	} else {
		$auth['upn'] = null;
		$auth['status'] = false;
	}
	return $auth;
}

function buildDocumentHead() {
	$html = '<!doctype html>
<html>
<head>
	<meta charset="utf-8" />
	
	<title>CC Digital Signage Manager</title>
	<link href="../style.css" rel="stylesheet" type="text/css" />
	
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



?>