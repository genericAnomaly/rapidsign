<?php
header('content-type:text/plain');

require_once('include/config.php');
require_once('include/db.php');


define('RESULT_PROGRAM_ERROR',		-1);
define('RESULT_ACTION_ERROR',		0);
define('RESULT_ACTION_FAILURE',		1);
define('RESULT_ACTION_SUCCESS',		2);
define('RESULT_CONTENTS_EMPTY',		3);
define('RESULT_CONTENTS_MINE',		4);
define('RESULT_CONTENTS_ALL',		5);

function readDef($def) {
	switch ($def) {
		case RESULT_PROGRAM_ERROR:	return 'RESULT_PROGRAM_ERROR';
		case RESULT_ACTION_ERROR:	return 'RESULT_ACTION_ERROR';
		case RESULT_ACTION_FAILURE:	return 'RESULT_ACTION_FAILURE';
		case RESULT_ACTION_SUCCESS:	return 'RESULT_ACTION_SUCCESS';
		case RESULT_CONTENTS_EMPTY: return 'RESULT_CONTENTS_EMPTY';
		case RESULT_CONTENTS_MINE:	return 'RESULT_CONTENTS_MINE';
		case RESULT_CONTENTS_ALL:	return 'RESULT_CONTENTS_ALL';
	}
}



//Constants
$constants['content_id'] = 38;
$constants['user_id_mine'] = 6;
$constants['user_id_other'] = 5;
$constants['player_id'] = 5;
$GLOBALS['constants'] = $constants;

//Initial parameters
$params[0]['superuser'] = 0;
$params[0]['global_player_read'] = 0;
$params[0]['global_player_write'] = 0;
$params[0]['local_player_read'] = 0;
$params[0]['local_player_write'] = 0;

//Final param values
$params[1]['superuser'] = 1;
$params[1]['global_player_read'] = 2;
$params[1]['global_player_write'] = 2;
$params[1]['local_player_read'] = 2;
$params[1]['local_player_write'] = 2;


echo "Use case testing for permissions schema\n";

echo "Connecting to database... ";
$dbh = db_connect();
echo "Connected.\n";

echo "\nTesting constants: ";
print_r($constants);

echo "\nTesting parameters: ";
print_r($params);


echo "\nRunning test cases\n";

subcaseLoop($params, 'superuser', 0, $dbh);






function subcaseLoop($params, $key, $depth, $dbh) {
	while ($params[0][$key] <= $params[1][$key]) {
		echo indent($depth) . "$key = " . $params[0][$key] . "\n";
		$nextKey = array_key_next($params[0], $key);
		if (isset($params[0][$nextKey])) {
			subcaseLoop($params, array_key_next($params[0], $key), $depth+1, $dbh);
		} else {
			runCases($params[0], $depth+1, $dbh);
		}
		$params[0][$key] ++;
	}
}

function array_key_next($array, $key) {
	$keys = array_keys($array);
	for ($i=0; $i < count($keys); $i++) {
		if ($keys[$i] == $key && isset($keys[$i+1]) ) return $keys[$i+1];
	}
	return -1;
}

function array_key_number($array, $key) {
	$keys = array_keys($array);
	for ($i=0; $i < count($keys); $i++) {
		if ($keys[$i] == $key) return $i;
	}
	return -1;
}

function indent($depth) {
	$t = "";
	for ($i=0; $i<$depth; $i++) $t .= "\t";
	return $t;
}






function runCases($params, $depth, $dbh) {
	//reset db
	$targets = db_resetTestPlayer($dbh);
	
	//Set permissions to be tested
	db_setPermissions($dbh, $params);
	
	
	//Fetch Player test
	$result = test_fetchPlayerList();
	$expectation = expectation_fetchPlayerList($params);
	echo indent($depth) . '- Playerlist fetch test - ' . "\t\t" . readDef($result['code']) . "\t" . '(expected ' . readDef($expectation) . ')';
	if ($expectation != $result['code']) echo "\t" . 'FAILED TEST';
	echo "\n";
	
	
	//Fetch Durations test
	$result = test_fetchDurations();
	$expectation = expectation_fetchDurations($params);
	echo indent($depth) . '- Duration fetch test - ' . "\t\t" . readDef($result['code']) . "\t" . '(expected ' . readDef($expectation) . ')';
	if ($expectation != $result['code']) echo "\t" . 'FAILED TEST';
	echo "\n";
	
	
	//Insert duration test
	$result = test_insertDuration($dbh);
	$expectation = expectation_writeDurationOwned($params);
	echo indent($depth) . '- Duration insertion test - ' . "\t\t" . readDef($result['code']) . "\t" . '(expected ' . readDef($expectation) . ')';
	if ($expectation != $result['code']) echo "\t" . 'FAILED TEST';
	echo "\n";
	
	//Delete owned duration test
	$expectation = expectation_writeDurationOwned($params);
	$result = test_deleteDuration($dbh, $targets['mine']);
	echo indent($depth) . '- Owned duration deletion test - ' . "\t" . readDef($result['code']) . "\t" . '(expected ' . readDef($expectation) . ')';
	if ($expectation != $result['code']) echo "\t" . 'FAILED TEST' . "\n";
	echo "\n";
	
	//Delete unowned duration test
	$expectation = expectation_writeDurationUnowned($params);
	$result = test_deleteDuration($dbh, $targets['other']);
	echo indent($depth) . '- Unowned duration deletion test - ' . "\t" . readDef($result['code']) . "\t" . '(expected ' . readDef($expectation) . ')';
	if ($expectation != $result['code']) echo "\t" . 'FAILED TEST';
	echo "\n";
	
	//Make sure the script doesn't time out.
	set_time_limit(30);
	//flush();
}






