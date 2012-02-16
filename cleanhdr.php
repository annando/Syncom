<?php
$stdin = "";

while (($input = fread(STDIN, 256)) != '')
	$stdin .= $input;

$mail = $stdin;

// Header und Body trennen
$pos = strpos($stdin, "\n\n");
$header = substr($stdin, 0, $pos);
$body = substr($stdin, $pos+1);

$headerdata = imap_rfc822_parse_headers($header, "news.piratenpartei.de");

if (@is_string($headerdata->message_id)) {
	$messageid = $headerdata->message_id;
	$pos = strpos($messageid, "@");

	if (!$pos) {
		$messageid = trim($messageid);
		if (substr($messageid, -1, 1) == ">") {
			$messageidnew = substr($messageid, 0, -1)."@news.piratenpartei.de>";
			$mail = str_replace($messageid, $messageidnew, $mail);
			$references = $headerdata->references;

			$pos = strrpos(" ".$references, " ");

			if ($pos > 0) {
				$lastref = substr($references, $pos);
				$mail = str_replace($lastref, $lastref." ".$messageid, $mail);
			} else {
				$pos = strpos($mail, "\n\n");
				$header = substr($mail, 0, $pos);
				$body = substr($mail, $pos+1);
				$mail = $header."\nReferences: ".$messageid."\n".$body;
			}
		}
	}
} else {
	$mail = $header."\nMessage-ID: <".date("Y.m.d.h.i.s.").uniqid()."@news.piratenpartei.de>\n".$body;
}

echo $mail;
?>
