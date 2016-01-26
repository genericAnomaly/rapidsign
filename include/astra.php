<?php


function astra_createHandle() {
	//Create session
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_COOKIESESSION, TRUE);
	curl_setopt($ch, CURLOPT_COOKIEFILE, "cookiefile");
	curl_setopt($ch, CURLOPT_COOKIEJAR, "cookiefile");
	//curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id()); 

	//Authenticate
	curl_setopt($ch, CURLOPT_URL, ASTRA_URL.'Logon.ashx');
	$credentials = array ('username' => ASTRA_UN, 'password' => ASTRA_PW);
	$credentials = json_encode($credentials);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $credentials);
	$authenticated = curl_exec($ch);
	
	//Clear flags
	curl_setopt($ch, CURLOPT_POST, false);
	
	//Spit it out
	if (!$authenticated) return false;
	return $ch;
}

function astra_fetchSchedule($ah, $site) {	//$site MUST be a guid
	
	/*
	//Create session
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_COOKIESESSION, TRUE);
	curl_setopt($ch, CURLOPT_COOKIEFILE, "cookiefile");
	curl_setopt($ch, CURLOPT_COOKIEJAR, "cookiefile");
	//curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id()); 

	//Authenticate
	curl_setopt($ch, CURLOPT_URL, ASTRA_URL.'Logon.ashx');
	$credentials = array ('username' => ASTRA_UN, 'password' => ASTRA_PW);
	$credentials = json_encode($credentials);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $credentials);
	$authenticated = curl_exec($ch);
	*/
	
	$ch = $ah;

	//Query
	$today = new DateTime('now');
	$today = $today->format('Y-m-d');
	$filter = '(((StartDate>="'.$today.'T00:00:00")&&(EndDate<="'.$today.'T00:00:00"))&&(Location.Room.BuildingId in ("'.$site.'")))';
	$query = array(	'action'	=> 'get',
					'view'		=> 'json',
					'columns'	=> 'ActivityName|0,ParentActivityName|1,Description|2,StartDate|3,EndDate|4,StartMinute|5,EndMinute|6,ActivityTypeCode|7,CampusName|8,BuildingCode|9,RoomNumber|10,RoomName|11,SectionMeetInstanceByActivityId.SectionMeeting.PrimaryInstructorId|12,SectionMeetInstanceByActivityId.IsCancellation|13',
					'fields'	=> 'ActivityName,ParentActivityName,Description,StartDate,EndDate,StartMinute,EndMinute,ActivityTypeCode,CampusName,BuildingCode,RoomNumber,RoomName,SectionMeetInstanceByActivityId.SectionMeeting.PrimaryInstructorId,SectionMeetInstanceByActivityId.IsCancellation',
					'sortOrder'	=> 'StartMinute',
					'filter'	=> $filter,
					);
	$query = http_build_query($query);
	curl_setopt($ch, CURLOPT_URL, ASTRA_URL.'~api/calendar/calendarList?'.$query);
	curl_setopt($ch, CURLOPT_POST, false);
	$result = curl_exec($ch);

	//Parse and return
	//TODO: merge dupes?
	//TODO: only show classes that haven't ended yet
	$items = json_decode($result, true);

	$toJSON = array();
	foreach ($items['data'] as $item) {
		$entry = array();
		$entry['date'] = $item[3];
		$entry['start'] = minutesToHumanTime($item[5]);
		$entry['end'] = minutesToHumanTime($item[6]);
		$entry['minute_start'] = $item[5];
		$entry['minute_end'] = $item[6];
		$entry['room'] = $item[10];

		if ($item[7] == 1) { //Classes
			curl_setopt($ch, CURLOPT_URL, ASTRA_URL.'~api/entity/instructor/byid/'.$item[12]);
			$instructor = curl_exec($ch);
			$instructor = json_decode($instructor, true);
			$name = explode('[', $item[2]);
			$name = $name[0];
			$code = explode('/', $item[0]);
			$code = $code[0];
			$entry['code'] = $code;
			$entry['name'] = $name;
			$entry['instructor'] = $instructor['LastName'];
		} else { //Events

			$entry['code'] = 'EVENT';
			$entry['name'] = $item[0];
			$entry['instructor'] = '';
		}
		$toJSON[] = $entry;
	}
	$JSON = json_encode($toJSON); //, JSON_PRETTY_PRINT); Server bugs out if it's below PHP 5.4
	//return $JSON;
	return $toJSON;
}



function astra_fetchBuildings($ah) {
	curl_setopt($ah, CURLOPT_URL, ASTRA_URL.'~api/entity/building');
	$json = curl_exec($ah);
	$array = json_decode($json, true);
	return $array;
}


//Convenience function
function minutesToHumanTime($minutes) {
	$dt = new DateTime();
	$dt->setTime(0, $minutes, 0);
	return $dt->format('h:i A');
}



?>