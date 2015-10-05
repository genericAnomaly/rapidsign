<?php

function db_connect() {
	try {
		@$dbh = new PDO('mysql:host='.MYSQL_HOST.';dbname='.MYSQL_DB, MYSQL_USER, MYSQL_PW);
	} catch (PDOException $e) {
		die ('DB Error');
	}
	return $dbh;
}




function db_insertContent($dbh, $what) {
	$stmt = $dbh->prepare("
		INSERT INTO `content`
		(`content_id`, `user_id`, `content_name`, `content_src`)
		VALUES
		(NULL, :user_id, :content_name, :content_src);
	");
	
	$stmt->bindValue(':user_id',			$what['user_id']);
	$stmt->bindValue(':content_name',		$what['name']);
	$stmt->bindValue(':content_src',		$what['src']);
	
	if ($stmt->execute()) {
		$id = $dbh->lastInsertId();
		return $id;
	}
	return -1;
}

function db_insertDuration($dbh, $what) {
	$stmt = $dbh->prepare("
		INSERT INTO `durations`
		(`duration_id`, `content_id`, `player_id`, `user_id`, `duration_name`, `date_start`, `date_end`)
		VALUES
		(NULL, :content_id, :player_id, :user_id, :duration_name, :date_start, :date_end);
	");
	
	$stmt->bindValue(':content_id',			$what['content_id']);
	$stmt->bindValue(':player_id',			$what['player_id']);
	$stmt->bindValue(':user_id',			$what['user_id']);
	$stmt->bindValue(':duration_name',		$what['duration_name']);
	$stmt->bindValue(':date_start',			$what['date_start']);
	$stmt->bindValue(':date_end',			$what['date_end']);
	
	if ($stmt->execute()) {
		$id = $dbh->lastInsertId();
		return $id;
	}
	return -1;
}

function db_insertUser($dbh, $what) {
	$stmt = $dbh->prepare("
		INSERT INTO `users`
		(`user_id`, `ldap_upn`, `user_role`)
		VALUES
		(NULL, :ldap_upn, :user_role);
	");
	
	$stmt->bindValue(':ldap_upn',		$what['ldap_upn']);
	$stmt->bindValue(':user_role',		$what['user_role']);
	
	if ($stmt->execute()) {
		$id = $dbh->lastInsertId();
	} else {
		return -1;
	}
	
	
	//Make sure to create a row for the new user in privs_global
	$stmt = $dbh->prepare("
		INSERT INTO `privs_global`
		(`user_id`)
		VALUES
		(:user_id)
	;");
	$stmt->bindValue(':user_id',		$id);
	$stmt->execute();
	//TODO: Modify this one to automatically create a global privs row for the created user
	
	return $id;
}




function db_fetchContent($dbh, $where) {
	$array = array();
	
	$stmt = $dbh->prepare("
		SELECT `content`.*, `users`.`ldap_upn`
		FROM `content`
		INNER JOIN `users` ON `content`.`user_id` = `users`.`user_id`
		WHERE `content`.`user_id` LIKE :user_id
		ORDER BY `content_name` ASC;
	");
	
	if (!isset($where['user_id'])) $where['user_id'] = '%';
	
	$stmt->bindValue(':user_id',			$where['user_id']);
	
	if ( $stmt->execute() ) {
		while ($row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$array[] = $row;
		}
	}

	return $array;
}

function db_fetchPlayers($dbh, $where) {
	$array = array();
	$stmt = $dbh->prepare("
		SELECT *
		FROM `players`
		WHERE `player_id` LIKE :player_id
		AND `player_shortname` LIKE :player_shortname
	;");
	
	if (!isset($where['player_id'])) {
		$where['player_id'] = '%';
	}
	if (!isset($where['player_shortname'])) {
		$where['player_shortname'] = '%';
	}
	
	$stmt->bindValue(':player_id',				$where['player_id']);
	$stmt->bindValue(':player_shortname',		$where['player_shortname']);
	
	if ( $stmt->execute() ) {
		while ($row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$array[] = $row;
		}
	}
	return $array;
}

function db_restricted_fetchPlayers($dbh, $auth) {
	//If user is superuser or has sufficient global player_read, no restriction, just run the empty fetch
	if (account_isSuperuser($auth) || $auth['permissions']['global']['perm_player_read'] >= PRIV_MINE) {
		return db_fetchPlayers($dbh, array());
	}
	
	//Otherwise, restrict it to players the user in $auth has read>mine on
	$array = array();
	$stmt = $dbh->prepare("
		SELECT *
		FROM `players`
		INNER JOIN `privs_players` ON `privs_players`.`player_id` = `players`.`player_id`
		WHERE `user_id` = :user_id
		AND `perm_player_read` >= :perm_player_read
	;");
	
	$stmt->bindValue(':user_id',				$auth['user']['user_id']);
	$stmt->bindValue(':perm_player_read',		PRIV_MINE);
	
	if ( $stmt->execute() ) {
		while ($row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$array[] = $row;
		}
	}
	return $array;
}

function db_fetchDurations($dbh, $where) {
	$array = array();
	$query = "
		SELECT `durations`.*, `content`.`content_src`, `content`.`content_name`
		FROM `durations`
		INNER JOIN `content` ON `content`.`content_id` = `durations`.`content_id`
		WHERE `player_id` = :player_id";
	
	if (isset($where['user_id'])) {
		$query .= "
		AND `durations`.`user_id` = :user_id";
	}
	
	if (isset($where['range']) && $where['range'] == 'current') {	//Simpler than making every request for pruned durations spit out dates but maybe not best practice? Figure this out later (TODO)
		$query .= "
		AND `date_start` <= CURRENT_DATE()
		AND `date_end` >= CURRENT_DATE()";
	}
	$query .= "
		ORDER BY `date_start` ASC;";
	$stmt = $dbh->prepare($query);
	
	$stmt->bindValue(':player_id',			$where['player_id']);
	if (isset($where['user_id'])) $stmt->bindValue(':user_id',			$where['user_id']);
	
	if ( $stmt->execute() ) {
		while ($row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$array[] = $row;
		}
	}
	return $array;
}

function db_fetchDurationInfo($dbh, $where) {
	//Specifically for verifying ownership/permissions prior to deleting a duration
	$query = "
		SELECT `user_id`, `player_id`
		FROM `durations`
		WHERE `duration_id` = :duration_id;
	";
	$stmt = $dbh->prepare($query);
	
	$stmt->bindValue(':duration_id',			$where['duration_id']);
	
	if ( $stmt->execute() ) {
		if ($row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			return $row;
		}
	}
	return -1;
}

function db_fetchUserInfo($dbh, $where) {
	
	$stmt = $dbh->prepare("
		SELECT *
		FROM `users`
		WHERE `ldap_upn` = :ldap_upn;
	");
	//INNER JOIN `privs_global` ON `privs_global`.`user_id` = `users`.`user_id`
	
	$stmt->bindValue(':ldap_upn',			$where['ldap_upn']);
	
	if ( $stmt->execute() ) {
		if ($row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			return $row;
		} else {
			return null;
		}
	}
	
	

}

function db_fetchUserPermissions($dbh, $where) {
	$array = array();

	//Get global permissions 
	$stmt = $dbh->prepare("
		SELECT *
		FROM `privs_global`
		WHERE `user_id` = :user_id;
	");
	
	$stmt->bindValue(':user_id',			$where['user_id']);
	
	if ( $stmt->execute() ) {
		if ($row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$array['global'] = $row;
		}
	}
	
	
	//Get per-player permissions 
	$stmt = $dbh->prepare("
		SELECT *
		FROM `privs_players`
		WHERE `user_id` = :user_id;
	");
	
	$stmt->bindValue(':user_id',			$where['user_id']);

	if ( $stmt->execute() ) {
		while ($row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			$array['players'][$row['player_id']] = $row;
		}
	}
	
	
	return $array;

	
}

function db_fetchPlayerInfo($dbh, $where) {
	$stmt = $dbh->prepare("
		SELECT *
		FROM `players`
		WHERE `player_id` LIKE :player_id
		AND `player_shortname` LIKE :player_shortname
		LIMIT 1;
	");
	
	//If no values provided, just deliver the first player that exists.
	//if (!isset($where['player_id']) && !isset($where['player_shortname']))
	
	if (!isset($where['player_id'])) $where['player_id'] = '%';
	if (!isset($where['player_shortname'])) $where['player_shortname'] = '%';
	
	$stmt->bindValue(':player_id',			$where['player_id']);
	$stmt->bindValue(':player_shortname',	$where['player_shortname']);
	
	//die(json_encode($where));
	
	
	if ( $stmt->execute() ) {
		if ($row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
			return $row;
		} else {
			return null;
		}
	}
}


function db_deleteDuration($dbh, $where) {
	$stmt = $dbh->prepare("
		DELETE FROM `durations`
		WHERE `duration_id`	= :duration_id
		AND `content_id`	LIKE :content_id;
	");
	 
	//If a duration_id is provided but no content_id, content_id is unimportant and can be '%'
	if (isset($where['duration_id']) && !isset($where['content_id'])) $where['content_id'] = '%';
	 
	$stmt->bindValue(':duration_id',		$where['duration_id']);
	$stmt->bindValue(':content_id',			$where['content_id']);
	 
	if ($stmt->execute()) {
		return $stmt->rowCount();
	}
	return -1;
}

function db_deleteContent($dbh, $where) {
	$stmt = $dbh->prepare("
		DELETE FROM `content`
		WHERE `content_id` = :content_id;
	");
	 
	$stmt->bindValue(':content_id',			$where['content_id']);
	
	
	if ($stmt->execute()) {
		return $stmt->rowCount();
	}
	return -1;
}


?>