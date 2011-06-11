<?php
// To-Do:
// - format-flowed

include_once "decodemail.php";
include_once "msgclean.php";
include_once "quoteconvert.php";
include_once "htmlconvert.php";

function to_utf8($string)
{
	if ($string != html_entity_decode(mb_convert_encoding($string, 'HTML-ENTITIES', "UTF-8"), ENT_QUOTES, 'UTF-8'))
		$string = utf8_encode($string);
	return($string);
}


function convertpost($email)
{
$params['include_bodies'] = true;
$params['decode_bodies']  = true;
$params['decode_headers'] = true;

// assuming $email contains MIME-Mail
$decoder = new Mail_mimeDecode($email);
$structure = $decoder->decode($params);
$charset = $structure->ctype_parameters['charset'];

// Böser Quickhack
// Hier wird davon ausgegangen, dass der Text entweder in 8859-1 oder UTF-8 vorliegt
$structure->headers['from'] = to_utf8($structure->headers['from']);
$structure->headers['subject'] = to_utf8($structure->headers['subject']);

if ((trim($structure->headers['subject']) == '') or (trim($structure->headers['subject']) == 'Re:'))
	$structure->headers['subject'] = '(kein Betreff)';

// resetten der Funktion
get_body_plain(true);
get_body_html(true);

$bodyhtml = get_body_html($structure,$params);

if ($bodyhtml != '')
	$message['body'] = htmlconvert($bodyhtml);
else {
	$message['body'] = get_body_plain($structure,$params);

	if (trim($message['body']) == '')
		$message['body'] = '(Kein Text)';
}
$message['body'] = str_replace("\r", "", $message['body']);

// PGP beseitigen
$message['body'] = removegpg($message['body']);

// Signatur beseitigen
$removed = removesig($message['body']);

$message['body'] = $removed['body'];

// Zeilenumbrueche anpassen
if ($bodyhtml == '')
	$message['body'] = removelinebreak($message['body']);

// Quoteebenen umstellen
$reply = ((substr(strtolower($structure->headers['subject']), 0, 3) == "re:") or
	(substr(strtolower($structure->headers['subject']), 0, 3) == "re-") or
	(sizeof($structure->headers['references']) != ""));

$message['body'] = convertquote($message['body'], $reply);

// Attribution-Line vereinheitlichen
$message['body'] = unifyattributionline($message['body']);

if ($removed['sig'] != '')
	$message['body'] .= '[hr][size=x-small][color=darkblue]'.trim($removed['sig']).'[/color][/size]';

$hostname = php_uname('n');
$from = imap_rfc822_parse_adrlist($structure->headers['from'], $hostname);
$message['from']['mailbox'] = $from[0]->mailbox;
$message['from']['host'] = $from[0]->host;
$message['from']['personal'] = $from[0]->personal;

$message['newsgroups'] = explode(',',$structure->headers['newsgroups']);
$message['subject'] = $structure->headers['subject'];
$message['date'] = strtotime($structure->headers['date']);

$sender = imap_rfc822_parse_adrlist($structure->headers['sender'], $hostname);
$message['sender']['mailbox'] = $sender[0]->mailbox;
$message['sender']['host'] = $sender[0]->host;
$message['sender']['personal'] = $sender[0]->personal;

$message['message-id'] = $structure->headers['message-id'];
$structure->headers['references'] = str_replace('><', '> <', $structure->headers['references']);
$message['references'] = preg_split("/[\s]+/", $structure->headers['references']);
$message['in-reply-to'] = $structure->headers['in-reply-to'];
$message['x-no-archive'] = $structure->headers['x-no-archive'];
$message['supersedes'] = $structure->headers['supersedes'];
$message['control'] = $structure->headers['control'];

return($message);
}
?>
