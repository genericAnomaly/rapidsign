<?php

//== MySQL credentials and server
//===============================
define('MYSQL_HOST',	'');
define('MYSQL_USER',	'');
define('MYSQL_PW',		'');
define('MYSQL_DB',		'');


//== LDAP credentials and server
//===============================
define('AD_HOST',			'');
define('AD_DN',				'');
define('AD_PW',				'');
define('AD_SCOPE',			'');
require_once('ldap.php');


//== Astra credentials and server
//===============================
define('ASTRA_URL',		'');
define('ASTRA_UN',		'');
define('ASTRA_PW',		'');
require_once('astra.php');


//== Web server settings
//===============================
define('APP_IMAGE_DIR',				'img/');
define('APP_CAN_WRITE_INVISIBLE',	false);


//== Permissions junk that should probably be dynamic and stored in SQL but would add so much dead weight and really just works this way
//===============================
define('PRIV_CAN_VIEW',		1);
define('PRIV_CAN_EDIT',		2);

define('PRIV_NONE',		0);
define('PRIV_MINE',		1);
define('PRIV_ALL',		2);

//== User experience Settings
//===============================
define('PHP_DATE_FRIENDLY',	'l, j F, Y');

?>