function expectation_fetchPlayerList($params) {
	if ($params['superuser'] == 1) return RESULT_CONTENTS_ALL;
	if ($params['global_player_read'] >= 1) return RESULT_CONTENTS_ALL;
	if ($params['local_player_read'] >= 1) return RESULT_CONTENTS_MINE;
	return RESULT_CONTENTS_EMPTY;	
}

function expectation_fetchDurations($params) {
	if ($params['superuser'] == 1) return RESULT_CONTENTS_ALL;
	if ($params['global_player_read'] == 2) return RESULT_CONTENTS_ALL;
	if ($params['local_player_read'] == 2) return RESULT_CONTENTS_ALL;
	if ($params['global_player_read'] == 1) return RESULT_CONTENTS_MINE;
	if ($params['local_player_read'] == 1) return RESULT_CONTENTS_MINE;
	return RESULT_ACTION_ERROR;
}

function expectation_writeDurationOwned($params) {
	if ($params['superuser'] == 1) return RESULT_ACTION_SUCCESS;
	if ( ($params['global_player_write'] >= 1 || $params['local_player_write'] >= 1) && ($params['global_player_read'] >= 1 || $params['local_player_read'] >= 1) ) return RESULT_ACTION_SUCCESS;
	return RESULT_ACTION_ERROR;
}

function expectation_writeDurationUnowned($params) {
	if ($params['superuser'] == 1) return RESULT_ACTION_SUCCESS;
	if ( ($params['global_player_write'] >= 2 || $params['local_player_write'] >= 2) && ($params['global_player_read'] >= 2 || $params['local_player_read'] >= 2) ) return RESULT_ACTION_SUCCESS;
	return RESULT_ACTION_ERROR;
}






function test_fetchPlayerList() {
	$url = 'http://localhost/signage/index.php';
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	$html = curl_exec($ch);
	curl_close($ch);
	
	//This is the worst
	$str = explode('<select id="select_player">', $html);
	$str = explode('</select>', $str[1]);
	$players = explode('</option><option', $str[0]);
	//print_r($players);
	
	$ret = array();
	
	$numPlayers = count($players);
	if ($numPlayers == 1) {
		if ( empty($players[0]) ) {
			$ret['code'] = RESULT_CONTENTS_EMPTY;
		} else {
			$ret['code'] = RESULT_CONTENTS_MINE;
		}
	} else {
		$ret['code'] = RESULT_CONTENTS_ALL;
	}
	return $ret;
	
}

function test_fetchDurations() {
	$url = 'http://localhost/signage/index.php';
	$ch = curl_init($url);
	
	$request = array(
		'action'	=>	'fetch',
		'fetch'		=>	array(
			'what'	=>	'durations',
			'where'	=>	array(
				'player_id'	=>	$GLOBALS['constants']['player_id']
			)
		)
	);
	
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request) );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	$json = curl_exec($ch);
	curl_close($ch);
	
	$ret['json'] = $json;
	$response = json_decode($json, true);
	
	if ($response == null) $ret['code'] = RESULT_PROGRAM_ERROR;
	if ($response['error'] == true) $ret['code'] = RESULT_ACTION_ERROR;
	
	if (isset($response['json'])) {
		$numDurations = count($response['json']);
		if ($numDurations == 0) $ret['code'] = RESULT_CONTENTS_EMPTY;
		if ($numDurations == 1) $ret['code'] = RESULT_CONTENTS_MINE;
		if ($numDurations > 1) $ret['code'] = RESULT_CONTENTS_ALL;
	}
	
	return $ret;
}

function test_insertDuration($dbh) {
	$name = uniqid();
	
	$url = 'http://localhost/signage/index.php';
	$ch = curl_init($url);
	
	$request = array(
		'action'	=>	'insert',
		'insert'		=>	array(
			'what'	=>	'duration',
			'values'	=>	array(
				'content_id'	=>	$GLOBALS['constants']['content_id'],
				'date_start'	=>	'2016-01-01',
				'date_end'	=>	'2016-12-31',
				'player_id'		=>	$GLOBALS['constants']['player_id'],
				'duration_name'	=>	$name
			)
		)
	);
	
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request) );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	$json = curl_exec($ch);
	curl_close($ch);
	
	$ret['json'] = $json;
	$response = json_decode($json, true);
	
	if ($response == null) $ret['code'] = RESULT_PROGRAM_ERROR;
	if ($response['error'] == true) {
		$ret['code'] = RESULT_ACTION_ERROR;
	} else {	
		if (db_durationExists($dbh, $name)) {
			$ret['code'] = RESULT_ACTION_SUCCESS;
		} else {
			$ret['code'] = RESULT_ACTION_FAILURE;
		}
	}
	
	return $ret;
}

