#!/usr/bin/php
<?php
$authurl = 'http://10.10.14.50/syncom/api/auth/';

$stdin = fread(STDIN, 4096);

preg_match('/ClientAuthname: (?<user>\S+)(.+)ClientPassword: (?<password>\S+)/s', $stdin, $match);

$match['user'] = str_replace('%20', ' ', $match['user']);

$ch = curl_init();

$postdata = array('username' => $match['user'], 
		'password' => $match['password']);

// setze die URL und andere Optionen
curl_setopt($ch, CURLOPT_URL, $authurl);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1); 
curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
curl_setopt($ch, CURLOPT_HEADER, 1);

// führe die Aktion aus und gebe die Daten an den Browser weiter
$output = curl_exec($ch);

$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// schliesse den cURL-Handle und gebe die Systemresourcen frei
curl_close($ch);

if ($httpcode != '200') {
	fwrite(STDOUT, 'invalid user or password '.$httpcode);
	exit(-1);
} else {
	fwrite(STDOUT, 'User:'.$match['user']."\n");
	exit(0);
}
?>
