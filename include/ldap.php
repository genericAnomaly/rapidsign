<?php

define('OPTION_EMAIL_STRICT',		true);



function ad_authUser($upn, $pw) {
//precondition: $upn and $pw are assumed to be AD credentials within AD_SCOPE
//postcondition: return true if they are valid credentials, false otherwise

	$ldh = ad_connect();
	$dn = ad_fetchDN($upn, $ldh);
	$pass = ad_authDN($dn, $pw, $ldh);
	ldap_unbind($ldh);
	return $pass;
}

function ad_connect() {
//precondition:	AD_HOST, AD_DN, and AD_PW are valid
//postcondition: returns a bound LDAP handle

	$ldh = ldap_connect(AD_HOST) or die (MSG_LDAP_ERROR);
	//Set options required by Windows Server 2003
	ldap_set_option($ldh, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldh, LDAP_OPT_PROTOCOL_VERSION, 3);
	$bound = ldap_bind($ldh, AD_DN, AD_PW);
	return $ldh;
}

function ad_fetchDN($upn, $ldh) {
//precondition: $upn is a string alleged to be a UPN in AD (most likely an email address), $ldh is an LDAP handle with credentials to perform a search.
//postcondition: if an account exists within AD_SCOPE with a UPN matching $upn, return that account's distinguished name. Otherwise, return -1

	if ($ldh==false) return false;	//ERROR_AD_CONNECTION
	if (!( (OPTION_EMAIL_STRICT && strictEmail($upn)) || (!OPTION_EMAIL_STRICT && ietfEmail($upn) ) )) return false;
	$upn = ldapEscapeSearch($upn);
	$ldap_filter = "(&(userPrincipalName=$upn))";
	$ldap_search_results = ldap_search($ldh, AD_SCOPE, $ldap_filter, array("dn"));
	//ensure results were returned
	$info = ldap_get_entries($ldh, $ldap_search_results);
	if ($info['count'] != 1) {
		return false;	//ERROR_AD_NO_MATCH
	} else {
		return $info[0]['dn'];
	}
}

function ietfEmail ($email) {
//precondition: $email is a string believed to be an email address
//postcondition: if $email is a valid ietf address, return true, otherwise return false
				//NB: This function is not strictly ietf compliant and will reject rare but legal ietf formatted addresses
	$valid = filter_var($email, FILTER_VALIDATE_EMAIL);
	if (!$valid) return false;
	$clean = filter_var($email, FILTER_SANITIZE_EMAIL);
	if ($clean != $email) return false;
	return true;
}

function strictEmail ($email) {
//precondition: $email is a string believed to be an email address
//postcondition: if $email is a valid address return true. Strict version disallows all characters except letters, digits, '.' and '_' (only one @ is allowed).
	$valid = filter_var($email, FILTER_VALIDATE_EMAIL);
	if (!$valid) return false;
	$email = filter_var($email, FILTER_SANITIZE_EMAIL);
	$strip = array('!', '#', '$', '%', '&', '\'', '*', '+', '/', '=', '?', '^', '`', '{', '|', '}', '~', '[', ']', ' '); // Removed '-' (hyphen) from disallowed characters
	$clean = str_replace($strip, '', $email);
	if ($clean != $email) return false;
	if (count(explode('@', $email)) > 2) return false;
	return true;
}

function ad_authDN($dn, $pw, $ldh) {
//precondition: $dn is a distinguished name and $pw is a string alleged to be $dn's password. $ldh is an LDAP handle
//postcondition: Return true if LDAP could bind a session with the supplied credentials. Otherwise return false

	//strip sketchy characters
	$dn = str_replace("\0", '', $dn);
	$pw = str_replace("\0", '', $pw);

	//do not allow anonymous or passwordless sessions
	if ($dn == "") return false;
	if ($pw == "") return false;

	//attempt to bind
	@$bound = ldap_bind($ldh, $dn, $pw);
	return $bound;
}

function ldapEscapeSearch ($string) {
//precondition: $string is a user provided string of unknown sanitisation
//postcondition: return $string escaped to be used in an LDAP search filter query

	$escape = array(	'\\',		'*',	'(',	')',	"\0"	);
	$replace = array(	'\\5c',		'\\2a',	'\\28',	'\\29',	'\\00'	);
	return str_replace($escape, $replace, $string);
}

?>