function test_deleteDuration($dbh, $id) {

	$url = 'http://localhost/signage/index.php';
	$ch = curl_init($url);
	
	$request = array(
		'action'	=>	'delete',
		'delete'		=>	array(
			'what'	=>	'duration',
			'where'	=>	array(
				'duration_id'	=>	$id,
			)
		)
	);
	
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request) );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	$json = curl_exec($ch);
	curl_close($ch);
	
	$ret['json'] = $json;
	$response = json_decode($json, true);
	
	if ($response == null) $ret['code'] = RESULT_PROGRAM_ERROR;
	if ($response['error'] == true) {
		$ret['code'] = RESULT_ACTION_ERROR;
	} else {	
		if (db_durationExistsWithID($dbh, $id)) {
			$ret['code'] = RESULT_ACTION_FAILURE;
		} else {
			$ret['code'] = RESULT_ACTION_SUCCESS;
		}
	}
	
	return $ret;
}






function db_resetTestPlayer($dbh) {
	$constants = $GLOBALS['constants'];
	
	//clear test sign
	$stmt = $dbh->prepare("
		DELETE FROM `durations`
		WHERE `player_id`	= :player_id;
	");
	$stmt->bindValue(':player_id', $constants['player_id']);
	$stmt->execute();
	
	//reinsert test targets
	$ids = array();
	
	$stmt = $dbh->prepare("
		INSERT INTO `durations`
		(`duration_id`, `content_id`, `player_id`, `user_id`, `duration_name`, `date_start`, `date_end`)
		VALUES
		(NULL, :content_id, :player_id, :user_id, :duration_name, CURRENT_DATE(), CURRENT_DATE());
	");
	
	$stmt->bindValue(':content_id',			$constants['content_id']);
	$stmt->bindValue(':player_id',			$constants['player_id']);
	$stmt->bindValue(':user_id',			$constants['user_id_mine']);
	$stmt->bindValue(':duration_name',		'Test duration owned');
	if ($stmt->execute()) {
		$ids['mine'] = $dbh->lastInsertId();
	}

	$stmt->bindValue(':user_id',			$constants['user_id_other']);
	$stmt->bindValue(':duration_name',		'Test duration unowned');
	if ($stmt->execute()) {
		$ids['other'] = $dbh->lastInsertId();
	}

	//return ids of test duration targets	
	return $ids;
}

function db_setPermissions($dbh, $perms) {
	$constants = $GLOBALS['constants'];
	
	//print_r($perms);
	
	//Global permissions
	$stmt = $dbh->prepare("
	UPDATE
		`privs_global`
	SET 
		`perm_superuser` = :perm_superuser,
		`perm_player_read` = :perm_player_read,
		`perm_player_write` = :perm_player_write
	WHERE
		`user_id` = :user_id;
	");
	
	$stmt->bindValue(':perm_superuser',				$perms['superuser']);
	$stmt->bindValue(':perm_player_read',			$perms['global_player_read']);
	$stmt->bindValue(':perm_player_write',			$perms['global_player_write']);
	$stmt->bindValue(':user_id',					$constants['user_id_mine']);
	
	$stmt->execute();
	
	//Local permissions
	$stmt = $dbh->prepare("
	UPDATE
		`privs_players`
	SET 
		`perm_player_read` = :perm_player_read,
		`perm_player_write` = :perm_player_write
	WHERE
		`user_id` = :user_id
	AND
		`player_id` = :player_id;
	");
	
	$stmt->bindValue(':perm_player_read',			$perms['local_player_read']);
	$stmt->bindValue(':perm_player_write',			$perms['local_player_write']);
	$stmt->bindValue(':user_id',					$constants['user_id_mine']);
	$stmt->bindValue(':player_id',					$constants['player_id']);

	
	$stmt->execute();
	
}

function db_durationExists($dbh, $duration_name) {
	//Use to verify whether a duration was successfully inserted
	$query = "
		SELECT `duration_id`
		FROM `durations`
		WHERE `duration_name` = :duration_name;
	";
	$stmt = $dbh->prepare($query);
	
	$stmt->bindValue(':duration_name', $duration_name);
	
	if ( $stmt->execute() ) {
		if ($row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			return true;
		}
	}
	return false;
}

function db_durationExistsWithID($dbh, $duration_id) {
	//Use to verify whether a duration was successfully inserted
	$query = "
		SELECT `duration_id`
		FROM `durations`
		WHERE `duration_id` = :duration_id;
	";
	$stmt = $dbh->prepare($query);
	
	$stmt->bindValue(':duration_id', $duration_id);
	
	if ( $stmt->execute() ) {
		if ($row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			return true;
		}
	}
	return false;
}




?>